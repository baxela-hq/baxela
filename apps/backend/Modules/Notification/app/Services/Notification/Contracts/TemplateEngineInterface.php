<?php

namespace Modules\Notification\Services\Notification\Contracts;

use Modules\Notification\Services\Notification\DTOs\RenderedTemplate;

interface TemplateEngineInterface
{
    public function render(
        array $variables,
        string $locale,
        string $code,
        string $audience,
    ): RenderedTemplate;
}
