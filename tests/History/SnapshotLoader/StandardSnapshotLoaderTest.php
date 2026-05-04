<?php
namespace Arillo\Elements\Tests\History\SnapshotLoader;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\CMS\Model\SiteTree;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;

class StandardSnapshotLoaderTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** @var SiteTree */
    private SiteTree $page;

    /** @var ElementBase */
    private ElementBase $el1;

    /** @var ElementBase */
    private ElementBase $el2;

    protected function setUp(): void
    {
        parent::setUp();

        $page = SiteTree::create([
            'Title' => 'Test Page',
            'URLSegment' => 'test-page',
        ]);
        $page->write();
        $this->page = $page;

        $el1 = ElementBase::create([
            'Title' => 'Element One',
            'URLSegment' => 'element-one',
            'RelationName' => 'Elements',
            'Sort' => 1,
            'Visible' => 1,
            'PageID' => $page->ID,
        ]);
        $el1->write();
        $this->el1 = $el1;

        $el2 = ElementBase::create([
            'Title' => 'Element Two',
            'URLSegment' => 'element-two',
            'RelationName' => 'Elements',
            'Sort' => 2,
            'Visible' => 1,
            'PageID' => $page->ID,
        ]);
        $el2->write();
        $this->el2 = $el2;
    }

    public function testLoadsElementsAtCurrentVersion(): void
    {
        $this->page->publishRecursive();
        $this->page->flushCache();

        $loader = new StandardSnapshotLoader();
        $version = $this->page->Version;
        $elements = $loader->loadAtVersion($this->page, 'Elements', $version);

        $this->assertCount(2, $elements);
        $titles = array_map(fn($e) => $e->Title, $elements);
        $this->assertContains('Element One', $titles);
        $this->assertContains('Element Two', $titles);
    }

    public function testLoadsElementSnapshotAtOlderVersion(): void
    {
        $this->page->publishRecursive();
        $oldVersion = $this->page->Version;

        // Sleep 1s to ensure LastEdited timestamps differ between publish rounds
        sleep(1);

        $this->el1->Title = 'Element One Updated';
        $this->el1->write();
        $this->page->publishRecursive();
        $this->page->flushCache();

        $loader = new StandardSnapshotLoader();
        $oldElements = $loader->loadAtVersion($this->page, 'Elements', $oldVersion);
        $found = null;
        foreach ($oldElements as $e) {
            if ($e->ID === $this->el1->ID) {
                $found = $e;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame('Element One', $found->Title, 'Old snapshot should still have original title');
    }

    public function testReturnsEmptyArrayForUnknownVersion(): void
    {
        $loader = new StandardSnapshotLoader();
        $this->assertSame([], $loader->loadAtVersion($this->page, 'Elements', 99999));
    }
}
