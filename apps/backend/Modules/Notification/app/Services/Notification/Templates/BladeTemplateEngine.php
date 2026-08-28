<?php

namespace Modules\Notification\Services\Notification\Templates;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Services\Notification\Contracts\TemplateEngineInterface;
use Modules\Notification\Services\Notification\DTOs\RenderedTemplate;
use Throwable;

class BladeTemplateEngine implements TemplateEngineInterface
{
    public function __construct(private ViewFactory $view) {}

    public function render(array $variables, string $locale, string $code, string $audience): RenderedTemplate
    {
        [$module, $entity, $action] = array_pad(explode('.', $code), 3, null);

        foreach ($this->candidateLocales($locale) as $candidate) {
            $viewPathBase = Module::NAME_LOWER."::notifications.$module.$candidate.$audience.$entity.$action";
            $subjectViewPath = "$viewPathBase.subject";
            $bodyViewPath = "$viewPathBase.body";

            if (! $this->view->exists($subjectViewPath) || ! $this->view->exists($bodyViewPath)) {
                continue;
            }

            try {
                $subject = $this->view->make($subjectViewPath, $variables)->render();
                $body = $this->view->make($bodyViewPath, $variables)->render();

                return new RenderedTemplate($subject, $body);
            } catch (Throwable $th) {
                Log::error("Error rendering template {$viewPathBase}: ".$th->getMessage());
            }
        }

        return new RenderedTemplate(null, '');
    }

    /**
     * Locale candidates in order of preference: the requested locale first,
     * then the configured fallback, then English — so a locale for which no
     * templates are shipped still renders instead of failing the request.
     *
     * @return array<int, string>
     */
    private function candidateLocales(string $locale): array
    {
        return collect([$locale, config('app.fallback_locale'), 'en'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
