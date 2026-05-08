<?php
namespace Arillo\Elements\Tests\History\Stubs;

use Arillo\Elements\ElementBase;
use SilverStripe\Dev\TestOnly;

class HtmlElementStub extends ElementBase implements TestOnly
{
    private static $table_name = 'HtmlElementStub';
    private static $db = ['Body' => 'HTMLText'];
}
