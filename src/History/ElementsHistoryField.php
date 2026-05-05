<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use Arillo\Elements\History\Diff\DiffTree;
use Arillo\Elements\History\SnapshotLoader\ElementSnapshotLoader;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

class ElementsHistoryField extends FormField
{
    private DataObject $holder;
    private string $relationName;
    private ?DiffTree $diffTree = null;
    private ?int $oldVersion = null;
    private ?int $newVersion = null;

    public function setHolder(DataObject $holder): self
    {
        $this->holder = $holder;
        return $this;
    }

    public function setRelationName(string $name): self
    {
        $this->relationName = $name;
        return $this;
    }

    public function setDiffTree(DiffTree $tree): self
    {
        $this->diffTree = $tree;
        return $this;
    }

    public function setVersions(int $old, int $new): self
    {
        $this->oldVersion = $old;
        $this->newVersion = $new;
        return $this;
    }

    public function Field($properties = [])
    {
        if ($this->diffTree !== null) {
            return $this->renderCompare();
        }
        return $this->renderSingleVersion();
    }

    /**
     * Hook for SS DiffTransformation. When two versions are being compared,
     * the form factory invokes this. We replace the field with a clone in
     * compare mode, computing a DiffTree from the configured versions.
     */
    public function performDiffTransformation($trans)
    {
        $newField = clone $this;
        if ($newField->oldVersion === null || $newField->newVersion === null) {
            // Default: compare current page version with the one immediately preceding it.
            $newField->oldVersion = max(1, ($this->holder->Version ?? 1) - 1);
            $newField->newVersion = $this->holder->Version ?? 1;
        }
        $newField->buildDiffTree();
        return $newField;
    }

    private function buildDiffTree(): void
    {
        $comparator = new ElementsTreeComparator(
            $this->holder,
            $this->relationName,
            $this->getLoader(),
            new FieldDiffer(),
            ElementBase::config()->get('history_max_depth'),
        );
        $this->diffTree = $comparator->compare($this->oldVersion, $this->newVersion);
    }

    private function renderSingleVersion(): string
    {
        $loader = $this->getLoader();
        $version = $this->holder->Version;
        $elements = $loader->loadAtVersion($this->holder, $this->relationName, $version);

        $items = [];
        foreach ($elements as $el) {
            $items[] = ArrayData::create([
                'Summary' => $el->getCMSSummary(),
                'Title' => $el->Title,
            ]);
        }

        return $this->customise([
            'Items' => ArrayList::create($items),
            'Mode' => 'single',
        ])->renderWith('Arillo/Elements/History/ElementsHistoryField');
    }

    private function renderCompare(): string
    {
        return $this->customise([
            'DiffTree' => $this->wrapDiffTree($this->diffTree),
            'Mode' => 'compare',
        ])->renderWith('Arillo/Elements/History/ElementsHistoryField');
    }

    /**
     * SilverStripe's template engine can't read public properties on plain
     * PHP objects, so we wrap the DiffTree's value objects in ArrayData
     * (which exposes properties via getField()).
     */
    private function wrapDiffTree(DiffTree $tree): ArrayData
    {
        return ArrayData::create([
            'reorder' => $tree->reorder ? $this->wrapElementDiff($tree->reorder) : null,
            'changes' => ArrayList::create(array_map(
                fn($d) => $this->wrapElementDiff($d),
                $tree->changes,
            )),
        ]);
    }

    private function wrapElementDiff(\Arillo\Elements\History\Diff\ElementDiff $d): ArrayData
    {
        return ArrayData::create([
            'status' => $d->status,
            'elementId' => $d->elementId,
            'elementClass' => $d->elementClass,
            'elementSummary' => $d->elementSummary,
            'oldSort' => $d->oldSort,
            'newSort' => $d->newSort,
            'errorMessage' => $d->errorMessage,
            'fieldChanges' => ArrayList::create(array_map(
                fn($f) => $this->wrapFieldDiff($f),
                $d->fieldChanges,
            )),
            'childChanges' => ArrayList::create(array_map(
                fn($c) => $this->wrapElementDiff($c),
                $d->childChanges,
            )),
        ]);
    }

    private function wrapFieldDiff(\Arillo\Elements\History\Diff\FieldDiff $f): ArrayData
    {
        return ArrayData::create([
            'fieldName' => $f->fieldName,
            'fieldLabel' => $f->fieldLabel,
            'kind' => $f->kind,
            'oldValue' => $f->oldValue,
            'newValue' => $f->newValue,
        ]);
    }

    private function getLoader(): ElementSnapshotLoader
    {
        return new StandardSnapshotLoader();
    }
}
