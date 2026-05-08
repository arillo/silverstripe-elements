<?php
namespace Arillo\Elements\Tests\History\SnapshotLoader;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\SnapshotLoader\StandardSnapshotLoader;
use Arillo\Elements\Tests\History\Stubs\HtmlElementStub;

class StandardSnapshotLoaderTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/elements_basic.yml';
    protected static $extra_dataobjects = [
        HtmlElementStub::class,
    ];
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

    public function testSnapshotIncludesSubclassFields(): void
    {
        // A subclass of ElementBase that adds its own DB field (Body) lives
        // in a separate subclass table joined via the schema. The snapshot
        // loader must hydrate elements through the ORM so subclass-only
        // fields are populated, not dropped to a base-table-only result.
        $page = $this->objFromFixture(\Page::class, 'page1');

        $sub = HtmlElementStub::create();
        $sub->Title = 'Sub element';
        $sub->Body = '<p>subclass-only payload</p>';
        $sub->RelationName = 'Elements';
        $sub->Sort = 99;
        $sub->Visible = 1;
        $sub->PageID = $page->ID;
        $sub->write();

        // Real second of separation so the page version's LastEdited is
        // unambiguously after sub's write — the archive subselect uses
        // LastEdited <= cutoff with second-resolution timestamps.
        sleep(1);

        $page->publishRecursive();
        // Refetch the page so $page->Version reflects the just-published
        // version. The in-memory copy can lag behind the DB when fixture
        // loading or earlier $owns cascades have already bumped the version.
        $page = \Page::get()->byID($page->ID);

        $loader = new StandardSnapshotLoader();
        $elements = $loader->loadAtVersion($page, 'Elements', $page->Version);

        $loaded = null;
        foreach ($elements as $e) {
            if ($e->ID === $sub->ID) {
                $loaded = $e;
                break;
            }
        }
        $this->assertNotNull($loaded, 'Subclass element should appear in snapshot');
        $this->assertInstanceOf(HtmlElementStub::class, $loaded);
        $this->assertSame('<p>subclass-only payload</p>', $loaded->Body, 'Subclass-only DB field must be hydrated');
    }
}
