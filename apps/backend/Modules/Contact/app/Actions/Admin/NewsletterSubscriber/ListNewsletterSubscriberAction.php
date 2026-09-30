<?php

namespace Modules\Contact\Actions\Admin\NewsletterSubscriber;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListNewsletterSubscriberAction extends AbstractNewsletterSubscriberAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = NewsletterSubscriberSchema::TABLE.'.'.NewsletterSubscriberSchema::ID;

        return QueryBuilder::for(NewsletterSubscriber::class)
            ->allowedFilters(
                AllowedFilter::exact(NewsletterSubscriberSchema::STATUS),
                AllowedFilter::partial(NewsletterSubscriberSchema::EMAIL),
            )
            ->allowedSorts(
                NewsletterSubscriberSchema::ID,
                NewsletterSubscriberSchema::CREATED_AT,
            )
            ->select([
                $id,
                NewsletterSubscriberSchema::EMAIL,
                NewsletterSubscriberSchema::LOCALE,
                NewsletterSubscriberSchema::STATUS,
                NewsletterSubscriberSchema::TABLE.'.'.NewsletterSubscriberSchema::CREATED_AT,
                NewsletterSubscriberSchema::TABLE.'.'.NewsletterSubscriberSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
