<?php

namespace Modules\Notification\Services\Notification\Templates;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Services\Notification\Contracts\TemplateEngineInterface;
use Modules\Notification\Services\Notification\DTOs\RenderedTemplate;

class BladeTemplateEngine implements TemplateEngineInterface
{
    public function __construct(private ViewFactory $view) {}

    public function render(array $variables, string $locale, string $code, string $audience): RenderedTemplate
    {
        $codeArr = explode('.', $code);
        $module = $codeArr[0];
        $entity = $codeArr[1];
        $action = $codeArr[2];

        $viewPathBase = Module::NAME_LOWER."::notifications.$module.$locale.$audience.$entity.$action";
        $subjectViewPath = "$viewPathBase.subject";
        $bodyViewPath = "$viewPathBase.body";

        $subjectContent = null;
        $bodyContent = null;

        try {
            $subjectContent = $this->view->make($subjectViewPath, $variables)->render();
            $bodyContent = $this->view->make($bodyViewPath, $variables)->render();

        } catch (\Throwable $th) {
            Log::error("Error rendering template {$viewPathBase}: ".$th->getMessage());
        }

        return new RenderedTemplate($subjectContent, $bodyContent);
    }
}
