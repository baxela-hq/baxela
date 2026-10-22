<?php

namespace Modules\Notification\Services\Notification\Templates;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Services\Notification\Contracts\TemplateEngineInterface;
use Modules\Notification\Services\Notification\DTOs\RenderedTemplate;

class LocaleTemplateEngine implements TemplateEngineInterface
{
    public function render(array $variables, string $locale, string $code, string $audience): RenderedTemplate
    {
        $codeArr = explode('.', $code);
        $module = $codeArr[0];
        $entity = $codeArr[1];
        $action = $codeArr[2];

        $translationBaseKey = Module::NAME_LOWER."::notifications.$locale.$module.$audience.$entity.$action";

        // Lang files key their entries by target locale, so the lookup must
        // run against that locale's group — not whatever the app locale
        // happens to be (e.g. a queued job's default).
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        $subjectContent = null;
        $bodyContent = null;

        try {
            // The locale is part of the KEY, so Laravel's fallback locale
            // cannot rescue a missing group — an absent locale would render
            // the raw key as the customer's subject. Retry against the
            // reference `en` group before giving up.
            $subjectContent = $this->lookup("$translationBaseKey.subject", $locale, $variables);
            $bodyContent = $this->lookup("$translationBaseKey.body", $locale, $variables);
        } catch (\Throwable $th) {
            Log::error("Error rendering template {$translationBaseKey}: ".$th->getMessage());
        } finally {
            app()->setLocale($previousLocale);
        }

        return new RenderedTemplate($subjectContent, $bodyContent);
    }

    /**
     * Resolve a template line, degrading to the `en` group when the
     * requested locale has no entry for it.
     *
     * @param  array<string, mixed>  $variables
     */
    private function lookup(string $key, string $locale, array $variables): ?string
    {
        $line = __($key, $variables);

        if (is_string($line) && $line !== $key) {
            return $line;
        }

        $fallbackKey = (string) str_replace(
            "notifications.{$locale}.",
            'notifications.en.',
            $key,
        );

        if ($fallbackKey === $key) {
            return null;
        }

        $fallback = __($fallbackKey, $variables);

        return is_string($fallback) && $fallback !== $fallbackKey ? $fallback : null;
    }
}
