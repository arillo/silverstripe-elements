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
 *   $pageInst->getItemsByRelation('Downloads');
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

        if ($realtionNames = $this->elementRelationNames())
        {
            foreach ($realtionNames as $key => $relationName)
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

    /**
     * Getter for items by relation name
     *
     * @param  string $relationName
     * @return DataList
     */
    public function getItemsByRelation($relationName)
    {
        return $this->owner
            ->Elements()
            ->filter('RelationName', $relationName)
        ;
    }

    /**
     * Extracts element classes for a relation from the config.
     * Filters out non existent class names
     *
     * @param  string $relationName
     * @return array
     */
    public function elementClassesForRelation($relationName)
    {
        if ($elementRelations = $this->owner->config()->element_relations)
        {
            if (isset($elementRelations[$relationName]))
            {
                return array_filter($elementRelations[$relationName], function($className)
                {
                    if (ClassInfo::exists($className) && (is_a(singleton($className), $this->_elementBaseClass)))
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
    public function elementClassesForDropdown(array $elementClasses)
    {
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
    public function elementRelationNames()
    {
        if ($elementRelations = $this->owner->config()->element_relations)
        {
            return array_keys($elementRelations);
        }
        return false;
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
        if ($elementClasses = $this->elementClassesForRelation($relationName))
        {
            // sort relations
            asort($elementClasses);

            $config = GridFieldConfig_RelationEditor::create()
                ->removeComponentsByType('GridFieldDeleteAction')
                ->removeComponentsByType('GridFieldAddExistingAutocompleter')
            ;

            if (ClassInfo::exists('GridFieldOrderableRows'))
            {
                $config->addComponent(new \GridFieldOrderableRows('Sort'));
            }

            if (count($elementClasses) > 1)
            {
                $config
                    ->removeComponentsByType('GridFieldAddNewButton')
                    ->addComponent($multiClass = new \GridFieldAddNewMultiClass())
                ;

                $multiClass->setClasses($this->elementClassesForDropdown($elementClasses));
            }

            $config
                ->getComponentByType('GridFieldPaginator')
                ->setItemsPerPage(50)
            ;

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
                ->setDisplayFields($columns)
            ;

            $tabName = "Root.{$relationName}";
            $label = _t("Element_Relations.{$relationName}", $relationName);
            $fields->addFieldToTab($tabName,
                $gridField = GridField::create(
                    $relationName,
                    $label,
                    $this->owner->getItemsByRelation($relationName),
                    $config
                )
            );

            if (count($elementClasses) == 1)
            {
                $gridField->setModelClass($elementClasses[0]);
            }

            $fields
                ->findOrMakeTab($tabName)
                ->setTitle($label)
            ;
        }
        return $this->owner;
    }
}
