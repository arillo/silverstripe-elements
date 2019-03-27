<?php
namespace Arillo\Elements;

use Colymba\BulkUpload\BulkUploader as CBulkUploader;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Core\Config\Config;

/**
 * Bulk uploader for elements.
 *
 * @package Arillo\Elements
 */
class BulkUploader extends CBulkUploader
{
    const ELEMENT_RELATIONNAME = 'elementRelationName';
    const ELEMENT_CLASSNAME = 'recordClassName';
    const FILE_RELATIONNAME = 'fileRelationName';
    const FOLDER_NAME = 'folderName';

    private static $default_upload_folder = 'Uploads';

    /**
     * @return array
     */
    public static function default_config()
    {
        return [
            self::FILE_RELATIONNAME => null,
            self::ELEMENT_CLASSNAME => null,
            self::ELEMENT_RELATIONNAME => null,
            self::FOLDER_NAME => Config::inst()->get(__CLASS__, 'default_upload_folder'),
        ];
    }

    /**
     * Apply a bulk uploader to a (elements) gridfield.
     * !!CAUTION only wokrs for gridfields managing on model.
     *
     * @param  GridField $gridField
     * @param  array     $config
     * @return GridField
     */
    public static function apply(
        GridField $gridField,
        array $config = []
    ) {
        $config = array_merge(self::default_config(), $config);
        $bulkUploader = new BulkUploader();

        foreach ($config as $key => $value)
        {
            $bulkUploader->setConfig($key, $value);
        }

        $gridField
            ->getConfig()
            ->addComponent(
                $bulkUploader
                    ->setUfSetup('setFolderName', $config['folderName'])
                    ->setAutoPublishDataObject(true)
            )
        ;

        return $gridField;
    }

    /**
     * Override setConfig to allow arbitary config settings
     * @param string $reference
     * @param mixed $value
     */
    public function setConfig($reference, $value)
    {
        $this->config[$reference] = $value;
        return $this;
    }
}
