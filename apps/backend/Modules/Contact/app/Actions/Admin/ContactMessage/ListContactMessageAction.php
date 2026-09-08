<?php

namespace Modules\Contact\Actions\Admin\ContactMessage;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListContactMessageAction extends AbstractContactMessageAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = ContactMessageSchema::TABLE.'.'.ContactMessageSchema::ID;

        return QueryBuilder::for(ContactMessage::class)
            ->allowedFilters(
                AllowedFilter::exact(ContactMessageSchema::STATUS),
                AllowedFilter::partial(ContactMessageSchema::NAME),
                AllowedFilter::partial(ContactMessageSchema::EMAIL),
                AllowedFilter::partial(ContactMessageSchema::SUBJECT),
            )
            ->allowedSorts(
                ContactMessageSchema::ID,
                ContactMessageSchema::STATUS,
                ContactMessageSchema::CREATED_AT,
            )
            ->select([
                $id,
                ContactMessageSchema::NAME,
                ContactMessageSchema::EMAIL,
                ContactMessageSchema::PHONE,
                ContactMessageSchema::SUBJECT,
                ContactMessageSchema::CONTENT,
                ContactMessageSchema::STATUS,
                ContactMessageSchema::IP_ADDRESS,
                ContactMessageSchema::TABLE.'.'.ContactMessageSchema::CREATED_AT,
                ContactMessageSchema::TABLE.'.'.ContactMessageSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
