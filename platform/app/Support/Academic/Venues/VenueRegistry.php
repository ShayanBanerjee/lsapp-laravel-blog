<?php

namespace App\Support\Academic\Venues;

use Illuminate\Support\Collection;

class VenueRegistry
{
    /** @var array<int, class-string<Venue>> */
    private const VENUES = [
        ArxivVenue::class,
        IeeeVenue::class,
        AcmVenue::class,
        SpringerVenue::class,
        NatureVenue::class,
    ];

    /** @return Collection<string, Venue> */
    public function all(): Collection
    {
        return collect(self::VENUES)
            ->map(fn (string $class) => new $class)
            ->keyBy(fn (Venue $venue) => $venue->key());
    }

    public function find(string $key): ?Venue
    {
        return $this->all()->get($key);
    }

    /** @return array<int, array<string, mixed>> */
    public function catalogue(): array
    {
        return $this->all()
            ->map(fn (Venue $venue) => [
                'key' => $venue->key(),
                'name' => $venue->name(),
                'publisher' => $venue->publisher(),
                'scope' => $venue->scope(),
                'document_class' => $venue->documentClass(),
                'editorial_system' => $venue->editorialSystem(),
                'guidelines_url' => $venue->guidelinesUrl(),
                'portal_url' => $venue->portalUrl(),
                'checklist' => $venue->checklist(),
            ])
            ->values()
            ->all();
    }
}
