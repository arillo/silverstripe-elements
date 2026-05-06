<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;
use Arillo\Elements\ElementBase;

class ElementHistoryExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        // The host project has a Deepl translator extension that reads Fluent
        // locale from FluentState; provide a Locale record so the lookup
        // succeeds in test setup.
        if (class_exists(\TractorCow\Fluent\Model\Locale::class)) {
            $locale = \TractorCow\Fluent\Model\Locale::create();
            $locale->Locale = 'de_CH';
            $locale->Title = 'German (Switzerland)';
            $locale->URLSegment = 'de';
            $locale->IsGlobalDefault = 1;
            $locale->write();
            \TractorCow\Fluent\Model\Locale::clearCached();
        }
    }

    public function testHistoryTabAddedToCMSFields(): void
    {
        $this->withFluentLocale(function () {
            $el = $this->objFromFixture(ElementBase::class, 'el1');
            $fields = $el->getCMSFields();

            $historyField = $fields->fieldByName('Root.History.ElementHistory');
            $this->assertNotNull($historyField);
            $this->assertInstanceOf(HistoryViewerField::class, $historyField);
        });
    }

    public function testPublishingElementBumpsHolderPage(): void
    {
        $this->withFluentLocale(function () {
            $page = $this->objFromFixture(\Page::class, 'page1');
            $el1 = $this->objFromFixture(ElementBase::class, 'el1');

            $page->Title = 'Test Page v1';
            $page->write();
            $page->publishRecursive();
            $oldVersion = $page->Version;

            sleep(1);

            // Publish ONLY the element. The page should still get a new
            // version courtesy of ElementHistoryExtension::onAfterPublish.
            $el1->Title = 'Element One Updated';
            $el1->write();
            $el1->publishRecursive();

            // Re-fetch the page to see its updated version.
            $page = \Page::get_by_id(\Page::class, $page->ID);
            $this->assertGreaterThan(
                $oldVersion,
                $page->Version,
                'Holder page should get a new version when an element is published',
            );
        });
    }

    public function testHistoryTabSkippedWhenDisabled(): void
    {
        ElementBase::config()->set('history_per_element_tab', false);
        try {
            $this->withFluentLocale(function () {
                $el = $this->objFromFixture(ElementBase::class, 'el1');
                $fields = $el->getCMSFields();
                $this->assertNull($fields->fieldByName('Root.History'));
            });
        } finally {
            ElementBase::config()->set('history_per_element_tab', true);
        }
    }

    private function withFluentLocale(callable $fn): void
    {
        if (!class_exists(\TractorCow\Fluent\State\FluentState::class)) {
            $fn();
            return;
        }
        \TractorCow\Fluent\State\FluentState::singleton()->withState(
            function ($state) use ($fn) {
                $state->setLocale('de_CH');
                $fn();
            }
        );
    }
}
