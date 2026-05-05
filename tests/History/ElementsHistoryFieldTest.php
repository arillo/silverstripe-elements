<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\History\ElementsHistoryField;

class ElementsHistoryFieldTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/elements_basic.yml';
    protected $usesDatabase = true;

    public function testCompareModeRendersStatusBadgesAndDiff(): void
    {
        $page = $this->objFromFixture(\Page::class, 'page1');
        $el1 = $this->objFromFixture(\Arillo\Elements\ElementBase::class, 'el1');
        $page->Title = 'Test Page v1';
        $page->write();
        $page->publishRecursive();
        $oldV = $page->Version;

        sleep(1);

        $el1->Title = 'Element One Modified';
        $el1->write();
        $page->Title = 'Test Page v2';
        $page->write();
        $page->publishRecursive();
        $newV = $page->Version;

        $field = ElementsHistoryField::create('Elements')
            ->setHolder($page)
            ->setRelationName('Elements')
            ->setVersions($oldV, $newV);

        $compared = $field->performDiffTransformation(\SilverStripe\Forms\FormTransformation::create());
        $output = (string) $compared->Field();

        $this->assertStringContainsString('element-diff--modified', $output);
        $this->assertStringContainsString('Element One Modified', $output);
    }

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
