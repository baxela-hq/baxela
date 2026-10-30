<?php

namespace Modules\Core\Actions\Admin\Timezone;

/**
 * PHP timezone identifiers grouped by region — the settings page's
 * timezone dropdown. Pure computation, no storage.
 */
class ListTimezoneAction
{
    /**
     * @return array<string, array<int, string>>
     */
    public function handle(): array
    {
        $grouped = collect(\DateTimeZone::listIdentifiers())
            ->mapToGroups(fn (string $identifier): array => [
                str_contains($identifier, '/') ? explode('/', $identifier)[0] : 'Other' => $identifier,
            ]);

        return $grouped
            ->sortBy(fn ($group, $region) => $region === 'Other' ? 'ZZZ' : $region)
            ->map(fn ($identifiers) => $identifiers->values()->all())
            ->all();
    }
}
