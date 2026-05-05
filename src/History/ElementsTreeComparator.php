<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use Arillo\Elements\History\Diff\DiffTree;
use Arillo\Elements\History\Diff\ElementDiff;
use Arillo\Elements\History\SnapshotLoader\ElementSnapshotLoader;
use SilverStripe\ORM\DataObject;

class ElementsTreeComparator
{
    public function __construct(
        private DataObject $holder,
        private string $relationName,
        private ElementSnapshotLoader $loader,
        private FieldDiffer $fieldDiffer,
        private ?int $maxDepth = null,
    ) {}

    public function compare(int $oldVersion, int $newVersion): DiffTree
    {
        return $this->compareLevel(
            $this->holder,
            $this->relationName,
            $oldVersion,
            $newVersion,
            0,
        );
    }

    private function compareLevel(
        DataObject $holder,
        string $relationName,
        int $oldVersion,
        int $newVersion,
        int $depth,
    ): DiffTree {
        $tree = new DiffTree();

        $oldElements = $this->loader->loadAtVersion($holder, $relationName, $oldVersion);
        $newElements = $this->loader->loadAtVersion($holder, $relationName, $newVersion);

        $oldById = [];
        foreach ($oldElements as $e) {
            $oldById[$e->ID] = $e;
        }
        $newById = [];
        foreach ($newElements as $e) {
            $newById[$e->ID] = $e;
        }

        // Build the diff list in newer-version order
        foreach ($newElements as $newEl) {
            if (!isset($oldById[$newEl->ID])) {
                $tree->changes[] = $this->classifyAdded($newEl);
            } else {
                $tree->changes[] = $this->classifyPair($oldById[$newEl->ID], $newEl, $depth);
            }
        }

        // Pin removed elements at their old position
        foreach ($oldElements as $i => $oldEl) {
            if (!isset($newById[$oldEl->ID])) {
                $insertAt = $this->findRemovedInsertPosition($oldElements, $newById, $i);
                array_splice($tree->changes, $insertAt, 0, [$this->classifyRemoved($oldEl)]);
            }
        }

        $tree->reorder = $this->detectReorder($oldElements, $newElements, $oldById, $newById);

        return $tree;
    }

    private function detectReorder(array $oldElements, array $newElements, array $oldById, array $newById): ?ElementDiff
    {
        $commonOldIds = [];
        foreach ($oldElements as $e) {
            if (isset($newById[$e->ID])) {
                $commonOldIds[] = $e->ID;
            }
        }
        $commonNewIds = [];
        foreach ($newElements as $e) {
            if (isset($oldById[$e->ID])) {
                $commonNewIds[] = $e->ID;
            }
        }
        if ($commonOldIds === $commonNewIds) {
            return null;
        }
        $marker = new ElementDiff();
        $marker->status = 'reordered';
        return $marker;
    }

    private function classifyAdded(ElementBase $newEl): ElementDiff
    {
        $diff = new ElementDiff();
        $diff->status = 'added';
        $diff->elementId = $newEl->ID;
        $diff->elementClass = $newEl->ClassName;
        $diff->elementSummary = (string) $newEl->getCMSSummary();
        $diff->newSort = $newEl->Sort;
        return $diff;
    }

    private function classifyRemoved(ElementBase $oldEl): ElementDiff
    {
        $diff = new ElementDiff();
        $diff->status = 'removed';
        $diff->elementId = $oldEl->ID;
        $diff->elementClass = $oldEl->ClassName;
        $diff->elementSummary = (string) $oldEl->getCMSSummary();
        $diff->oldSort = $oldEl->Sort;
        return $diff;
    }

    private function classifyPair(ElementBase $oldEl, ElementBase $newEl, int $depth = 0): ElementDiff
    {
        $diff = new ElementDiff();
        $diff->elementId = $newEl->ID;
        $diff->elementClass = $newEl->ClassName;
        $diff->elementSummary = (string) $newEl->getCMSSummary();
        $diff->oldSort = $oldEl->Sort;
        $diff->newSort = $newEl->Sort;

        $fieldChanges = $this->fieldDiffer->diff($oldEl, $newEl);
        $diff->fieldChanges = $fieldChanges;

        // Recurse into nested children if depth allows
        if ($this->maxDepth === null || $depth < $this->maxDepth) {
            $childTree = $this->compareLevel(
                $newEl,
                'Elements',
                $oldEl->Version,
                $newEl->Version,
                $depth + 1,
            );
            $childChanges = array_filter(
                $childTree->changes,
                fn($c) => $c->status !== 'unchanged',
            );
            $diff->childChanges = array_values($childChanges);
        }

        if (!empty($fieldChanges) || !empty($diff->childChanges)) {
            $diff->status = 'modified';
        } else {
            $diff->status = 'unchanged';
        }
        return $diff;
    }

    private function findRemovedInsertPosition(array $oldElements, array $newById, int $oldIndex): int
    {
        // Walk backwards for the previous element that exists in new; insert after it
        for ($i = $oldIndex - 1; $i >= 0; $i--) {
            if (isset($newById[$oldElements[$i]->ID])) {
                return $i + 1;
            }
        }
        return 0;
    }
}
