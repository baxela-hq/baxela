<?php

namespace Modules\Core\Tests\Feature\Sentry;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Records everything the Sentry client would send, so error-reporting
 * behaviour can be asserted without any network access.
 */
class RecordingTransport implements TransportInterface
{
    /** @var Event[] */
    public array $events = [];

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
