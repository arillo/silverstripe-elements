<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\VersionedAdmin\Forms\DataObjectVersionFormFactory;
use Arillo\Elements\History\ElementsHistoryField;
use Arillo\Elements\History\PageHistoryFormFactoryExtension;

class PageHistoryFormFactoryExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Configure 'Elements' as an element relation on the Page class so the
        // extension treats it as a swappable GridField.
        \Page::config()->merge('element_relations', [
            'Elements' => [\Arillo\Elements\ElementBase::class],
        ]);
    }

    protected function tearDown(): void
    {
        \Page::config()->remove('element_relations');
        parent::tearDown();
    }

    public function testGridFieldSwappedForPageWithElementsExtension(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');

        $tab = new \SilverStripe\Forms\Tab('Main');
        $tab->push(GridField::create('Elements', 'Elements', new ArrayList()));
        $fields = FieldList::create($tab);

        $ext = new PageHistoryFormFactoryExtension();
        $ext->setOwner(new DataObjectVersionFormFactory());
        $ext->updateFormFields($fields, null, 'Form', ['Record' => $page]);

        $field = $fields->dataFieldByName('Elements');
        $this->assertInstanceOf(ElementsHistoryField::class, $field);
        $this->assertNotInstanceOf(GridField::class, $field);
    }

    public function testNoOpWhenHistoryDisabled(): void
    {
        \Arillo\Elements\ElementBase::config()->set('history_enabled', false);
        try {
            $page = $this->objFromFixture(\Page::class, 'page1');
            $tab = new \SilverStripe\Forms\Tab('Main');
            $tab->push(GridField::create('Elements', 'Elements', new ArrayList()));
            $fields = FieldList::create($tab);

            $ext = new PageHistoryFormFactoryExtension();
            $ext->setOwner(new DataObjectVersionFormFactory());
            $ext->updateFormFields($fields, null, 'Form', ['Record' => $page]);

            $this->assertInstanceOf(GridField::class, $fields->dataFieldByName('Elements'));
        } finally {
            \Arillo\Elements\ElementBase::config()->set('history_enabled', true);
        }
    }
}
