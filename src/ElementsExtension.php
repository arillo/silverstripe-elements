<?php
namespace Arillo\Elements;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\{
    FieldList,
    FormAction
};

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;

use SilverStripe\Forms\GridField\{
    GridField,
    GridFieldDeleteAction,
    GridFieldConfig_RelationEditor,
    GridFieldPaginator,
    GridFieldAddNewButton,
    GridFieldDataColumns,
    GridFieldAddExistingAutocompleter
};

use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Versioned\Versioned;

// use \GridFieldAddNewMultiClass;
// use \ElementBase;

/**
 * Establishes multiple has_many elements relations, which can be set up via the config system
 * e.g:
 *   Page:
 *     extensions:
 *        - Arillo\Elements\ElementsExtension("Element")
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
 * @package Arillo\Elements
 */
class ElementsExtension extends DataExtension
{
    private static $has_many = [
        'Elements' => ElementBase::class
    ];

    /**
     * Holds parsed relations taking into consideration the inheritance.
     * @var string
     */
    protected $elementRelations;


    /**
     * Move an elements gridfield to an other tab.
     *
     * @param  FieldList $fields
     * @param  string    $relationName
     * @param  string    $newTabName
     * @param  string    $insertBefore      optional: insert before an other field
     * @return FieldList                    the altered fields
     */
    public static function move_elements_manager(
        FieldList $fields,
        string $relationName,
        string $newTabName = 'Root.Main',
        string $insertBefore = null
    ): FieldList
    {
        $itemsGf = $fields->dataFieldByName($relationName);
        $fields
            ->removeByName($relationName)
            ->addFieldToTab($newTabName, $itemsGf, $insertBefore)
        ;

        return $fields;
    }

    public static function create_default_elements(ElementBase $record): int
    {
        $count = 0;
        if (!$record || !$record->ID)
        {
            throw new SS_HTTPResponse_Exception("Bad record ID #" . (int)$data['ID'], 404);
        }

        if ($relationNames = ElementsExtension::page_element_relation_names($record))
        {
            $defaultElements = $record->getDefaultElements();
            if (count($relationNames) > 0)
            {
                foreach ($relationNames as $relationName => $elementsClasses)
                {
                    if (isset($defaultElements[$relationName]))
                    {
                        $elementClasses = $defaultElements[$relationName];
                        foreach ($elementClasses as $className)
                        {
                            $definedElements = $record->ElementsByRelation($relationName)->map('ClassName', 'ClassName');
                            if (!isset($definedElements[$className]))
                            {
                                $element = new $className;
                                $element->populate('PageID', $record->ID, $relationName);
                                $element->write();
                                $count++;
                            }
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Check if all provied classes derive from ElementBase.
     * @param  array  $relation
     * @return array
     */
    public static function validate_class_inheritance(array $relation): array
    {
        return array_filter($relation, function($className)
        {
            if (
                ClassInfo::exists($className)
                && (is_a(singleton($className), ElementBase::class))
            ) {
                return $className;
            }

            // user_error("Your element needs to extend from the ElementBase Class", E_USER_WARNING);
        });
    }

    public static function map_classnames($elementClasses)
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

    public static function page_element_relation_names($page)
    {
        $relations = $page->uninherited('element_relations');
        if (!$relations) $relations = [];

        // inherit relations from another PageType
        if ($inherit_relations_from = $page->uninherited('element_relations_inherit_from'))
        {
            if ($inherit_relations = Config::inst()->get($inherit_relations_from, 'element_relations', Config::UNINHERITED))
            {
                $relations = array_merge_recursive($relations, $inherit_relations);
            }
        }
        return $relations;
    }

    public function defaultsCreated()
    {
        $defaultElements = $this->getDefaultElements();
        $relationNames = ElementsExtension::page_element_relation_names($this->owner);
        if (count($relationNames) > 0)
        {
            foreach ($relationNames as $relationName => $elementsClasses)
            {
                if (isset($defaultElements[$relationName]))
                {
                    $elementClasses = $defaultElements[$relationName];
                    $definedElements = $this->owner->ElementsByRelation($relationName)->map('ClassName', 'ClassName');
                    foreach ($elementClasses as $className)
                    {
                        if (!isset($definedElements[$className]))
                        {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->exists()) return;

        $relations = ElementsExtension::page_element_relation_names($this->owner);

        if ($relations)
        {
            $this->elementRelations = array_keys($relations);
            foreach ($relations as $key => $relation)
            {
                $this->gridFieldForElementRelation($fields, $key, self::validate_class_inheritance($relation));
            }
        }
    }

    public function getDefaultElements()
    {
        $relations = $this->owner->uninherited('element_defaults');
        if (!$relations) $relations = [];

        // inherit relations from another PageType
        if ($inherit_relations_from = $this->owner->uninherited('element_relations_inherit_from'))
        {
            if ($inherit_relations = Config::inst()->get($inherit_relations_from, 'element_defaults', Config::UNINHERITED))
            {
                $relations = array_merge_recursive($relations, $inherit_relations);
            }
        }
        return $relations;
    }

    /**
     * Remove all related elements
     */
    public function onAfterDelete()
    {
        $staged = Versioned::get_by_stage($this->owner->ClassName, 'Stage')
            ->byID($this->owner->ID)
        ;

        $live = Versioned::get_by_stage($this->owner->ClassName, 'Live')
            ->byID($this->owner->ID)
        ;

        if (!$staged && !$live)
        {
            foreach($this->owner->Elements() as $element)
            {
                $element->deleteFromStage('Live');
                $element->deleteFromStage('Stage');
                $element->delete();
            }
        }
        parent::onAfterDelete();
    }

    /**
     * Publish all related elements.
     */
    public function onAfterPublish()
    {
        $this->publishElements($this->owner->Elements());
    }

    private function publishElements($elements)
    {
        if ($elements->Count() > 0)
        {
            foreach($elements as $subElement)
            {
                // \SilverStripe\Dev\Debug::dump($elements->Count());
                // die;
                // $subElement->write();
                // $subElement->publish('Stage', 'Live');
                $subElement->copyVersionToStage('Stage', 'Live');
                if ($subElement->getSchema()->hasManyComponent(ElementBase::class, 'Elements'))
                {
                    $this->publishElements($subElement->Elements());
                }
            }
        }
    }

    /**
     * Getter for items by relation name
     *
     * @param  string $relationName
     * @return DataList
     */
    public function ElementsByRelation(string $relationName)
    {
        $filter = [ 'RelationName' => $relationName ];
        if (!ClassInfo::exists('Fluent')
            && !is_a(Controller::curr(), 'LeftAndMain')
        ) {
            $filter['Visible'] = true;
        }

        return $this->owner
            ->Elements()
            ->filter($filter)
        ;
    }

    /**
     * Adds a GridField for a elements relation
     *
     * @param  FieldList $fields
     * @param  string    $relationName
     * @return DataObject
     */
    public function gridFieldForElementRelation(
        FieldList $fields,
        $relationName,
        $relation
    ) {
        // sort relations
        asort($relation);

        $config = GridFieldConfig_RelationEditor::create()
            ->removeComponentsByType(GridFieldDeleteAction::class)
            ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
            ->addComponent(new GridFieldOrderableRows('Sort'))
            ->addComponent(new GridFieldDeleteAction())
        ;

        if ($this->owner->canEdit() && $this->owner->getDefaultElements())
        {
            if (!$this->defaultsCreated()) {
                $config->addComponent(new GridFieldDefaultElementsButton());
            }
        }

        if (count($relation) > 1)
        {
            $config
                ->removeComponentsByType(GridFieldAddNewButton::class)
                ->addComponent($multiClass = new GridFieldAddNewMultiClass())
            ;

            $multiClass->setClasses(ElementsExtension::map_classnames($relation));
        }

        $config
            ->getComponentByType(GridFieldPaginator::class)
            ->setItemsPerPage(150)
        ;

        $columns = [
            'StatusFlags' => 'Status',
            'Type'=> 'Type',
            'Title' => 'Title'
        ];

        if (ClassInfo::exists('Fluent'))
        {
            $columns['Languages'] = 'Lang';
        }

        if (count($relation) == 1
            && $summaryFields = singleton($relation[0])->summaryFields()
        ) {
            $columns = array_merge($columns, $summaryFields);
        }

        $config
            ->getComponentByType(GridFieldDataColumns::class)
            ->setDisplayFields($columns)
        ;

        $tabName = "Root.{$relationName}";

        // if only one relation is set, add gridfield to main tab
        if (count($this->elementRelations) == 1) $tabName = "Root.Main";

        $label = _t("Element_Relations.{$relationName}", $relationName);
        $fields->addFieldToTab($tabName,
            $gridField = GridField::create(
                $relationName,
                $label,
                $this->owner->ElementsByRelation($relationName),
                $config
            )
        );

        $gridField->addExtraClass('elements-gridfield');

        if (count($relation) == 1) $gridField->setModelClass($relation[0]);

        if (count($this->elementRelations) > 1)
        {
            $fields
                ->findOrMakeTab($tabName)
                ->setTitle($label)
            ;
        }

        return $this->owner;
    }

    public function updateStatusFlags(&$flags)
    {
        if (ElementBase::has_modified_element($this->owner->Elements()))
        {
            $flags['modified'] = [
                'text' => _t('SiteTree.MODIFIEDONDRAFTSHORT', 'Modified'),
                'title' => _t('SiteTree.MODIFIEDONDRAFTHELP', 'Page has unpublished changes'),
            ];
        }
    }
}