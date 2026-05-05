<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;
use Arillo\Elements\ElementBase;
use Arillo\Elements\History\ElementHistoryExtension;

class ElementHistoryExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Wire the extension at runtime since _config/history.yml lands in T18.
        ElementBase::add_extension(ElementHistoryExtension::class);

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
