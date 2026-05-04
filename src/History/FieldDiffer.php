<?php
namespace Arillo\Elements\History;

use Arillo\Elements\History\Diff\FieldDiff;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;

class FieldDiffer
{
    /** @return FieldDiff[] */
    public function diff(DataObject $old, DataObject $new): array
    {
        $diffs = [];
        $schema = DataObject::getSchema();
        $fields = $schema->fieldSpecs(get_class($new));

        foreach ($fields as $name => $spec) {
            if ($this->isExcluded($name)) {
                continue;
            }
            $oldValue = $old->getField($name);
            $newValue = $new->getField($name);
            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $diff = new FieldDiff();
            $diff->fieldName = $name;
            $diff->fieldLabel = $name; // i18n added in Task 8
            $diff->kind = $this->classifyDbField($new, $name);
            $diff->oldValue = $oldValue;
            $diff->newValue = $newValue;
            $diffs[] = $diff;
        }

        return $diffs;
    }

    /**
     * Compare two field values for equality.
     *
     * Scalar values use strict equality. Composite DBField objects (e.g.
     * MultiValueField) are compared by their string representation so that two
     * freshly-created instances with the same underlying data are not flagged as
     * changed.
     */
    private function valuesAreEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        // Both objects: compare by string cast (DBField::__toString returns the value)
        if (is_object($a) && is_object($b) && get_class($a) === get_class($b)) {
            return (string) $a === (string) $b;
        }
        return false;
    }

    private function isExcluded(string $fieldName): bool
    {
        return in_array($fieldName, ['Sort', 'RelationName', 'Version', 'RecordID', 'WasPublished'], true);
    }

    private function classifyDbField(DataObject $record, string $fieldName): string
    {
        $obj = $record->dbObject($fieldName);
        if ($obj instanceof DBHTMLText) {
            return 'html';
        }
        return 'text';
    }
}
