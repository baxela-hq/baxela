<?php

namespace Modules\Notification\Services\Notification\Contracts;

interface TemplateRepositoryInterface
{
    public function get(string $name): TemplateEngineInterface;
}
