<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\History\ElementsHistoryField;

class ElementsHistoryFieldTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    public function testSingleVersionRendersSummaries(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();

        $field = ElementsHistoryField::create('Elements')
            ->setHolder($page)
            ->setRelationName('Elements');

        $output = (string) $field->Field();
        $this->assertStringContainsString('Element One', $output);
        $this->assertStringContainsString('Element Two', $output);
    }
}
