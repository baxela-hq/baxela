<?php

namespace Modules\Auth\Tests\Feature;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/auth'.$endpoint;
    }
}
