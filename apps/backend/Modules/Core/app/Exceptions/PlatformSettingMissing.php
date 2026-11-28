<?php

namespace Modules\Core\Exceptions;

use Throwable;

/**
 * A required platform setting is absent or unreadable (multi-tenancy
 * §6.7 item 7). The message is safe — it names the setting, not its
 * value — but the outcome is always a 500: this is installation
 * misconfiguration, and no store value may stand in for it.
 */
class PlatformSettingMissing extends BaseException
{
    public function __construct(
        public readonly string $settingName,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            code: 'core.platform_setting.missing',
            httpStatus: 500,
            meta: ['setting' => $settingName],
            isSafe: true,
            previous: $previous,
            messageParams: ['name' => $settingName],
        );
    }
}
