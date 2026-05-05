<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\ElementsTreeComparator;
use Arillo\Elements\History\FieldDiffer;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;

class ElementsTreeComparatorTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    public function testUnchangedProducesAllUnchanged(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $v = $page->Version;

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($v, $v);

        $this->assertNotEmpty($tree->changes);
        foreach ($tree->changes as $diff) {
            $this->assertSame('unchanged', $diff->status);
        }
    }

    public function testModifiedFieldProducesModifiedDiff(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(ElementBase::class, 'el1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $el1->Title = 'Changed';
        $el1->write();
        $page->Title = 'Test Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;
        $this->assertGreaterThan($oldV, $newV);

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($oldV, $newV);

        $found = null;
        foreach ($tree->changes as $diff) {
            if ($diff->elementId === $el1->ID) {
                $found = $diff;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame('modified', $found->status);
        $this->assertNotEmpty($found->fieldChanges);
    }

    public function testAddedElementClassifiedAdded(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $newEl = ElementBase::create([
            'Title' => 'New Element',
            'PageID' => $page->ID,
            'RelationName' => 'Elements',
            'Sort' => 99,
        ]);
        $newEl->write();
        $page->Title = 'Test Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;
        $this->assertGreaterThan($oldV, $newV);

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($oldV, $newV);

        $added = array_filter($tree->changes, fn($d) => $d->status === 'added');
        $this->assertCount(1, $added);
    }

    public function testNestedChildChangeFlagsParentModified(): void
    {
        $loader = new \SilverStripe\Dev\YamlFixture(__DIR__ . '/fixtures/elements_nested.yml');
        $loader->writeInto($this->fixtureFactory);
        $page = $this->fixtureFactory->get(\Page::class, 'page2');
        $parent = $this->fixtureFactory->get(ElementBase::class, 'parent1');
        $child = $this->fixtureFactory->get(ElementBase::class, 'child1');

        $page->Title = 'Nested Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $child->Title = 'Child Updated';
        $child->write();
        $page->Title = 'Nested Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;
        $this->assertGreaterThan($oldV, $newV);

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($oldV, $newV);

        $parentDiff = null;
        foreach ($tree->changes as $d) {
            if ($d->elementId === $parent->ID) {
                $parentDiff = $d;
                break;
            }
        }
        $this->assertNotNull($parentDiff);
        $this->assertSame('modified', $parentDiff->status);
        $this->assertNotEmpty($parentDiff->childChanges);
        $this->assertSame('modified', $parentDiff->childChanges[0]->status);
    }

    public function testMaxDepthCollapsesGrandchildren(): void
    {
        $loader = new \SilverStripe\Dev\YamlFixture(__DIR__ . '/fixtures/elements_nested.yml');
        $loader->writeInto($this->fixtureFactory);
        $page = $this->fixtureFactory->get(\Page::class, 'page2');
        $page->Title = 'Nested v1';
        $page->write();
        $page->publishRecursive();
        $v = $page->Version;

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
            0,
        );
        $tree = $comparator->compare($v, $v);

        foreach ($tree->changes as $d) {
            $this->assertEmpty($d->childChanges, 'Children should be excluded at depth 0');
        }
    }

    public function testReorderProducesReorderMarker(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(ElementBase::class, 'el1');
        $el2 = $this->objFromFixture(ElementBase::class, 'el2');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $el1->Sort = 2;
        $el1->write();
        $el2->Sort = 1;
        $el2->write();
        $page->Title = 'Test Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;
        $this->assertGreaterThan($oldV, $newV);

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($oldV, $newV);

        $this->assertNotNull($tree->reorder, 'Reorder should be detected');
        $this->assertSame('reordered', $tree->reorder->status);
    }

    public function testRemovedElementClassifiedRemoved(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(ElementBase::class, 'el1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $el1->doArchive();
        $page->Title = 'Test Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;
        $this->assertGreaterThan($oldV, $newV);

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            new FieldDiffer(),
        );
        $tree = $comparator->compare($oldV, $newV);

        $removed = array_filter($tree->changes, fn($d) => $d->status === 'removed');
        $this->assertCount(1, $removed);
    }
}
