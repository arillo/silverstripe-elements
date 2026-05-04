<?php
namespace Arillo\Elements\History\SnapshotLoader;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

class StandardSnapshotLoader implements ElementSnapshotLoader
{
    public function loadAtVersion(DataObject $holder, string $relationName, int $version): array
    {
        $holderVersion = Versioned::get_version(get_class($holder), $holder->ID, $version);
        if (!$holderVersion) {
            return [];
        }
        $cutoff = $holderVersion->LastEdited;

        $holderField = is_a($holder, SiteTree::class) ? 'PageID' : 'ElementID';
        $versionsTable = DataObject::getSchema()->tableName(ElementBase::class) . '_Versions';

        $rows = (new SQLSelect())
            ->setFrom("\"$versionsTable\"")
            ->setSelect(['"RecordID"', 'MAX("Version") AS "MaxVersion"'])
            ->setWhere([
                "\"$holderField\" = ?" => $holder->ID,
                "\"RelationName\" = ?" => $relationName,
                "\"LastEdited\" <= ?" => $cutoff,
            ])
            ->setGroupBy('"RecordID"')
            ->execute();

        $elements = [];
        foreach ($rows as $row) {
            $element = Versioned::get_version(ElementBase::class, $row['RecordID'], $row['MaxVersion']);
            if ($element && !$element->isArchived()) {
                $elements[] = $element;
            }
        }
        usort($elements, fn($a, $b) => $a->Sort <=> $b->Sort);
        return $elements;
    }
}
