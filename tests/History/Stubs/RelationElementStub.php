<?php
namespace Arillo\Elements\Tests\History\Stubs;

use Arillo\Elements\ElementBase;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Group;

class RelationElementStub extends ElementBase implements TestOnly
{
    private static $table_name = 'RelationElementStub';
    private static $has_one = [
        'PrimaryImage' => Image::class,
        'AttachedFile' => File::class,
        'OwnerGroup' => Group::class,
    ];
    private static $many_many = [
        'TaggedGroups' => Group::class,
    ];
}
