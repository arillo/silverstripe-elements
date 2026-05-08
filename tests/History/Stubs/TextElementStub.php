<?php
namespace Arillo\Elements\Tests\History\Stubs;

use Arillo\Elements\ElementBase;
use SilverStripe\Dev\TestOnly;

class TextElementStub extends ElementBase implements TestOnly
{
    private static $table_name = 'TextElementStub';
    private static $db = ['Title' => 'Varchar'];
}
