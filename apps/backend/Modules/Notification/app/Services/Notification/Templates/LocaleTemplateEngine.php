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
        $subjectKey = "$translationBaseKey.subject";
        $bodyKey = "$translationBaseKey.body";

        $subjectContent = null;
        $bodyContent = null;

        try {
            $subjectContent = __($subjectKey);
            $bodyContent = __($bodyKey);

        } catch (\Throwable $th) {
            Log::error("Error rendering template {$translationBaseKey}: ".$th->getMessage());
        }

        return new RenderedTemplate($subjectContent, $bodyContent);
    }
}
