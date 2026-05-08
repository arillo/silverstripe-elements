<?php
namespace Arillo\Elements\Tests\History\Stubs;

use Arillo\ArbitrarySettings\SettingsExtension;
use Arillo\Elements\ElementBase;
use SilverStripe\Dev\TestOnly;

class SettingsElementStub extends ElementBase implements TestOnly
{
    private static $table_name = 'SettingsElementStub';
    private static $extensions = [SettingsExtension::class];
    private static $settings = [
        'bg' => ['options' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light'],
        'size' => ['options' => ['sm' => 'Small', 'lg' => 'Large'], 'default' => 'sm'],
    ];
}
