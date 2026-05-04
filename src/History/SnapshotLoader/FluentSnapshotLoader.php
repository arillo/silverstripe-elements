<?php
namespace Arillo\Elements\History\SnapshotLoader;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentSnapshotLoader implements ElementSnapshotLoader
{
    public function loadAtVersion(DataObject $holder, string $relationName, int $version): array
    {
        $locale = FluentState::singleton()->getLocale() ?: Locale::getDefault()->Locale;

        // Fetch the holder version record without locale scoping so that Fluent's
        // augmentSQL does not restrict to a locale-specific versions table join.
        $cutoff = null;
        FluentState::singleton()->withState(function ($state) use ($holder, $version, &$cutoff) {
            $state->setLocale(null);
            $holderVersion = Versioned::get_version(get_class($holder), $holder->ID, $version);
            if ($holderVersion) {
                $cutoff = $holderVersion->LastEdited;
            }
        });

        if (!$cutoff) {
            return [];
        }

        $holderField = is_a($holder, SiteTree::class) ? 'PageID' : 'ElementID';
        $baseTable = DataObject::getSchema()->tableName(ElementBase::class);
        $versionsTable = $baseTable . FluentVersionedExtension::SUFFIX_VERSIONS;
        // FluentExtension::SUFFIX = 'Localised'; getLocalisedTable() appends '_' . SUFFIX
        $localisedVersionsTable = $baseTable . '_' . FluentExtension::SUFFIX . FluentVersionedExtension::SUFFIX_VERSIONS;

        $rows = (new SQLSelect())
            ->setFrom("\"$localisedVersionsTable\" AS \"VL\"")
            ->addInnerJoin($versionsTable, "\"VL\".\"RecordID\" = \"V\".\"RecordID\" AND \"VL\".\"Version\" = \"V\".\"Version\"", 'V')
            ->setSelect(['"VL"."RecordID"', 'MAX("VL"."Version") AS "MaxVersion"'])
            ->setWhere([
                "\"V\".\"$holderField\" = ?" => $holder->ID,
                "\"V\".\"RelationName\" = ?" => $relationName,
                "\"V\".\"LastEdited\" <= ?" => $cutoff,
                '"VL"."Locale" = ?' => $locale,
            ])
            ->setGroupBy('"VL"."RecordID"')
            ->execute();

        $elements = [];
        foreach ($rows as $row) {
            FluentState::singleton()->withState(function ($state) use ($row, &$elements, $locale) {
                $state->setLocale($locale);
                $element = Versioned::get_version(ElementBase::class, $row['RecordID'], $row['MaxVersion']);
                if ($element && !$element->isArchived()) {
                    $elements[] = $element;
                }
            });
        }
        usort($elements, fn($a, $b) => $a->Sort <=> $b->Sort);
        return $elements;
    }
}
