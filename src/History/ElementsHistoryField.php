<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use Arillo\Elements\History\Diff\DiffTree;
use Arillo\Elements\History\SnapshotLoader\ElementSnapshotLoader;
use Arillo\Elements\History\SnapshotLoader\FluentSnapshotLoader;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\HtmlDiff;

class ElementsHistoryField extends HTMLReadonlyField
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
        return $this->Value();
    }

    public function Value()
    {
        if ($this->diffTree !== null) {
            return $this->renderCompare();
        }
        return $this->renderSingleVersion();
    }

    /**
     * The React form schema reads state['value'] (defaults to dataValue()).
     * Our content is dynamically rendered, not stored in $value, so we put
     * it into the schema state explicitly here.
     */
    public function getSchemaStateDefaults()
    {
        $state = parent::getSchemaStateDefaults();
        $state['value'] = (string) $this->Value();
        return $state;
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
        $data = [
            'fieldName' => $f->fieldName,
            'fieldLabel' => $f->fieldLabel,
            'kind' => $f->kind,
            'oldValue' => is_array($f->oldValue) ? ArrayData::create($f->oldValue) : $f->oldValue,
            'newValue' => is_array($f->newValue) ? ArrayData::create($f->newValue) : $f->newValue,
        ];

        // For string-valued kinds, pre-compute an inline ins/del diff using
        // SilverStripe's HtmlDiff so the rendered output matches the standard
        // page-CMS-field diff style. has_one_image is structural (array of
        // {thumbnailUrl,filename,id}) and is rendered side-by-side instead.
        if ($f->kind !== 'has_one_image' && is_string($f->oldValue) && is_string($f->newValue)) {
            $escape = $f->kind !== 'html';
            $data['inlineDiff'] = HtmlDiff::compareHtml(
                $f->oldValue,
                $f->newValue,
                $escape,
            );
        } elseif ($f->kind === 'settings') {
            // settings values can be scalar (bool/int/string) or null
            $oldStr = $f->oldValue === null ? '' : (string) $f->oldValue;
            $newStr = $f->newValue === null ? '' : (string) $f->newValue;
            $data['inlineDiff'] = HtmlDiff::compareHtml($oldStr, $newStr, true);
        }

        return ArrayData::create($data);
    }

    private function getLoader(): ElementSnapshotLoader
    {
        if (ElementBase::singleton()->hasExtension(ElementBase::FLUENT_CLASS)) {
            return new FluentSnapshotLoader();
        }
        return new StandardSnapshotLoader();
    }
}
