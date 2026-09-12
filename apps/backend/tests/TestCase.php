<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Core\Models\Language;

abstract class TestCase extends BaseTestCase
{
    /**
     * The default English language row, shared by every module whose
     * translation payloads resolve their `language` codes against it.
     */
    public static function defaultLanguage(): Language
    {
        return Language::query()->firstOrCreate(
            ['code' => 'en'],
            ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
        );
    }
}
