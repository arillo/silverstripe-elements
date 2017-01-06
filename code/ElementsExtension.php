<?php
namespace webtoolkit\elements;

use \DataExtension;
use \FieldList;
use \Config;
use \ClassInfo;
use \GridField;
use \GridFieldConfig_RelationEditor;

/**
 * Establishes multiple has_many elements relations, which can be set up via the config system
 * e.g:
 *   Page:
 *     extensions:
 *        - webtoolkit\elements\ElementsExtension("Element")
 *     element_relations:
 *        Elements:
 *          - Element
 *          - DownloadElement
 *        Downloads:
 *          - DownloadElement
 *
 * Adds a getter function to access the elements by relation name
 *
 *   $pageInst->ElementsByRelation('Downloads');
 *
 * @package webtoolkit\elements
 */
class ElementsExtension extends DataExtension
{
    /**
     * Holds element base class. Will be set in constructor.
     * @var string
     */
    protected $_elementBaseClass;

    /**
     * Move an elements gridfield to an other tab.
     *
     * @param  FieldList $fields
     * @param  string    $relationName
     * @param  string    $newTabName
     * @param  string    $insertBefore      optional: insert before an other field
     * @return FieldList                    the altered fields
     */
    public static function move_elements_manager(FieldList $fields, $relationName, $newTabName = 'Root.Main', $insertBefore = null)
    {
        $itemsGf = $fields->dataFieldByName($relationName);
        $fields->removeByName($relationName);
        $fields->addFieldToTab($newTabName, $itemsGf, $insertBefore);
        return $fields;
    }

    /**
     * Extracts element classes for a relation from the config.
     * Filters out non existent class names
     *
     * @param  string $relationName
     * @return array
     */
    public static function relation_classes($owner, $relationName)
    {
        if ($elementRelations = $owner->config()->element_relations)
        {
            if (isset($elementRelations[$relationName]))
            {
            	$baseClass = $owner->getElementBaseClass();
                return array_filter($elementRelations[$relationName], function($className) use ($baseClass)
                {
                    if (ClassInfo::exists($className) && (is_a(singleton($className), $baseClass)))
                    {
                        return $className;
                    }
                });
            }
        }
        return [];
    }

    /**
     * Creates a element classes map for use in a Dropdown.
     *
     * Expects a flat array of class names e.g.:
     *   ['BaseElement', 'DownloadElement']
     *
     * Returns a map in folowing format:
     *   [
     *       'ClassName' => '<ClassName> or <class.singular_name>'
     *   ]
     *  E.g:
     *   [
     *     'BaseElement' => 'Title',
     *     'DownloadElement' => 'Download'
     *   ]
     *
     * @param  array $elementClasses
     * @return array
     */
    public static function relation_classes_map($owner, $relationName)
    {
    	$elementClasses = ElementsExtension::relation_classes($owner, $relationName);
        $result = [];
        foreach ($elementClasses as $elementClass)
        {
            $result[$elementClass] = $elementClass;
            if ($label = singleton($elementClass)->stat('singular_name'))
            {
                $result[$elementClass] = $label;
            }
        }
        return $result;
    }


    /**
     * Returns an array of all element relation names.
     *
     * @return mixed array | bool
     */
    public static function relation_names($owner)
    {
        if ($elementRelations = $owner->config()->element_relations)
        {
            return array_keys($elementRelations);
        }
        return false;
    }

    /**
     * Sets up the extension with a given element base class
     * @param string $elementBaseClass
     */
    public function __construct($elementBaseClass = 'ElementBase')
    {
        parent::__construct();
        $this->_elementBaseClass = $elementBaseClass;
    }

    /**
     * Adds a has_many relation called "Elements" to the extended object.
     * @param  string $class
     * @param  class $extension
     * @return array
     */
    public function extraStatics($class = null, $extension = null)
    {
        return [
            'has_many' => [ 'Elements' => $this->_elementBaseClass ]
        ];
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->exists()) return;

        if ($relationNames = self::relation_names($this->owner))
        {
            foreach ($relationNames as $key => $relationName)
            {
                $this->gridFieldForElementRelation($fields, $relationName);
            }
        }
    }

    /**
     * Publish all related elements.
     */
    public function onAfterPublish()
    {
        foreach($this->owner->Elements() as $element)
        {
            $element->write();
            $element->publish('Stage', 'Live');
        }
    }

    public function getElementBaseClass()
    {
    	return $this->_elementBaseClass;
    }

    /**
     * Getter for items by relation name
     *
     * @param  string $relationName
     * @return DataList
     */
    public function ElementsByRelation($relationName)
    {
        return $this->owner
            ->Elements()
            ->filter('RelationName', $relationName);
    }


    /**
     * Adds a GridField for a elements relation
     *
     * @param  FieldList $fields
     * @param  string    $relationName
     * @return DataObject
     */
    public function gridFieldForElementRelation(FieldList $fields, $relationName)
    {
        if ($elementClasses = self::relation_classes($this->owner, $relationName))
        {
            // sort relations
            asort($elementClasses);

            $config = GridFieldConfig_RelationEditor::create()
                ->removeComponentsByType('GridFieldDeleteAction')
                ->removeComponentsByType('GridFieldAddExistingAutocompleter');

            // CHECK: needs check if composer dependency set?
            if (ClassInfo::exists('GridFieldOrderableRows'))
            {
                $config->addComponent(new \GridFieldOrderableRows('Sort'));
            }

            if (count($elementClasses) > 1)
            {
                $config
                    ->removeComponentsByType('GridFieldAddNewButton')
                    ->addComponent($multiClass = new \GridFieldAddNewMultiClass());

                $multiClass->setClasses(self::relation_classes_map($this->owner, $relationName));
            }

            $config
                ->getComponentByType('GridFieldPaginator')
                ->setItemsPerPage(150);

            $columns = [
                // 'Icon' => 'Icon',
                'singular_name'=> 'Type',
                'Title' => 'Title'
            ];

            if (ClassInfo::exists('Fluent'))
            {
            	$columns['Languages'] = 'Lang';
        	}

            if (count($elementClasses) == 1
                && $summaryFields = singleton($elementClasses[0])->summaryFields()
            ) {
                $columns = array_merge($columns, $summaryFields);
            }

            $config
                ->getComponentByType('GridFieldDataColumns')
                ->setDisplayFields($columns);

            $tabName = "Root.{$relationName}";
            $label = _t("Element_Relations.{$relationName}", $relationName);
            $fields->addFieldToTab($tabName,
                $gridField = GridField::create(
                    $relationName,
                    $label,
                    $this->owner->ElementsByRelation($relationName),
                    $config
                )
            );

            if (count($elementClasses) == 1)
            {
                $gridField->setModelClass($elementClasses[0]);
            }

            $fields
                ->findOrMakeTab($tabName)
                ->setTitle($label);
        }
        return $this->owner;
    }
}
