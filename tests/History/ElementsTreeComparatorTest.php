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
