<?php
namespace Arillo\Elements\Tests\History;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Group;
use Arillo\Elements\History\FieldDiffer;
use Arillo\Elements\Tests\History\Stubs\TextElementStub;
use Arillo\Elements\Tests\History\Stubs\HtmlElementStub;
use Arillo\Elements\Tests\History\Stubs\RelationElementStub;
use Arillo\Elements\Tests\History\Stubs\SettingsElementStub;

class FieldDifferTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected static $extra_dataobjects = [
        TextElementStub::class,
        HtmlElementStub::class,
        RelationElementStub::class,
        SettingsElementStub::class,
    ];

    public function testTextFieldDiff(): void
    {
        $old = TextElementStub::create();
        $old->Title = 'old title';

        $new = TextElementStub::create();
        $new->Title = 'new title';

        $differ = new FieldDiffer();
        $diffs = $differ->diff($old, $new);

        $titleDiff = $this->findDiff($diffs, 'Title');
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

        $bodyDiff = $this->findDiff($diffs, 'Body');
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

    public function testHasOneImageKind(): void
    {
        // Skip writing real Image records — the host project's
        // AutoPublishFileExtension recurses when File::write() is called from a
        // test. Kind classification is schema-level so arbitrary IDs suffice.
        $old = RelationElementStub::create();
        $old->PrimaryImageID = 100;

        $new = RelationElementStub::create();
        $new->PrimaryImageID = 200;

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'PrimaryImage');
        $this->assertNotNull($diff);
        $this->assertSame('has_one_image', $diff->kind);
    }

    public function testHasOneFileKind(): void
    {
        // Same workaround as testHasOneImageKind.
        $old = RelationElementStub::create();
        $old->AttachedFileID = 100;

        $new = RelationElementStub::create();
        $new->AttachedFileID = 200;

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'AttachedFile');
        $this->assertNotNull($diff);
        $this->assertSame('has_one_file', $diff->kind);
    }

    public function testHasOneGenericKind(): void
    {
        $oldGroup = Group::create(['Title' => 'Group A']);
        $oldGroup->write();
        $newGroup = Group::create(['Title' => 'Group B']);
        $newGroup->write();

        $old = RelationElementStub::create();
        $old->OwnerGroupID = $oldGroup->ID;
        $old->write();
        $new = RelationElementStub::create();
        $new->OwnerGroupID = $newGroup->ID;
        $new->write();

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'OwnerGroup');
        $this->assertNotNull($diff);
        $this->assertSame('has_one', $diff->kind);
        $this->assertStringContainsString('Group A', $diff->oldValue);
        $this->assertStringContainsString('Group B', $diff->newValue);
    }

    public function testManyManyKind(): void
    {
        $g1 = Group::create(['Title' => 'G1']);
        $g1->write();
        $g2 = Group::create(['Title' => 'G2']);
        $g2->write();

        $old = RelationElementStub::create();
        $old->write();
        $old->TaggedGroups()->add($g1);

        $new = RelationElementStub::create();
        $new->write();
        $new->TaggedGroups()->add($g1);
        $new->TaggedGroups()->add($g2);

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'TaggedGroups');
        $this->assertNotNull($diff);
        $this->assertSame('many_many', $diff->kind);
    }

    public function testHasOneFkColumnsAreNotDiffedAsText(): void
    {
        $old = RelationElementStub::create();
        $old->PrimaryImageID = 100;
        $new = RelationElementStub::create();
        $new->PrimaryImageID = 200;

        $differ = new FieldDiffer();
        $diffs = $differ->diff($old, $new);

        $this->assertNull(
            $this->findDiff($diffs, 'PrimaryImageID'),
            'has_one FK column (PrimaryImageID) should not appear as a separate text diff'
        );
        $this->assertNotNull($this->findDiff($diffs, 'PrimaryImage'));
    }

    public function testArbitrarySettingsDiff(): void
    {
        if (!class_exists(\Arillo\ArbitrarySettings\SettingsExtension::class)) {
            $this->markTestSkipped('ArbitrarySettings module not installed');
        }

        $old = SettingsElementStub::create();
        $old->ArbitrarySettingsValue = json_encode(['bg' => 'light', 'size' => 'sm']);

        $new = SettingsElementStub::create();
        $new->ArbitrarySettingsValue = json_encode(['bg' => 'dark', 'size' => 'sm']);

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'settings.bg');
        $this->assertNotNull($diff);
        $this->assertSame('settings', $diff->kind);
        $this->assertSame('light', $diff->oldValue);
        $this->assertSame('dark', $diff->newValue);
        $this->assertNull(
            $this->findDiff($differ->diff($old, $new), 'settings.size'),
            'Unchanged setting key should not appear in diff'
        );
    }

    public function testArbitrarySettingsValueRawColumnNotDiffedAsText(): void
    {
        if (!class_exists(\Arillo\ArbitrarySettings\SettingsExtension::class)) {
            $this->markTestSkipped('ArbitrarySettings module not installed');
        }

        $old = SettingsElementStub::create();
        $old->ArbitrarySettingsValue = json_encode(['bg' => 'light']);

        $new = SettingsElementStub::create();
        $new->ArbitrarySettingsValue = json_encode(['bg' => 'dark']);

        $differ = new FieldDiffer();
        $diffs = $differ->diff($old, $new);

        $this->assertNull(
            $this->findDiff($diffs, 'ArbitrarySettingsValue'),
            'Raw ArbitrarySettingsValue blob should not appear; only per-key diffs'
        );
    }

    public function testConfigurableExclusions(): void
    {
        TextElementStub::config()->merge('history_excluded_fields', ['Title']);

        try {
            $old = TextElementStub::create();
            $old->Title = 'A';
            $new = TextElementStub::create();
            $new->Title = 'B';

            $differ = new FieldDiffer();
            $diff = $this->findDiff($differ->diff($old, $new), 'Title');
            $this->assertNull($diff, 'Title should be excluded by config override');
        } finally {
            TextElementStub::config()->remove('history_excluded_fields');
        }
    }

    public function testFieldLabelsResolveFromI18n(): void
    {
        $old = TextElementStub::create();
        $old->Title = 'A';
        $new = TextElementStub::create();
        $new->Title = 'B';

        $differ = new FieldDiffer();
        $diff = $this->findDiff($differ->diff($old, $new), 'Title');
        $this->assertNotNull($diff);
        $this->assertNotEmpty($diff->fieldLabel, 'A label should resolve');
    }

    private function findDiff(array $diffs, string $name)
    {
        foreach ($diffs as $d) {
            if ($d->fieldName === $name) {
                return $d;
            }
        }
        return null;
    }
}
