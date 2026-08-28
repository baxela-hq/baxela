<?php

namespace Modules\User\Actions\User\Profile;

use Modules\Core\Contracts\Events\User\UserProfileUpdatedEvent;
use Modules\Core\Utils\Auth;
use Modules\User\Http\Requests\User\Profile\ProfileRequest;
use Modules\User\Schemas\Profile\ProfileSchema;

class UpdateProfileAction extends AbstractProfileAction
{
    public function handle(ProfileRequest $request)
    {
        $record = $this->model->getByUserId(Auth::id());

        if (is_null($record)) {
            $body = $request->validated();
            $body[ProfileSchema::USER_ID] = Auth::id();
            $record = $this->model->create($body);
            $record = $record->fresh();
        } else {
            $record->update($request->validated());
        }

        event(UserProfileUpdatedEvent::fill($record->toArray()));

        return $record;
    }
}
