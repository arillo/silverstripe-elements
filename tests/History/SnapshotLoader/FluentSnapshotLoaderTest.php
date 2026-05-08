<?php
namespace Arillo\Elements\Tests\History\SnapshotLoader;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\SnapshotLoader\FluentSnapshotLoader;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentSnapshotLoaderTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        if (!class_exists(FluentState::class)) {
            $this->markTestSkipped('Fluent not installed');
        }
        parent::setUp();

        // Fluent's augmentWrite() only writes to localised tables when a Locale
        // record exists for the active locale. Create one for 'de_CH' so that
        // publishRecursive() populates Arillo_ElementBase_Localised_Versions.
        $locale = Locale::create();
        $locale->Locale = 'de_CH';
        $locale->Title = 'German (Switzerland)';
        $locale->URLSegment = 'de';
        $locale->IsGlobalDefault = 1;
        $locale->write();
        Locale::clearCached();
    }

    public function testLoadsLocaleScopedSnapshot(): void
    {
        FluentState::singleton()->withState(function ($state) {
            $state->setLocale('de_CH');

            $page = $this->objFromFixture(\Page::class, 'page1');
            $page->publishRecursive();

            $loader = new FluentSnapshotLoader();
            $elements = $loader->loadAtVersion($page, 'Elements', $page->Version);

            $this->assertNotEmpty($elements);
        });
    }
}
