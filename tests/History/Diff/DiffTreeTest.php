<?php
namespace Arillo\Elements\Tests\History\Diff;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\History\Diff\DiffTree;
use Arillo\Elements\History\Diff\ElementDiff;
use Arillo\Elements\History\Diff\FieldDiff;

class DiffTreeTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testEmptyTree(): void
    {
        $tree = new DiffTree();
        $this->assertSame([], $tree->changes);
        $this->assertNull($tree->reorder);
    }

    public function testElementDiffStores(): void
    {
        $diff = new ElementDiff();
        $diff->status = 'modified';
        $diff->elementId = 42;
        $diff->elementClass = 'Arillo\\Elements\\ElementBase';
        $diff->elementSummary = '<p>summary</p>';
        $diff->fieldChanges = [];
        $diff->childChanges = [];

        $this->assertSame('modified', $diff->status);
        $this->assertSame(42, $diff->elementId);
    }

    public function testFieldDiffStores(): void
    {
        $field = new FieldDiff();
        $field->fieldName = 'HTMLText';
        $field->fieldLabel = 'Body';
        $field->kind = 'html';
        $field->oldValue = '<p>old</p>';
        $field->newValue = '<p>new</p>';

        $this->assertSame('HTMLText', $field->fieldName);
        $this->assertSame('html', $field->kind);
    }

    public function testTreeAggregates(): void
    {
        $tree = new DiffTree();
        $diff = new ElementDiff();
        $diff->status = 'added';
        $diff->elementId = 1;
        $tree->changes[] = $diff;

        $this->assertCount(1, $tree->changes);
        $this->assertSame('added', $tree->changes[0]->status);
    }
}
