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

    public function testExceptionInOneElementIsolated(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(ElementBase::class, 'el1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();

        $throwingDiffer = new class extends FieldDiffer {
            public function diff(\SilverStripe\ORM\DataObject $old, \SilverStripe\ORM\DataObject $new): array
            {
                if ($new->Title === 'Element One') {
                    throw new \RuntimeException('boom');
                }
                return parent::diff($old, $new);
            }
        };

        $comparator = new ElementsTreeComparator(
            $page,
            'Elements',
            new StandardSnapshotLoader(),
            $throwingDiffer,
        );
        $tree = $comparator->compare($page->Version, $page->Version);

        $errors = array_filter($tree->changes, fn($d) => $d->status === 'error');
        $this->assertCount(1, $errors);
        $err = array_values($errors)[0];
        $this->assertSame($el1->ID, $err->elementId);
        $this->assertSame('boom', $err->errorMessage);

        $okStatuses = array_map(fn($d) => $d->status, $tree->changes);
        $this->assertContains('unchanged', $okStatuses, 'Other elements should be unaffected');
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

    public function testDeepDraftChangeWithoutIntermediateEditIsDetected(): void
    {
        // Reproduces a real-world bug: when comparing two draft versions of a
        // page where only a deeply-nested grandchild element was edited (and
        // the intermediate parent elements were not directly touched), the
        // change must still be visible. The original implementation derived
        // the snapshot cutoff from each intermediate element's own version
        // timestamp, which is earlier than the grandchild edit when the
        // intermediate elements weren't bumped — so the change was silently
        // dropped from the diff.
        $page = $this->objFromFixture(\Page::class, 'page1');

        $parent = ElementBase::create([
            'Title' => 'Deep Parent',
            'PageID' => $page->ID,
            'RelationName' => 'Elements',
            'Sort' => 10,
            'Visible' => 1,
        ]);
        $parent->write();

        $child = ElementBase::create([
            'Title' => 'Deep Child',
            'ElementID' => $parent->ID,
            'RelationName' => 'Elements',
            'Sort' => 1,
            'Visible' => 1,
        ]);
        $child->write();

        $grandchild = ElementBase::create([
            'Title' => 'Grandchild original',
            'ElementID' => $child->ID,
            'RelationName' => 'Elements',
            'Sort' => 1,
            'Visible' => 1,
        ]);
        $grandchild->write();

        // Cutoffs depend on LastEdited (second-resolution), so each step needs
        // a real second of separation. v1 limitation.
        sleep(1);

        $page->Title = 'Draft v_old';
        $page->write();
        $oldV = $page->Version;

        sleep(1);

        // Edit ONLY the grandchild. Do not touch parent or child — the bug
        // depends on intermediate element versions staying constant.
        $grandchild->Title = 'Grandchild updated';
        $grandchild->write();

        sleep(1);

        $page->Title = 'Draft v_new';
        $page->write();
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
        $this->assertNotNull($parentDiff, 'Parent element should appear in diff tree');
        $this->assertSame('modified', $parentDiff->status, 'Parent must be flagged modified due to deep grandchild change');
        $this->assertNotEmpty($parentDiff->childChanges, 'Parent must have child changes from deep edit');

        $childDiff = $parentDiff->childChanges[0];
        $this->assertSame('modified', $childDiff->status, 'Child must be flagged modified due to grandchild change');
        $this->assertNotEmpty($childDiff->childChanges, 'Child must have grandchild changes');

        $grandchildDiff = $childDiff->childChanges[0];
        $this->assertSame('modified', $grandchildDiff->status, 'Grandchild must be flagged modified');
        $this->assertNotEmpty($grandchildDiff->fieldChanges, 'Grandchild must have field changes');
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
