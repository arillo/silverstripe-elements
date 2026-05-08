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
}
