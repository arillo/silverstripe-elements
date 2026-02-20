<?php
namespace Arillo\Elements;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;

/**
 * Adds onBulkUpload hook to an element.
 * Sets RelationName & model class by BulkUploader config.
 *
 * @package Arillo\Elements
 */
class BulkUploadExtension extends Extension
{
    public function onBulkUpload(GridField $gridField)
    {
        $uploader = $gridField
            ->getConfig()
            ->getComponentByType(BulkUploader::class)
        ;

        $this->owner->RelationName = $uploader->getConfig(BulkUploader::ELEMENT_RELATIONNAME);
    }
}
