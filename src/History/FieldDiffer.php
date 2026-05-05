<?php
namespace Arillo\Elements\History;

use Arillo\Elements\History\Diff\FieldDiff;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;

class FieldDiffer
{
    /** @return FieldDiff[] */
    public function diff(DataObject $old, DataObject $new): array
    {
        return array_merge(
            $this->diffDbFields($old, $new),
            $this->diffHasOne($old, $new),
            $this->diffManyMany($old, $new),
        );
    }

    private function diffDbFields(DataObject $old, DataObject $new): array
    {
        $diffs = [];
        $schema = DataObject::getSchema();
        $fields = $schema->fieldSpecs(get_class($new));
        $hasOneRelations = $new->config()->get('has_one') ?? [];

        foreach ($fields as $name => $spec) {
            if ($this->isExcluded($name)) {
                continue;
            }
            // Skip the *ID FK columns of has_one relations; diffHasOne handles those.
            if (str_ends_with($name, 'ID')
                && isset($hasOneRelations[substr($name, 0, -2)])
            ) {
                continue;
            }

            $oldValue = $old->getField($name);
            $newValue = $new->getField($name);
            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $diff = new FieldDiff();
            $diff->fieldName = $name;
            $diff->fieldLabel = $name;
            $diff->kind = $this->classifyDbField($new, $name);
            $diff->oldValue = $oldValue;
            $diff->newValue = $newValue;
            $diffs[] = $diff;
        }

        return $diffs;
    }

    private function diffHasOne(DataObject $old, DataObject $new): array
    {
        $diffs = [];
        $hasOne = $new->config()->get('has_one') ?? [];

        foreach ($hasOne as $name => $targetClass) {
            $oldId = (int) $old->getField($name . 'ID');
            $newId = (int) $new->getField($name . 'ID');
            if ($oldId === $newId) {
                continue;
            }

            $diff = new FieldDiff();
            $diff->fieldName = $name;
            $diff->fieldLabel = $name;
            $diff->kind = $this->classifyHasOne($targetClass);
            $diff->oldValue = $this->renderHasOne($targetClass, $oldId);
            $diff->newValue = $this->renderHasOne($targetClass, $newId);
            $diffs[] = $diff;
        }

        return $diffs;
    }

    private function diffManyMany(DataObject $old, DataObject $new): array
    {
        $diffs = [];
        $manyMany = $new->config()->get('many_many') ?? [];

        foreach ($manyMany as $name => $targetClass) {
            $oldList = $this->renderManyMany($old, $name);
            $newList = $this->renderManyMany($new, $name);
            if ($oldList === $newList) {
                continue;
            }

            $diff = new FieldDiff();
            $diff->fieldName = $name;
            $diff->fieldLabel = $name;
            $diff->kind = 'many_many';
            $diff->oldValue = $oldList;
            $diff->newValue = $newList;
            $diffs[] = $diff;
        }

        return $diffs;
    }

    private function classifyHasOne(string $targetClass): string
    {
        if (is_a($targetClass, Image::class, true)) {
            return 'has_one_image';
        }
        if (is_a($targetClass, File::class, true)) {
            return 'has_one_file';
        }
        return 'has_one';
    }

    private function renderHasOne(string $targetClass, int $id): string
    {
        if (!$id) {
            return '(none)';
        }
        $obj = DataObject::get_by_id($targetClass, $id);
        if (!$obj || !$obj->exists()) {
            return "(missing #$id)";
        }
        $title = $obj->Title ?: ($obj->Name ?? '') ?: ($obj->Filename ?? '');
        return trim("$title (#$id)");
    }

    private function renderManyMany(DataObject $record, string $relationName): string
    {
        if (!$record->ID) {
            return '';
        }
        $list = $record->{$relationName}();
        $items = [];
        foreach ($list as $item) {
            $title = $item->Title ?: ($item->Name ?? '');
            $items[] = trim("$title (#" . $item->ID . ")");
        }
        sort($items);
        return implode(', ', $items);
    }

    private function valuesAreEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
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
