<?php
namespace Arillo\Elements\History\SnapshotLoader;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class StandardSnapshotLoader implements ElementSnapshotLoader
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
        $holderVersion = Versioned::get_version(get_class($holder), $holder->ID, $version);
        return $holderVersion ? $holderVersion->LastEdited : null;
    }

    public function loadAtCutoff(DataObject $holder, string $relationName, ?string $cutoff): array
    {
        if (!$cutoff) {
            return [];
        }

        $holderField = is_a($holder, SiteTree::class) ? 'PageID' : 'ElementID';

        // Use Versioned's archive reading mode so the ORM rewrites every
        // table in the class hierarchy to its _Versions counterpart, picks
        // the latest version <= cutoff per record, and applies WasDeleted=0
        // automatically. Subclass-specific fields (added on extending
        // ElementBase) are joined via the standard schema, so they are
        // included in the snapshot rather than dropped by a base-only query.
        return Versioned::withVersionedMode(function () use ($holder, $holderField, $relationName, $cutoff) {
            Versioned::reading_archived_date($cutoff);
            return ElementBase::get()
                ->filter([
                    $holderField => $holder->ID,
                    'RelationName' => $relationName,
                ])
                ->sort('Sort', 'ASC')
                ->toArray();
        });
    }
}
