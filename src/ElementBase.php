<?php
namespace Arillo\Elements;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Element base model.
 *
 * @package Arillo\Elements
 */
class ElementBase extends DataObject implements CMSPreviewable
{
    const FLUENT_CLASS = 'TractorCow\Fluent\Extension\FluentVersionedExtension';
    const CMS_SUMMARY_TEMPLATE = 'Arillo\Elements\ElementBaseSummary';

    protected static $_cached_get_by_url = [];

    private static $table_name = 'Arillo_ElementBase';
    private static $extensions = [Versioned::class];

    private static $show_urlsegment_field = false;
    private static $versioned_gridfield_extensions = false;

    private static $icon = 'font-icon-box';

    private static $db = [
        'Title' => 'Text',
        'URLSegment' => 'Varchar(255)',
        'RelationName' => 'Varchar(255)',
        'Visible' => 'Boolean(1)',
        'Sort' => 'Int',
    ];

    private static $indexes = [
        'ElementBase_ID_RelationName' => [
            'type' => 'index',
            'columns' => ['ID', 'RelationName'],
        ],
        'ElementBase_PageID_RelationName' => [
            'type' => 'index',
            'columns' => ['PageID', 'RelationName'],
        ],
        'ElementBase_ElementID_RelationName' => [
            'type' => 'index',
            'columns' => ['ElementID', 'RelationName'],
        ],
    ];

    private static $has_one = [
        'Page' => SiteTree::class,
        'Element' => ElementBase::class,
    ];

    private static $default_sort = 'Sort ASC';

    private static $translate = [
        'Title',
    ];

    private static $searchable_fields = [
        'ID',
    ];

    private static $summary_fields = [
        'CMSTypeInfo' => 'Type',
        'CMSSummary' => 'Summary',
    ];

    private static $defaults = [
        'Visible' => true,
    ];

    /**
     * @param  $elements
     * @return boolean
     */
    public static function has_modified_element($elements)
    {
        if ($elements->Count() > 0) {
            foreach ($elements as $element) {
                if ($element->stagesDiffer(Versioned::DRAFT, Versioned::LIVE)) {
                    return true;
                }

                if ($element->getSchema()->hasManyComponent(__CLASS__, 'Elements')) {
                    ElementBase::has_modified_element($element->Elements());
                }
            }
        }
    }

    /**
     * Generate next Sort value on element creation.
     *
     * @return ElementBase
     */
    public function generateElementSortForHolder()
    {
        if (!$this->Sort) {
            $holderFilter = ['PageID' => $this->PageID];
            if ($this->ElementID) {
                $holderFilter = ['ElementID' => $this->ElementID];
            }

            $this->Sort = self::get()
                ->filter($holderFilter)
                ->max('Sort') + 1
            ;
        }
        return $this;
    }

    /**
     * @return ElementBase
     */
    public function generateUniqueURLSegment($title = null)
    {
        $filter = URLSegmentFilter::create();

        if (!$this->URLSegment) {
            $this->URLSegment = $title ?? $this->Title;
        }

        $this->URLSegment = $filter->filter($this->URLSegment);

        if (!$this->URLSegment) {
            $this->URLSegment = uniqid();
        }

        $count = 2;

        // add a -n to the URLSegment if it already existed
        while ($this->getByUrlSegment(__CLASS__, $this->URLSegment, $this->ID)) {
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment) . '-' . $count;
            $count++;
        }
        return $this;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this
            ->generateUniqueURLSegment()
            ->generateElementSortForHolder()
        ;
    }

    /**
     * Type info for GridField usage.
     *
     * @return string
     */
    public function getCMSTypeInfo()
    {
        $data = ArrayData::create([
            'Icon' => $this->config()->icon,
            'Type' => $this->getType(),
        ]);

        // Add versioned states (rendered as a circle over the icon)
        if ($this->hasExtension(Versioned::class)) {
            $data->IsVersioned = true;
            if ($this->isOnDraftOnly()) {
                $data->VersionState = 'draft';
                $data->VersionStateTitle = _t(
                    'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.ADDEDTODRAFTHELP',
                    'Item has not been published yet'
                );
            } elseif ($this->isModifiedOnDraft()) {
                $data->VersionState = 'modified';
                $data->VersionStateTitle = $data->VersionStateTitle = _t(
                    'SilverStripe\\Versioned\\VersionedGridFieldState\\VersionedGridFieldState.MODIFIEDONDRAFTHELP',
                    'Item has unpublished changes'
                );
            }
        }

        return $data->renderWith('Arillo\\Elements\\TypeInfo');
    }

    public function addCMSFieldsHeader($fields)
    {
        $relationName = Controller::curr()->request->param('FieldName');

        $description = "<div class='alert alert-dark'><i class='element-icon {$this->config()->icon}'></i> {$this->i18n_singular_name()} ({$this->ID}) &nbsp; –";

        if ($this->hasExtension(self::FLUENT_CLASS)) {
            $locale = $this->LocaleInformation(
                \TractorCow\Fluent\State\FluentState::singleton()->getLocale()
            );

            $description .= "&nbsp; <span class='element-state element-state-{$locale->URLSegment}'>{$locale->URLSegment}</span> &nbsp;";
        }

        $description .= "{$this->getStatusFlags('')} </div>";

        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create('ClassNameDescription', $description),
            TextField::create('Title', _t(__CLASS__ . '.Title', 'Title'), null, 255),
            HiddenField::create('RelationName', $relationName, $relationName),
        ]);

        if ($this->config()->show_urlsegment_field) {
            $fields->addFieldsToTab(
                'Root.Main',
                ElementURLSegmentField::create('URLSegment', _t(__CLASS__ . '.URLSegment', 'Url-Segment'))
                // TextField::create('URLSegment', _t(__CLASS__ . '.URLSegment', 'URLSegment'), null, 255)
            );
        }

        if (!$this->hasExtension(self::FLUENT_CLASS)) {
            $fields->addFieldToTab(
                'Root.Main',
                CheckboxField::create('Visible', _t(__CLASS__ . '.Visible', 'Is element visible'))
            );
        }
    }

    public function populate($type, $id, $relation)
    {
        $this->Title = $this->i18n_singular_name() . ' title';
        $this->PageID = $id;
        $this->RelationName = $relation;
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));
        $this->addCMSFieldsHeader($fields);

        if (
            !$this->isInDB()
            && $this->class === ElementBase::class
            && $elementRelation = Controller::curr()->request->param('FieldName')
        ) {
            $relationNames = ElementsExtension::page_element_relation_names($this->Page());
            if (isset($relationNames[$elementRelation])) {
                $fields->addFieldToTab(
                    'Root.Main',
                    DropdownField::create(
                        'ClassName',
                        _t(__CLASS__ . '.ClassName', 'Type'),
                        ElementsExtension::map_classnames($relationNames[$elementRelation])
                    )
                );
            }
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function getHolder()
    {
        if ($this->Element()->exists()) {
            return $this->Element();
        }

        if ($this->Page()->exists()) {
            return $this->Page();
        }

        return false;
    }

    /**
     * Recursive look up for holder page.
     */
    public function getHolderPage()
    {
        if (!$this->PageID && !$this->ElementID) {
            return null;
        }

        $holder = $this->getHolder();
        while (
            $holder
            && $holder->exists()
            && !is_a($holder, SiteTree::class)
        ) {
            $holder = $holder->getHolder();
        }

        return $holder;
    }

    public function getCMSActions()
    {
        $fields = parent::getCMSActions();

        if (
            $this->ID
            && is_a(Controller::curr(), CMSPageEditController::class)
            && ($this->stagesDiffer(Versioned::DRAFT, Versioned::LIVE)
                || self::has_modified_element($this->Elements()))
        ) {
            $fields->push(
                FormAction::create(
                    'publishPage',
                    _t(__CLASS__ . '.PublishPage', 'Publish page')
                )
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn action btn btn-primary font-icon-rocket')
            );
        }
        return $fields;
    }

    public function getType()
    {
        return $this->i18n_singular_name();
    }

    public function getStatusFlags($separator = '<br>')
    {
        $modified = false;
        $state = [];
        $published = _t(__CLASS__ . '.State_published', 'published');
        $draft = _t(__CLASS__ . '.State_draft', 'draft');
        $modifiedContent = _t(__CLASS__ . '.State_modified', 'modified');
        $notVisible = _t(__CLASS__ . '.State_hidden', 'hidden');

        $state[] = $this->isPublished()
        ? "<span class='element-state active'>{$published}</span>"
        : "<span class='element-state modified'>{$draft}</span>"
        ;

        if ($this->stagesDiffer('Stage', 'Live')) {
            $modified = true;
        }

        if (ElementBase::has_modified_element($this->owner->Elements())) {
            $modified = true;
        }

        if ($modified) {
            $state[] = "<span class='element-state modified'>{$modifiedContent}</span>";
        }

        if (!$this->hasExtension(self::FLUENT_CLASS)) {
            if (!$this->Visible) {
                $state[] = "<span class='element-state inactive'>{$notVisible}</span>";
            }

        }

        return DBField::create_field('HTMLVarchar', implode($state, $separator));
    }

    /**
     * Summary for GridField usage.
     * @return string
     */
    public function getCMSSummary()
    {
        return $this->renderWith(self::CMS_SUMMARY_TEMPLATE);
    }

    public function getLanguages()
    {
        $pills = '';
        if ($this->hasExtension(self::FLUENT_CLASS)) {
            if ($locales = \TractorCow\Fluent\Model\Locale::get()) {
                foreach ($locales as $locale) {
                    $class = $this->isAvailableInLocale($locale) ? 'active' : 'inactive';
                    $pills .= "<span class='element-state $class'>{$locale->URLSegment}</span><br>";
                }
            }
        }
        return DBField::create_field('HTMLVarchar', $pills);
    }

    public function getCMSVisible()
    {
        $pills = '';
        $class = $this->Visible ? 'active' : 'inactive';
        $icon = $this->Visible ? "&#9733;" : "&#9734;";
        $pills .= "<span class='element-state $class'>{$icon}</span><br>";
        return DBField::create_field('HTMLVarchar', $pills);
    }

    public function PreviewLink($action = null)
    {
        return Controller::join_links(
            Director::baseURL(),
            'cms-preview',
            'show',
            str_replace('\\', '-', $this->ClassName),
            $this->ID
        );
    }

    public function Link()
    {
        return $this->Page()->Link('#' . $this->URLSegment);
    }

    public function CMSEditLink()
    {
        return $this->Link();
    }

    /**
     * To determine preview mechanism (e.g. embedded / iframe)
     *
     * @return string
     */
    public function getMimeType()
    {
        return 'embedded';
    }

    /**
     * Render for template useage.
     *
     * @param int $IsPos
     * @param bool $IsFirst
     * @param bool $IsLast
     * @param bool $IsEvenOdd
     */
    public function Render(
        $IsPos = null,
        $IsFirst = null,
        $IsLast = null,
        $IsEvenOdd = null
    ) {
        $this->IsPos = $IsPos;
        $this->IsFirst = $IsFirst;
        $this->IsLast = $IsLast;
        $this->IsEvenOdd = $IsEvenOdd;
        $controller = Controller::curr();
        return $controller
            ->customise($this)
            ->renderWith($this->ClassName)
        ;
    }

    /**
     * Publish holder page, trigger publish all sub elements.
     */
    public function publishPage()
    {
        $look = true;
        $parent = $this;
        while ($look) {
            if ($parent = $parent->getHolder()) {
                if (is_a($parent, SiteTree::class)) {
                    $look = false;
                }
            } else {
                $look = false;
            }
        }

        if ($parent->doPublish()) {
            return _t(__CLASS__ . '.PageAndElementsPublished', 'Page & elements published');
        }
        return _t(__CLASS__ . '.PageAndElementsPublishError', 'There was an error publishing the page');
    }

    /**
     * @param  string $class
     * @param  string $str
     * @param  int $excludeID
     * @return bool
     */
    protected function getByUrlSegment(
        string $class,
        string $str,
        $excludeID = null
    ) {
        if (!isset(static::$_cached_get_by_url[$str])) {
            $list = $class::get()->filter('URLSegment', $str);
            if ($excludeID) {
                $list = $list->exclude('ID', $excludeID);
            }

            $obj = $list->First();
            static::$_cached_get_by_url[$str] = ($obj && $obj->exists()) ? $obj : false;
        }
        return static::$_cached_get_by_url[$str];
    }

    // Permissions
    public function canView($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }
}
