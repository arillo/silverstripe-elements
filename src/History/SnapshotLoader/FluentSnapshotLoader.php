<?php
namespace Arillo\Elements\History\SnapshotLoader;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentSnapshotLoader implements ElementSnapshotLoader
{
    public function loadAtVersion(DataObject $holder, string $relationName, int $version): array
    {
        $cutoff = $this->resolveCutoff($holder, $version);
        if (!$cutoff) {
            return [];
        }
        return $this->loadAtCutoff($holder, $relationName, $cutoff);
    }

    public function resolveCutoff(DataObject $holder, int $version): ?string
    {
        // Resolve with locale scoping disabled, so FluentVersionedExtension::augmentSQL
        // doesn't restrict the lookup to a single localised versions table.
        $cutoff = null;
        FluentState::singleton()->withState(function ($state) use ($holder, $version, &$cutoff) {
            $state->setLocale(null);
            $holderVersion = Versioned::get_version(get_class($holder), $holder->ID, $version);
            if ($holderVersion) {
                $cutoff = $holderVersion->LastEdited;
            }
        });
        return $cutoff;
    }

    public function loadAtCutoff(DataObject $holder, string $relationName, ?string $cutoff): array
    {
        if (!$cutoff) {
            return [];
        }

        $locale = FluentState::singleton()->getLocale();
        if (!$locale) {
            $default = Locale::getDefault();
            $locale = $default ? $default->Locale : null;
        }
        if (!$locale) {
            return [];
        }

        $holderField = is_a($holder, SiteTree::class) ? 'PageID' : 'ElementID';
        $elements = [];

        // Versioned archive mode rewrites all tables in the class hierarchy
        // to their _Versions counterparts and applies WasDeleted=0; Fluent's
        // augmentSQL hooks into 'archive' mode and rewrites the localised
        // tables plus locale fallback chain. Subclass fields are joined via
        // the regular schema so any subclass adding its own DB fields is
        // included in the snapshot.
        FluentState::singleton()->withState(function ($state) use (
            $locale,
            $holder,
            $holderField,
            $relationName,
            $cutoff,
            &$elements,
        ) {
            $state->setLocale($locale);
            Versioned::withVersionedMode(function () use (
                $holder,
                $holderField,
                $relationName,
                $cutoff,
                &$elements,
            ) {
                Versioned::reading_archived_date($cutoff);
                $elements = ElementBase::get()
                    ->filter([
                        $holderField => $holder->ID,
                        'RelationName' => $relationName,
                    ])
                    ->sort('Sort', 'ASC')
                    ->toArray();
            });
        });

        return $elements;
    }
}
