<?php

namespace Modules\Core\Support\Erd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Discovers Eloquent models and their relation methods via reflection, and
 * turns them into app-level ERD edges and polymorphic notes.
 *
 * Safety assumptions:
 *   - only trusted application models under each module's app/Models are scanned;
 *   - relation methods are invoked on bare model instances (the scanner runs
 *     no queries itself, though a custom relation method could).
 * Every skip (required parameters, failed invocation, unsupported relation
 * type) is reported through ScanResult::$skips instead of being swallowed.
 */
class ModelRelationsScanner
{
    public function __construct(
        protected string $modulesBasePath,
    ) {}

    public function scan(): ScanResult
    {
        $models = [];
        $edges = [];
        $morphNotes = [];
        $skips = [];
        $tableModules = [];

        foreach ($this->modelFiles() as [$module, $path]) {
            $model = $this->reflectModel($path, $module, $skips);

            if ($model !== null) {
                $models[] = $model;
                $tableModules[$model->table] ??= $model->module;
            }
        }

        foreach ($models as $model) {
            $this->scanModel($model, $edges, $morphNotes, $skips);
        }

        usort($edges, static fn (ErdEdge $a, ErdEdge $b): int => [$a->fromTable, $a->toTable] <=> [$b->fromTable, $b->toTable]);
        usort($morphNotes, static fn (MorphNote $a, MorphNote $b): int => [$a->table, $a->typeColumn] <=> [$b->table, $b->typeColumn]);

        return new ScanResult($models, $edges, $morphNotes, $tableModules, $skips);
    }

    /**
     * All model files as [module name, absolute path], module by directory.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function modelFiles(): array
    {
        $found = [];

        foreach (glob($this->modulesBasePath.'/*/app/Models') ?: [] as $modelsDir) {
            $relative = substr($modelsDir, strlen($this->modulesBasePath.DIRECTORY_SEPARATOR));
            $module = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];

            foreach (File::allFiles($modelsDir) as $file) {
                if ($file->getExtension() === 'php') {
                    $found[] = [$module, $file->getPathname()];
                }
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @param  array<int, string>  $skips
     */
    protected function reflectModel(string $path, string $module, array &$skips): ?ModelInfo
    {
        $class = $this->classFromFile($path);

        if ($class === null) {
            $skips[] = basename($path).' — no model class found';

            return null;
        }

        if (! class_exists($class)) {
            require_once $path;
        }

        if (! class_exists($class)) {
            $skips[] = $path.' — class not found after require';

            return null;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
            return null;
        }

        try {
            /** @var Model $instance */
            $instance = $reflection->newInstance();
        } catch (Throwable $e) {
            $skips[] = $class.' — instantiation failed: '.$this->reason($e);

            return null;
        }

        return new ModelInfo($class, $module, $instance->getTable());
    }

    /**
     * Fully-qualified class name from the namespace declared inside the file.
     */
    protected function classFromFile(string $path): ?string
    {
        $contents = (string) file_get_contents($path);

        if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return null;
        }

        $class = basename($path, '.php');

        if (! preg_match('/\b(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+'.preg_quote($class, '/').'\b/', $contents)) {
            return null;
        }

        return $matches[1].'\\'.$class;
    }

    /**
     * Collects relation edges and morph notes for one model's relation methods.
     *
     * @param  array<int, ErdEdge>  $edges
     * @param  array<int, MorphNote>  $morphNotes
     * @param  array<int, string>  $skips
     */
    protected function scanModel(ModelInfo $model, array &$edges, array &$morphNotes, array &$skips): void
    {
        $reflection = new ReflectionClass($model->class);
        $instance = $reflection->newInstance();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $this->isRelationMethod($method)) {
                continue;
            }

            $label = $model->class.'::'.$method->name;

            if ($method->getNumberOfRequiredParameters() > 0) {
                $skips[] = $label.' — requires parameters';

                continue;
            }

            try {
                $relation = $method->invoke($instance);
            } catch (Throwable $e) {
                $skips[] = $label.' — invocation failed: '.$this->reason($e);

                continue;
            }

            if (! $relation instanceof Relation) {
                $skips[] = $label.' — did not return a relation';

                continue;
            }

            $this->classifyRelation($model, $method->name, $relation, $edges, $morphNotes, $skips);
        }
    }

    /**
     * Public, non-static, declared by the model or its traits (never
     * inherited from Illuminate), returning a class named type.
     */
    protected function isRelationMethod(ReflectionMethod $method): bool
    {
        if ($method->isStatic() || str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return false;
        }

        $returnType = $method->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return false;
        }

        return is_a($returnType->getName(), Relation::class, true);
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     * @param  array<int, ErdEdge>  $edges
     * @param  array<int, MorphNote>  $morphNotes
     * @param  array<int, string>  $skips
     */
    protected function classifyRelation(ModelInfo $model, string $methodName, Relation $relation, array &$edges, array &$morphNotes, array &$skips): void
    {
        $label = $model->class.'::'.$methodName;

        if ($relation instanceof MorphTo) {
            $morphNotes[] = new MorphNote($relation->getParent()->getTable(), $relation->getMorphType(), $relation->getForeignKeyName());

            return;
        }

        if ($relation instanceof MorphToMany) {
            $skips[] = $label.' — morph-to-many unsupported';

            return;
        }

        if ($relation instanceof HasOneThrough || $relation instanceof HasManyThrough) {
            $skips[] = $label.' — through relation unsupported';

            return;
        }

        if ($relation instanceof MorphOneOrMany) {
            $morphNotes[] = new MorphNote(
                $relation->getRelated()->getTable(),
                $relation->getMorphType(),
                $relation->getForeignKeyName(),
                [$this->morphAlias($model->class)],
            );

            return;
        }

        if ($relation instanceof BelongsToMany) {
            $pivot = $relation->getTable();

            $edges[] = new ErdEdge($pivot, $model->table, [$relation->getForeignPivotKeyName()], [$relation->getParentKeyName()], ErdEdge::KIND_APP);
            $edges[] = new ErdEdge($pivot, $relation->getRelated()->getTable(), [$relation->getRelatedPivotKeyName()], [$relation->getRelatedKeyName()], ErdEdge::KIND_APP);

            return;
        }

        if ($relation instanceof BelongsTo) {
            $edges[] = new ErdEdge($model->table, $relation->getRelated()->getTable(), [$relation->getForeignKeyName()], [$relation->getOwnerKeyName()], ErdEdge::KIND_APP);

            return;
        }

        if ($relation instanceof HasOneOrMany) {
            $edges[] = new ErdEdge($relation->getRelated()->getTable(), $model->table, [$relation->getForeignKeyName()], [$relation->getLocalKeyName()], ErdEdge::KIND_APP);

            return;
        }

        $skips[] = $label.' — unsupported relation '.class_basename($relation);
    }

    /**
     * Morph alias for a model from the registered morph map, or its
     * lowercased basename when unmapped.
     */
    protected function morphAlias(string $class): string
    {
        $alias = array_search($class, Relation::morphMap(), true);

        return is_string($alias) ? $alias : strtolower(class_basename($class));
    }

    protected function reason(Throwable $e): string
    {
        return mb_substr($e->getMessage(), 0, 80);
    }
}
