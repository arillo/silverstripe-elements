<?php
namespace Arillo\Elements;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Establishes multiple has_many elements relations, which can be set up via the config system
 * e.g:
 *   Page:
 *     extensions:
 *        - Arillo\Elements\ElementsExtension
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
 * @package Arillo\Elements
 *
 */
class ElementsExtension extends DataExtension
{
    private static $has_many = [
        'Elements' => ElementBase::class,
    ];

    private static $owns = [
        'Elements',
    ];

    private static $use_custom_tab = false;

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
    ): FieldList {
        $itemsGf = $fields->dataFieldByName($relationName);
        $fields
            ->removeByName($relationName)
            ->addFieldToTab($newTabName, $itemsGf, $insertBefore)
        ;

        return $fields;
    }

    /**
     * Create_default elements , if setup via config @see self::getDefaultElements().
     * @param  SiteTree $record
     * @return int
     */
    public static function create_default_elements(SiteTree $record): int
    {
        $count = 0;
        if (!$record || !$record->ID) {
            throw new SS_HTTPResponse_Exception("Bad record ID #" . (int) $data['ID'], 404);
        }

        if ($relationNames = ElementsExtension::page_element_relation_names($record)) {
            $defaultElements = $record->getDefaultElements();
            if (count($relationNames) > 0) {
                foreach ($relationNames as $relationName => $elementsClasses) {
                    if (isset($defaultElements[$relationName])) {
                        $elementClasses = $defaultElements[$relationName];
                        foreach ($elementClasses as $className) {
                            $definedElements = $record->ElementsByRelation($relationName)->map('ClassName', 'ClassName');
                            if (!isset($definedElements[$className])) {
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
        return array_filter($relation, function ($className) {
            if (
                ClassInfo::exists($className)
                && (is_a(singleton($className), ElementBase::class))
            ) {
                return $className;
            }
            user_error('Your element needs to extend from ' . ElementBase::class, E_USER_WARNING);
        });
    }

    public static function map_classnames(array $elementClasses): array
    {
        $result = [];
        foreach ($elementClasses as $elementClass) {
            $result[$elementClass] = singleton($elementClass)->getType();
        }
        return $result;
    }

    public static function page_element_relation_names(DataObject $record): array
    {
        $relations = $record->uninherited('element_relations');
        if (!$relations) {
            $relations = [];
        }

        // inherit relations from another record type
        if ($inherit_relations_from = $record->uninherited('element_relations_inherit_from')) {
            if ($inherit_relations = Config::inst()->get($inherit_relations_from, 'element_relations', Config::UNINHERITED)) {
                $relations = array_merge_recursive($relations, $inherit_relations);
            }
        }
        return $relations;
    }

    /**
     * @return bool
     */
    public function defaultsCreated()
    {
        $defaultElements = $this->getDefaultElements();
        $relationNames = ElementsExtension::page_element_relation_names($this->owner);
        if (count($relationNames) > 0) {
            foreach ($relationNames as $relationName => $elementsClasses) {
                if (isset($defaultElements[$relationName])) {
                    $elementClasses = $defaultElements[$relationName];
                    $definedElements = $this
                        ->owner
                        ->ElementsByRelation($relationName)
                        ->map('ClassName', 'ClassName')
                    ;

                    foreach ($elementClasses as $className) {
                        if (!isset($definedElements[$className])) {
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
        if (!$this->owner->exists()) {
            return;
        }

        $relations = ElementsExtension::page_element_relation_names($this->owner);

        if ($relations) {
            $this->elementRelations = array_keys($relations);
            foreach ($relations as $key => $relation) {
                $this->gridFieldForElementRelation(
                    $fields,
                    $key,
                    self::validate_class_inheritance($relation)
                );
            }
        }
    }

    /**
     * Elements to generate withi create default elements action.
     * Can be configured like this:
     *   YourElement:
     *     element_relations:
     *       Elements:
     *         - YourChildElement
     *
     *      element_defaults:
     *        - YourChildElement
     *
     * @return array
     */
    public function getDefaultElements()
    {
        $relations = $this->owner->uninherited('element_defaults');
        if (!$relations) {
            $relations = [];
        }

        // inherit relations from another PageType
        if ($inherit_relations_from = $this->owner->uninherited('element_relations_inherit_from')) {
            if ($inherit_relations = Config::inst()->get($inherit_relations_from, 'element_defaults', Config::UNINHERITED)) {
                $relations = array_merge_recursive($relations, $inherit_relations);
            }
        }
        return $relations;
    }

    /**
     * Publish all related elements.
     */
    public function onAfterVersionedPublish()
    {
        $this->publishElements($this->owner->Elements());
    }

    private function publishElements($elements)
    {
        if ($elements->Count() > 0) {
            foreach ($elements as $subElement) {
                $subElement->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
                if ($subElement->getSchema()->hasManyComponent(ElementBase::class, 'Elements')) {
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
        $filter = ['RelationName' => $relationName];
        if (
            !$this->owner->hasExtension(ElementBase::FLUENT_CLASS)
            && !is_a(Controller::curr(), LeftAndMain::class)
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
            ->removeComponentsByType(GridFieldFilterHeader::class)
            ->addComponent(new GridFieldOrderableRows('Sort'))
            ->addComponent(new GridFieldDeleteAction())
        ;

        // attach default elements action
        if (
            $this->owner->canEdit()
            && !$this->defaultsCreated()
            && $this->owner->getDefaultElements()
            && isset($this->owner->getDefaultElements()[$relationName])
        ) {
            $config->addComponent(new GridFieldDefaultElementsButton());
        }

        if (count($relation) > 1) {
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
            'CMSTypeInfo' => _t(__CLASS__ . '.CMSTypeInfo', 'Type'),
            'CMSSummary' => _t(__CLASS__ . '.CMSSummary', 'Summary'),
        ];

        if ($this->owner->hasExtension(ElementBase::FLUENT_CLASS)) {
            $columns['Languages'] = _t(__CLASS__ . '.Languages', 'Lang');
        } else {
            // $columns['Visible'] = _t(__CLASS__ . '.Visible', 'Available');
            $columns['VisibleForCMS'] = _t(__CLASS__ . '.Visible', 'Available');
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
        if (!$this->owner->config()->use_custom_tab && count($this->elementRelations) == 1) {
            $tabName = "Root.Main";
        }

        $label = _t("Element_Relations.{$relationName}", $relationName);

        $detailForm = $config->getComponentByType(GridFieldDetailForm::class);

        // add publish page button in case of propper perms
        $holderPage = is_a($this->owner, SiteTree::class) ? $this->owner : $this->owner->getHolderPage();
        if ($holderPage && $holderPage->canPublish()) {
            $detailForm->setItemRequestClass(VersionedElement_ItemRequest::class);
        }

        $fields->addFieldToTab(
            $tabName,
            $gridField = GridField::create(
                $relationName,
                $label,
                $this->owner->ElementsByRelation($relationName),
                $config
            )
        );

        $gridField->addExtraClass('elements-gridfield');

        if (count($relation) == 1) {
            $gridField->setModelClass($relation[0]);
        }

        if (count($relation) > 1 && $detailForm->hasMethod('setShowAdd')) {
            $detailForm->setShowAdd(false);
        }

        if (count($this->elementRelations) > 1) {
            $fields
                ->findOrMakeTab($tabName)
                ->setTitle($label)
            ;
        }
        return $this->owner;
    }

    public function updateStatusFlags(&$flags)
    {
        if (ElementBase::has_modified_element($this->owner->Elements())) {
            $flags['modified'] = [
                'text' => _t('SiteTree.MODIFIEDONDRAFTSHORT', 'Modified'),
                'title' => _t('SiteTree.MODIFIEDONDRAFTHELP', 'Page has unpublished changes'),
            ];
        }
    }
}
