<?php

namespace Arillo\Elements;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;

/**
 * Fixes Fluent issues during page/element duplication in SilverStripe 5:
 *  - Copies localised fields from _Localised (draft table, always current state)
 *  - Copies translations for has_many relations (e.g. Elements)
 *  - Copies FluentFilteredExtension state (visible locales)
 *  - Makes URLSegments unique per locale
 *
 * Apply to your Page class via YAML config when using Fluent:
 *
 *   Page:
 *     extensions:
 *       - Arillo\Elements\FluentDuplicateFixExtension
 *
 * Then override Page::duplicate() to fix URLSegments after all hooks complete:
 *
 *   public function duplicate(bool $doWrite = true, array|null $relations = null): static
 *   {
 *       $new = parent::duplicate($doWrite, $relations);
 *       if ($doWrite) {
 *           $ext = $new->getExtensionInstance(FluentDuplicateFixExtension::class);
 *           if ($ext) {
 *               $ext->makeURLSegmentsUnique($new);
 *           }
 *       }
 *       return $new;
 *   }
 */
class FluentDuplicateFixExtension extends Extension
{
    private static array $duplicate_relations_sort = [
        'Elements' => 'Sort ASC',
    ];

    private bool $dupFixPending = false;
    private int $dupFromID = 0;
    private string $dupFromClass = '';

    protected static array $dupFixRanFor = []; // [ClassName][ID] => true

    /**
     * Set flags BEFORE duplicate writes, so onAfterWrite() can act on them.
     */
    public function onBeforeDuplicate(
        DataObject $original,
        bool $doWrite,
        ?array &$relations,
    ): void {
        $this->dupFixPending = true;
        $this->dupFromID = (int) $original->ID;
        $this->dupFromClass = $original->ClassName;
    }

    /**
     * After duplication completes, force a second write to flush UnsavedRelationList
     * and trigger onAfterWrite() with the has_many children now persisted.
     */
    public function onAfterDuplicate(
        DataObject $original,
        bool $doWrite,
        array $relations,
    ): void {
        if ($doWrite) {
            $this->owner->forceChange();
            $this->owner->write();
        }
    }

    public function onAfterWrite(): void
    {
        if (!$this->dupFixPending) {
            return;
        }

        $id = (int) $this->owner->ID;
        if (!$id || !$this->dupFromID) {
            return;
        }

        $cls = $this->owner->ClassName;
        if (!empty(self::$dupFixRanFor[$cls][$id])) {
            return;
        }

        self::$dupFixRanFor[$cls][$id] = true;
        $this->dupFixPending = false;

        $from = DataObject::get_by_id($this->dupFromClass, $this->dupFromID);
        if (!$from) {
            return;
        }

        // Fix the record itself (copy localised translations for all locales)
        $this->fixOneRecord($from, $this->owner);

        // Fix has_many relations (e.g. Elements)
        $relSort = (array) $this->owner
            ->config()
            ->get('duplicate_relations_sort');
        foreach ($relSort as $relName => $sort) {
            $this->copyNodeRecursive(
                $from,
                $this->owner,
                (string) $relName,
                (string) $sort,
            );
        }
    }

    private function copyNodeRecursive(
        DataObject $from,
        DataObject $to,
        string $relName,
        string $sort,
    ): void {
        $this->fixOneRecord($from, $to);
        if (!$from->hasMethod($relName) || !$to->hasMethod($relName)) {
            return;
        }

        $fromChildren = $from->{$relName}()->sort($sort)->toArray();
        $toChildren = $to->{$relName}()->sort($sort)->toArray();

        $count = min(count($fromChildren), count($toChildren));
        for ($i = 0; $i < $count; $i++) {
            $a = $fromChildren[$i];
            $b = $toChildren[$i];

            if ($a->ClassName !== $b->ClassName) {
                continue;
            }

            $this->copyNodeRecursive($a, $b, $relName, $sort);
        }
    }

    private function fixOneRecord(DataObject $from, DataObject $to): void
    {
        $fromID = (int) $from->ID;
        $toID = (int) $to->ID;

        if (!$fromID || !$toID || $fromID === $toID) {
            return;
        }

        // Copy FluentFilteredExtension state (visible locales)
        if (
            $from->hasExtension(FluentFilteredExtension::class) &&
            $to->hasExtension(FluentFilteredExtension::class)
        ) {
            $this->copyFilteredLocales($from, $to, $fromID, $toID);
        }

        // Copy localised field translations
        $hasFluent =
            ($from->hasExtension(FluentVersionedExtension::class) &&
                $to->hasExtension(FluentVersionedExtension::class)) ||
            ($from->hasExtension(FluentExtension::class) &&
                $to->hasExtension(FluentExtension::class));

        if (!$hasFluent) {
            return;
        }

        $localisedTables = $to->getLocalisedTables();

        foreach ($localisedTables as $baseTable => $fields) {
            $this->copyLocalisedRows(
                $to,
                (string) $baseTable,
                $fields,
                $fromID,
                $toID,
            );
        }
    }

    /**
     * Copy all locale rows from _Localised (draft table) which always holds
     * the latest state — including unpublished translations and rows
     * written via raw SQL (e.g. by an importer).
     */
    private function copyLocalisedRows(
        DataObject $to,
        string $baseTable,
        array $fields,
        int $fromID,
        int $toID,
    ): void {
        $localisedDraft = $to->getLocalisedTable($baseTable);

        DB::prepared_query(
            "DELETE FROM \"$localisedDraft\" WHERE \"RecordID\" = ?",
            [$toID],
        );

        if (empty($fields)) {
            return;
        }

        $insertCols = '"RecordID","Locale","' . implode('","', $fields) . '"';
        $selectCols = 'S."' . implode('", S."', $fields) . '"';

        DB::prepared_query(
            "INSERT INTO \"$localisedDraft\" ($insertCols)
             SELECT ? AS \"RecordID\", S.\"Locale\", $selectCols
             FROM \"$localisedDraft\" S
             WHERE S.\"RecordID\" = ?",
            [$toID, $fromID],
        );
    }

    /**
     * Copy FluentFilteredExtension state (which locales the record is visible in).
     */
    private function copyFilteredLocales(
        DataObject $from,
        DataObject $to,
        int $fromID,
        int $toID,
    ): void {
        $baseTable = $from->baseTable();
        $filteredTable = $baseTable . '_' . FluentFilteredExtension::SUFFIX;
        $baseTableID = $baseTable . 'ID';
        $localeTable = Locale::singleton()->baseTable();
        $localeID = $localeTable . 'ID';

        DB::prepared_query(
            "DELETE FROM \"$filteredTable\" WHERE \"$baseTableID\" = ?",
            [$toID],
        );

        DB::prepared_query(
            "INSERT INTO \"$filteredTable\" (\"$baseTableID\", \"$localeID\")
             SELECT ? AS \"$baseTableID\", \"$localeID\"
             FROM \"$filteredTable\"
             WHERE \"$baseTableID\" = ?",
            [$toID, $fromID],
        );
    }

    /**
     * Make URLSegments unique per locale after duplication.
     * Must be called from Page::duplicate() after all extensions complete.
     */
    public function makeURLSegmentsUnique(DataObject $page): void
    {
        if (!$page instanceof SiteTree) {
            return;
        }

        $toID = (int) $page->ID;
        $parentID = (int) $page->ParentID;
        $localisedTable = 'SiteTree_Localised';

        $locales = Locale::get();

        foreach ($locales as $locale) {
            $localeCode = $locale->Locale;

            $current = DB::prepared_query(
                "SELECT \"URLSegment\" FROM \"$localisedTable\"
                 WHERE \"RecordID\" = ? AND \"Locale\" = ?",
                [$toID, $localeCode],
            )->value();

            if (!$current) {
                continue;
            }

            $baseSegment = preg_replace('/-\d+$/', '', $current);
            $count = 2;
            $newSegment = $current;

            while (
                $this->urlSegmentExistsForLocale(
                    $newSegment,
                    $parentID,
                    $toID,
                    $localeCode,
                )
            ) {
                $newSegment = $baseSegment . '-' . $count;
                $count++;
            }

            if ($newSegment !== $current) {
                DB::prepared_query(
                    "UPDATE \"$localisedTable\"
                     SET \"URLSegment\" = ?
                     WHERE \"RecordID\" = ? AND \"Locale\" = ?",
                    [$newSegment, $toID, $localeCode],
                );
            }
        }
    }

    private function urlSegmentExistsForLocale(
        string $segment,
        int $parentID,
        int $excludeID,
        string $locale,
    ): bool {
        $localisedTable = 'SiteTree_Localised';

        $exists = DB::prepared_query(
            "SELECT COUNT(*) FROM \"$localisedTable\" L
             INNER JOIN \"SiteTree\" S ON S.\"ID\" = L.\"RecordID\"
             WHERE L.\"URLSegment\" = ?
               AND S.\"ParentID\" = ?
               AND L.\"RecordID\" != ?
               AND L.\"Locale\" = ?",
            [$segment, $parentID, $excludeID, $locale],
        )->value();

        return (int) $exists > 0;
    }
}
