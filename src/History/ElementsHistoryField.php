<?php
namespace Arillo\Elements\History;

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

    public function Field($properties = [])
    {
        if ($this->diffTree !== null) {
            return $this->renderCompare();
        }
        return $this->renderSingleVersion();
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
        // Implemented in Task 14
        return '';
    }

    private function getLoader(): ElementSnapshotLoader
    {
        return new StandardSnapshotLoader();
    }
}
