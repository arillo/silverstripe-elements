<?php
namespace Arillo\Elements\History\SnapshotLoader;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
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
            // Skip records whose latest-at-cutoff version was a deletion.
            // (Stage-aware isArchived() reflects the current state, which is
            // wrong for historical version snapshots — a record archived
            // at newV would otherwise also drop out of the oldV snapshot.)
            $wasDeleted = false;
            foreach (DB::prepared_query(
                "SELECT \"WasDeleted\" FROM \"$versionsTable\" WHERE \"RecordID\" = ? AND \"Version\" = ?",
                [$row['RecordID'], $row['MaxVersion']]
            ) as $vRow) {
                $wasDeleted = (bool) $vRow['WasDeleted'];
                break;
            }
            if ($wasDeleted) {
                continue;
            }

            $element = Versioned::get_version(ElementBase::class, $row['RecordID'], $row['MaxVersion']);
            if ($element) {
                $elements[] = $element;
            }
        }
        usort($elements, fn($a, $b) => $a->Sort <=> $b->Sort);
        return $elements;
    }
}
