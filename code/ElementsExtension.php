<?php
namespace arillo\elements;

use \DataExtension;
use \FieldList;
use \Config;
use \Controller;
use \ClassInfo;
use \GridField;
use \GridFieldConfig_RelationEditor;
use \FormAction;
use \GridFieldOrderableRows;
use \GridFieldAddNewMultiClass;

/**
 * Establishes multiple has_many elements relations, which can be set up via the config system
 * e.g:
 *   Page:
 *     extensions:
 *        - arillo\elements\ElementsExtension("Element")
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
 * @package arillo\elements
 */
class ElementsExtension extends DataExtension
{

    /**
     * Holds element base class. Will be set in constructor.
     * @var string
     */
    protected $_elementBaseClass;


    /**
     * Holds parsed relations taking into consideration the inheritance.
     * @var string
     */
    protected $_elementRelations;

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

    public function updateCMSActions(FieldList $fields)
    {
        if ($this->owner->canEdit() && $this->owner->getDefaultElements())
        {
            $fields->addFieldToTab('ActionMenus.MoreOptions', FormAction::create('doCreateDefaults', _t('ElementsExtension.CreateDefaults','Create default elements')));
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->exists()) return;

        $relations = $this->getElementRelationNames();

        if ($relations)
        {
            $this->_elementRelations = array_keys($relations);
            foreach ($relations as $key => $relation)
            {
                $this->gridFieldForElementRelation($fields, $key, $this->checkClassNames($relation));
            }
        }
    }

    public function getElementRelationNames()
    {
        $relations = $this->owner->uninherited('element_relations');
        if (!$relations) $relations = [];

        // inherit relations from another PageType
        if ($inherit_relations_from = $this->owner->uninherited('element_relations_inherit_from'))
        {
            if ($inherit_relations = Config::inst()->get($inherit_relations_from, 'element_relations', Config::UNINHERITED))
            {
                $relations = array_merge_recursive($relations, $inherit_relations);
            }
        }
        return $relations;
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

    public function checkClassNames($relation)
    {
        $baseClass = $this->owner->getElementBaseClass();
        return array_filter($relation, function($className) use ($baseClass)
        {
            if (ClassInfo::exists($className) && (is_a(singleton($className), $baseClass)))
            {
                return $className;
            }
        });
    }

    public function getClassNames($elementClasses)
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
     * Remove all related elements
     */
    public function onAfterDelete()
    {
        foreach($this->owner->Elements() as $element)
        {
            $element->deleteFromStage('Live');
            $element->deleteFromStage('Stage');
            $element->delete();
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

    private function publishElements($elements){
        if($elements->Count()>0){
            foreach($elements as $subelement)
            {
                $subelement->write();
                $subelement->publish('Stage', 'Live');
                if($subelement->hasManyComponent('Elements')){
                    $this->publishElements($subelement->Elements());
                }
            }
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
    public function gridFieldForElementRelation(FieldList $fields, $relationName, $relation)
    {
        // sort relations
        asort($relation);

        $config = GridFieldConfig_RelationEditor::create()
            ->removeComponentsByType('GridFieldDeleteAction')
            ->removeComponentsByType('GridFieldAddExistingAutocompleter')
            ->addComponent(new GridFieldOrderableRows('Sort'))
        ;

        if (count($relation) > 1)
        {
            $config
                ->removeComponentsByType('GridFieldAddNewButton')
                ->addComponent($multiClass = new GridFieldAddNewMultiClass())
            ;

            $multiClass->setClasses($this->getClassNames($relation));
        }

        $config
            ->getComponentByType('GridFieldPaginator')
            ->setItemsPerPage(150)
        ;

        $columns = [
            // 'Icon' => 'Icon',
            'i18n_singular_name'=> 'Type',
            'Title' => 'Title'
        ];

        if (ClassInfo::exists('Fluent'))
        {
            $columns['Languages'] = 'Lang';
        } else {
            $columns['Languages'] = '';
        }

        if (count($relation) == 1
            && $summaryFields = singleton($relation[0])->summaryFields()
        ) {
            $columns = array_merge($columns, $summaryFields);
        }

        $config
            ->getComponentByType('GridFieldDataColumns')
            ->setDisplayFields($columns)
        ;

        $tabName = "Root.{$relationName}";

        // if only one relation is set, add gridfield to main tab
        if (count($this->_elementRelations) == 1) $tabName = "Root.Main";

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

        $fields
            ->findOrMakeTab($tabName)
            ->setTitle($label)
        ;

        return $this->owner;
    }
}
