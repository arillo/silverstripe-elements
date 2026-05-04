<?php
namespace Arillo\Elements\Tests\History\SnapshotLoader;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;

class StandardSnapshotLoaderTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    public function testLoadsElementsAtCurrentVersion(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $page->publishRecursive();
        $page->flushCache();

        $loader = new StandardSnapshotLoader();
        $version = $page->Version;
        $elements = $loader->loadAtVersion($page, 'Elements', $version);

        $this->assertCount(2, $elements);
        $titles = array_map(fn($e) => $e->Title, $elements);
        $this->assertContains('Element One', $titles);
        $this->assertContains('Element Two', $titles);
    }

    public function testLoadsElementSnapshotAtOlderVersion(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(ElementBase::class, 'el1');
        $page->publishRecursive();
        $oldVersion = $page->Version;

        // LastEdited resolves to seconds; we need a real second of separation
        // for the cutoff query to distinguish snapshots. v1 limitation.
        sleep(1);

        $el1->Title = 'Element One Updated';
        $el1->write();
        $page->publishRecursive();
        $page->flushCache();

        $loader = new StandardSnapshotLoader();
        $oldElements = $loader->loadAtVersion($page, 'Elements', $oldVersion);
        $found = null;
        foreach ($oldElements as $e) {
            if ($e->ID === $el1->ID) {
                $found = $e;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame('Element One', $found->Title, 'Old snapshot should still have original title');
    }

    public function testReturnsEmptyArrayForUnknownVersion(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $loader = new StandardSnapshotLoader();
        $this->assertSame([], $loader->loadAtVersion($page, 'Elements', 99999));
    }
}
