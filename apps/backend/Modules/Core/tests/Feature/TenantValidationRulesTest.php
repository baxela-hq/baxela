<?php

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Rules\LanguageUniquePair;
use Modules\Core\Rules\LowercaseUniquePair;
use Modules\Core\Rules\ScopedExists;
use Modules\Core\Rules\ScopedUnique;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;

uses(RefreshDatabase::class);

it('ScopedUnique counts only the active tenant rows', function () {
    $context = app(TenantContext::class);

    $insert = function (int $tenant, string $subject) use ($context): void {
        $context->runForTenant($tenant, fn () => DB::table(ContactMessageSchema::TABLE)->insert([
            TenantColumn::TENANT_ID => $tenant,
            ContactMessageSchema::NAME => 'Visitor',
            ContactMessageSchema::EMAIL => $tenant.'@example.test',
            ContactMessageSchema::SUBJECT => $subject,
            ContactMessageSchema::CONTENT => 'Body',
            ContactMessageSchema::CREATED_AT => now(),
            ContactMessageSchema::UPDATED_AT => now(),
        ]));
    };

    $translator = app(Translator::class);
    $fails = function (ValidationRule $rule, mixed $value) use ($translator): bool {
        $failed = false;
        $rule->validate('subject', $value, function (string $message) use ($translator, &$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, $translator);
        });

        return $failed;
    };

    $insert(1, 'taken-subject');
    $insert(2, 'other-store-subject');

    $context->runForTenant(1, function () use ($fails): void {
        $rule = new ScopedUnique(ContactMessageSchema::TABLE, ContactMessageSchema::SUBJECT);

        expect($fails($rule, 'other-store-subject'))->toBeFalse()
            ->and($fails($rule, 'taken-subject'))->toBeTrue();
    });
});

it('ScopedExists finds only rows owned by the active tenant', function () {
    $context = app(TenantContext::class);

    $insert = function (int $tenant, string $subject) use ($context): void {
        $context->runForTenant($tenant, fn () => DB::table(ContactMessageSchema::TABLE)->insert([
            TenantColumn::TENANT_ID => $tenant,
            ContactMessageSchema::NAME => 'Visitor',
            ContactMessageSchema::EMAIL => $tenant.'@example.test',
            ContactMessageSchema::SUBJECT => $subject,
            ContactMessageSchema::CONTENT => 'Body',
            ContactMessageSchema::CREATED_AT => now(),
            ContactMessageSchema::UPDATED_AT => now(),
        ]));
    };

    $translator = app(Translator::class);
    $fails = function (ValidationRule $rule, mixed $value) use ($translator): bool {
        $failed = false;
        $rule->validate('subject', $value, function (string $message) use ($translator, &$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, $translator);
        });

        return $failed;
    };

    $insert(1, 'own-subject');
    $insert(2, 'other-store-subject');

    $context->runForTenant(1, function () use ($fails): void {
        $rule = new ScopedExists(ContactMessageSchema::TABLE, ContactMessageSchema::SUBJECT);

        expect($fails($rule, 'own-subject'))->toBeFalse()
            ->and($fails($rule, 'other-store-subject'))->toBeTrue();
    });
});

it('LowercaseUniquePair ignores another tenant and catches a case-variant duplicate in this one', function () {
    $context = app(TenantContext::class);

    $insert = function (int $tenant, string $subject) use ($context): void {
        $context->runForTenant($tenant, fn () => DB::table(ContactMessageSchema::TABLE)->insert([
            TenantColumn::TENANT_ID => $tenant,
            ContactMessageSchema::NAME => 'Visitor',
            ContactMessageSchema::EMAIL => $tenant.'@example.test',
            ContactMessageSchema::SUBJECT => $subject,
            ContactMessageSchema::CONTENT => 'Body',
            ContactMessageSchema::CREATED_AT => now(),
            ContactMessageSchema::UPDATED_AT => now(),
        ]));
    };

    $translator = app(Translator::class);
    $fails = function (ValidationRule $rule, mixed $value) use ($translator): bool {
        $failed = false;
        $rule->validate('subject', $value, function (string $message) use ($translator, &$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, $translator);
        });

        return $failed;
    };

    $insert(1, 'Taken-Subject');
    $insert(2, 'Other-Store');

    $context->runForTenant(1, function () use ($fails): void {
        $rule = new LowercaseUniquePair(ContactMessageSchema::TABLE, ContactMessageSchema::SUBJECT);

        expect($fails($rule, 'OTHER-STORE'))->toBeFalse()
            ->and($fails($rule, 'TAKEN-subject'))->toBeTrue();
    });
});

it('LanguageUniquePair ignores another tenant and catches a duplicate slug in this one', function () {
    $context = app(TenantContext::class);
    $languageId = 1;

    $insert = function (int $tenant, string $slug) use ($context, $languageId): void {
        $context->runForTenant($tenant, function () use ($tenant, $slug, $languageId): void {
            $productId = DB::table(ProductSchema::TABLE)->insertGetId([
                TenantColumn::TENANT_ID => $tenant,
                ProductSchema::TYPE => ProductTypeEnum::SIMPLE->value,
                ProductSchema::STATUS => ProductStatusEnum::IN_STOCK->value,
                ProductSchema::IS_PUBLISHED => true,
                ProductSchema::CREATED_AT => now(),
                ProductSchema::UPDATED_AT => now(),
            ]);

            DB::table(ProductTranslationSchema::TABLE)->insert([
                TenantColumn::TENANT_ID => $tenant,
                ProductTranslationSchema::PRODUCT_ID => $productId,
                ProductTranslationSchema::LANGUAGE_ID => $languageId,
                ProductTranslationSchema::TITLE => 'Product '.$slug,
                ProductTranslationSchema::SLUG => $slug,
                ProductTranslationSchema::CONTENT => 'Body',
                ProductTranslationSchema::CREATED_AT => now(),
                ProductTranslationSchema::UPDATED_AT => now(),
            ]);
        });
    };

    $translator = app(Translator::class);
    $fails = function (ValidationRule $rule, mixed $value) use ($translator): bool {
        $failed = false;
        $rule->validate('translations.0.slug', $value, function (string $message) use ($translator, &$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, $translator);
        });

        return $failed;
    };

    request()->merge(['translations' => [['language_id' => $languageId]]]);

    $insert(1, 'taken-slug');
    $insert(2, 'other-store-slug');

    $context->runForTenant(1, function () use ($fails): void {
        $rule = new LanguageUniquePair(ProductTranslationSchema::TABLE, ProductTranslationSchema::SLUG, []);

        expect($fails($rule, 'other-store-slug'))->toBeFalse()
            ->and($fails($rule, 'taken-slug'))->toBeTrue();
    });
});

it('throws for every tenant-aware rule outside tenant mode', function () {
    $rules = [
        new ScopedUnique(ContactMessageSchema::TABLE, ContactMessageSchema::SUBJECT),
        new ScopedExists(ContactMessageSchema::TABLE, ContactMessageSchema::ID),
        new LowercaseUniquePair(ContactMessageSchema::TABLE, ContactMessageSchema::SUBJECT),
        new LanguageUniquePair(ProductTranslationSchema::TABLE, ProductTranslationSchema::SLUG, []),
    ];

    request()->merge(['translations' => [['language_id' => 1]]]);

    $translator = app(Translator::class);
    $assertThrows = function () use ($rules, $translator): void {
        foreach ($rules as $rule) {
            $attribute = $rule instanceof LanguageUniquePair ? 'translations.0.slug' : 'value';

            expect(fn () => $rule->validate(
                $attribute,
                'anything',
                static fn (string $message): PotentiallyTranslatedString => new PotentiallyTranslatedString($message, $translator),
            ))->toThrow(TenantContextMissingException::class);
        }
    };

    $context = app(TenantContext::class);
    $context->reset();
    $assertThrows();

    $context->runCentral('tenant.probe', $assertThrows);
});
