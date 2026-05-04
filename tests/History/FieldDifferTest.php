<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Dev\SapphireTest;
use Arillo\Elements\History\FieldDiffer;
use Arillo\Elements\Tests\History\Stubs\TextElementStub;
use Arillo\Elements\Tests\History\Stubs\HtmlElementStub;

class FieldDifferTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testTextFieldDiff(): void
    {
        $old = TextElementStub::create();
        $old->Title = 'old title';

        $new = TextElementStub::create();
        $new->Title = 'new title';

        $differ = new FieldDiffer();
        $diffs = $differ->diff($old, $new);

        $titleDiff = null;
        foreach ($diffs as $d) {
            if ($d->fieldName === 'Title') { $titleDiff = $d; break; }
        }
        $this->assertNotNull($titleDiff);
        $this->assertSame('text', $titleDiff->kind);
        $this->assertSame('old title', $titleDiff->oldValue);
        $this->assertSame('new title', $titleDiff->newValue);
    }

    public function testHtmlFieldDiff(): void
    {
        $old = HtmlElementStub::create();
        $old->Body = '<p>old html</p>';

        $new = HtmlElementStub::create();
        $new->Body = '<p>new html</p>';

        $differ = new FieldDiffer();
        $diffs = $differ->diff($old, $new);

        $bodyDiff = null;
        foreach ($diffs as $d) {
            if ($d->fieldName === 'Body') { $bodyDiff = $d; break; }
        }
        $this->assertNotNull($bodyDiff);
        $this->assertSame('html', $bodyDiff->kind);
    }

    public function testNoChangesProducesEmptyArray(): void
    {
        $old = TextElementStub::create();
        $old->Title = 'same';

        $new = TextElementStub::create();
        $new->Title = 'same';

        $differ = new FieldDiffer();
        $this->assertSame([], $differ->diff($old, $new));
    }
}
