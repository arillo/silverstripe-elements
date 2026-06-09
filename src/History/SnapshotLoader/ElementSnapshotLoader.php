<?php
namespace Arillo\Elements\History\SnapshotLoader;

use SilverStripe\ORM\DataObject;

interface ElementSnapshotLoader
{
    /**
     * Returns array of populated ElementBase records owned by $holder via $relationName at $version.
     *
     * @param DataObject $holder Versioned holder (page or parent element)
     * @param string $relationName e.g. 'Elements', 'Downloads'
     * @param int $version Version number of the holder
     * @return \Arillo\Elements\ElementBase[]
     */
    public function loadAtVersion(DataObject $holder, string $relationName, int $version): array;

    /**
     * Returns array of populated ElementBase records owned by $holder via $relationName at an
     * explicit cutoff timestamp. Used by the tree comparator so the cutoff can be derived once
     * from the top-level holder version and propagated through recursion — re-deriving cutoffs
     * from each intermediate element's own version misses deep edits made after the parent
     * element's last touch.
     *
     * @param string|null $cutoff LastEdited cutoff (DB format). Null/empty returns [].
     * @return \Arillo\Elements\ElementBase[]
     */
    public function loadAtCutoff(DataObject $holder, string $relationName, ?string $cutoff): array;

    /**
     * Resolves the cutoff timestamp (holder version's LastEdited) for a given holder version,
     * or null if the version doesn't exist.
     */
    public function resolveCutoff(DataObject $holder, int $version): ?string;
}
