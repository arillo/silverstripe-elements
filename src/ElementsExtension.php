<?php
namespace Arillo\Elements;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\ORM\DataExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Control\HTTPResponse_Exception;
use TractorCow\Fluent\Forms\DeleteAllLocalesAction;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

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

    private static $owns = ['Elements'];

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
            ->addFieldToTab($newTabName, $itemsGf, $insertBefore);

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
            throw new HTTPResponse_Exception('Bad record ID', 404);
        }

        if (
            $relationNames = ElementsExtension::page_element_relation_names(
                $record
            )
        ) {
            $defaultElements = $record->getDefaultElements();
            if (count($relationNames) > 0) {
                foreach ($relationNames as $relationName => $elementsClasses) {
                    if (isset($defaultElements[$relationName])) {
                        $elementClasses = $defaultElements[$relationName];
                        foreach ($elementClasses as $className) {
                            $definedElements = $record
                                ->ElementsByRelation($relationName)
                                ->map('ClassName', 'ClassName');
                            if (!isset($definedElements[$className])) {
                                $element = new $className();
                                $element->populate(
                                    'PageID',
                                    $record->ID,
                                    $relationName
                                );
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
                ClassInfo::exists($className) &&
                is_a(singleton($className), ElementBase::class)
            ) {
                return $className;
            }
            user_error(
                $className . ' does not extend from' . ElementBase::class,
                E_USER_WARNING
            );
        });
    }

    public static function map_classnames(array $elementClasses): array
    {
        $result = [];
        foreach ($elementClasses as $elementClass) {
            $result[$elementClass] = singleton($elementClass)->getType();
        }
        asort($result);
        return $result;
    }

    public static function page_element_relation_names(
        DataObject $record
    ): array {
        return self::gather_element_relations_inherit_config(
            $record,
            'element_relations'
        );
    }

    /**
     * Collect element relations config.
     * $configName can be 'element_relations' or 'element_defaults'
     *
     * @param DataObject $record
     * @param string $configName
     * @return array
     */
    public static function gather_element_relations_inherit_config(
        DataObject $record,
        string $configName
    ): array {
        $relations = $record->uninherited($configName);
        if (!$relations) {
            $relations = [];
        }

        if (
            $inheritRelationsFrom = $record->uninherited(
                'element_relations_inherit_from'
            )
        ) {
            $allInheritances = [];
            if (is_string($inheritRelationsFrom)) {
                $allInheritances = [$inheritRelationsFrom];
            } elseif (is_array($inheritRelationsFrom)) {
                $allInheritances = array_merge(
                    $allInheritances,
                    $inheritRelationsFrom
                );
            }

            if (count($allInheritances)) {
                foreach ($allInheritances as $inheritance) {
                    if (
                        $inheritRelations = Config::inst()->get(
                            $inheritance,
                            $configName,
                            Config::UNINHERITED
                        )
                    ) {
                        $relations = array_merge_recursive(
                            $relations,
                            $inheritRelations
                        );
                    }
                }
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
        $relationNames = ElementsExtension::page_element_relation_names(
            $this->owner
        );
        if (count($relationNames) > 0) {
            foreach ($relationNames as $relationName => $elementsClasses) {
                if (isset($defaultElements[$relationName])) {
                    $elementClasses = $defaultElements[$relationName];
                    $definedElements = $this->owner
                        ->ElementsByRelation($relationName)
                        ->map('ClassName', 'ClassName');

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

        $relations = ElementsExtension::page_element_relation_names(
            $this->owner
        );

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
        return self::gather_element_relations_inherit_config(
            $this->owner,
            'element_defaults'
        );
    }

    /**
     * Publish all related elements.
     */
    // public function onAfterVersionedPublish()
    // {
    //     // $this->publishElements($this->owner->Elements());
    // }

    // private function publishElements($elements)
    // {
    //     if ($elements->Count() > 0) {
    //         foreach ($elements as $subElement) {
    //             $subElement->copyVersionToStage(
    //                 Versioned::DRAFT,
    //                 Versioned::LIVE
    //             );
    //             if (
    //                 $subElement
    //                     ->getSchema()
    //                     ->hasManyComponent(ElementBase::class, 'Elements')
    //             ) {
    //                 $this->publishElements($subElement->Elements());
    //             }
    //         }
    //     }
    // }

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
            !$this->owner->hasExtension(ElementBase::FLUENT_CLASS) &&
            !is_a(Controller::curr(), LeftAndMain::class)
        ) {
            $filter['Visible'] = true;
        }

        return $this->owner->Elements()->filter($filter);
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
            ->addComponent(new GridFieldDeleteAction());

        // attach default elements action
        if (
            $this->owner->canEdit() &&
            !$this->defaultsCreated() &&
            $this->owner->getDefaultElements() &&
            isset($this->owner->getDefaultElements()[$relationName])
        ) {
            $config->addComponent(new GridFieldDefaultElementsButton());
        }

        if (count($relation) > 1) {
            $config
                ->removeComponentsByType(GridFieldAddNewButton::class)
                ->addComponent($multiClass = new GridFieldAddNewMultiClass());

            $multiClass->setClasses(
                ElementsExtension::map_classnames($relation)
            );
        }

        $config
            ->getComponentByType(GridFieldPaginator::class)
            ->setItemsPerPage(150);

        $columns = [
            'CMSTypeInfo' => _t(__CLASS__ . '.CMSTypeInfo', 'Type'),
            'CMSSummary' => _t(__CLASS__ . '.CMSSummary', 'Summary'),
        ];

        if ($this->owner->hasExtension(ElementBase::FLUENT_CLASS)) {
            $columns['Languages'] = _t(__CLASS__ . '.Languages', ' ');
        } else {
            $columns['CMSVisible'] = _t(__CLASS__ . '.CMSVisible', ' ');
        }

        if ($this->owner->hasExtension(ElementBase::FLUENT_CLASS)) {
            $config->addComponent(new DeleteAllLocalesAction());
        }

        if (
            count($relation) == 1 &&
            ($summaryFields = singleton($relation[0])->summaryFields())
        ) {
            $columns = array_merge($summaryFields, $columns);
        }

        $config
            ->getComponentByType(GridFieldDataColumns::class)
            ->setDisplayFields($columns);

        $tabName = "Root.{$relationName}";

        // if only one relation is set, add gridfield to main tab
        if (
            !$this->owner->config()->use_custom_tab &&
            count($this->elementRelations) == 1
        ) {
            $tabName = 'Root.Main';
        }

        $label = _t("Element_Relations.{$relationName}", $relationName);
        if (
            $this->owner->hasMethod('fieldLabels') &&
            ($labels = $this->owner->fieldLabels(true)) &&
            isset($labels[$relationName])
        ) {
            $label = $labels[$relationName];
        }

        $detailForm = $config->getComponentByType(GridFieldDetailForm::class);

        // add publish page button in case of propper perms
        $holderPage = is_a($this->owner, SiteTree::class)
            ? $this->owner
            : $this->owner->getHolderPage();
        if ($holderPage && $holderPage->canPublish()) {
            $detailForm->setItemRequestClass(
                VersionedElement_ItemRequest::class
            );
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
            $fields->findOrMakeTab($tabName)->setTitle($label);
        }
        return $this->owner;
    }
    
        public function updateStagesDiffer(&$stagesDiffer)
    {
        if ($stagesDiffer) {
            return $stagesDiffer;
        }

        if (($elements = $this->owner->Elements()) && $elements->exists()) {
            return $stagesDiffer = array_reduce(
                $elements->toArray(),
                function ($acc, $el) {
                    if ($acc) {
                        return $acc;
                    }
                    return $el->stagesDiffer();
                },
                false
            );
        }

        return $stagesDiffer;
    }

    public function updateIsOnDraft(&$isOnDraft)
    {
        if ($isOnDraft) {
            return $isOnDraft;
        }

        if (($elements = $this->owner->Elements()) && $elements->exists()) {
            return $isOnDraft = array_reduce(
                $elements->toArray(),
                function ($acc, $el) {
                    if ($acc) {
                        return $acc;
                    }
                    return $el->isOnDraft();
                },
                false
            );
        }

        return $isOnDraft;
    }

    public function updateStatusFlags(&$flags)
    {
        if (ElementBase::has_modified_element($this->owner->Elements())) {
            $flags['modified'] = [
                'text' => _t('SiteTree.MODIFIEDONDRAFTSHORT', 'Modified'),
                'title' => _t(
                    'SiteTree.MODIFIEDONDRAFTHELP',
                    'Page has unpublished changes'
                ),
            ];
        }
    }
}
