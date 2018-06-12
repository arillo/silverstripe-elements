<?php
namespace Arillo\Elements;

use SilverStripe\ORM\{
    DataObject,
    DataList,
    CMSPreviewable
};

use SilverStripe\Forms\{
    CheckboxField,
    FieldList,
    FormAction,
    LiteralField,
    HiddenField,
    TabSet,
    TextField
};

use SilverStripe\Versioned\Versioned;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Security\Permission;
use SilverStripe\Control\{
    Controller,
    Director
};
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Parsers\URLSegmentFilter;

class ElementBase extends DataObject implements CMSPreviewable
{
    const FLUENT_CLASS = 'TractorCow\Fluent\Extension\FluentExtension';

    protected static $_cached_get_by_url = [];

    private static
        $show_urlsegment_field = false,

        $table_name = 'Arillo_ElementBase',

        $extensions = [ Versioned::class ],

        $db = [
            'Title' => 'Text',
            'URLSegment' => 'Varchar(255)',
            'RelationName' => 'Varchar(255)',
            'Visible' => 'Boolean(1)',
            'Sort' => 'Int'
        ],

        $indexes = [
            'ElementBase_ID_RelationName' => [
                'type' => 'index',
                'columns' => [ 'ID', 'RelationName' ],
            ]
        ],

        $has_one = [
            'Page' => SiteTree::class,
            'Element' => ElementBase::class
        ],

        $default_sort = 'Sort ASC',

        $translate = [
            'Title'
        ],

        $searchable_fields = [
            'ClassName',
            'URLSegment'
        ],

        $summary_fields = [
            'Title' => 'Title',
            'StatusFlags' => 'Status',
        ],

        $defaults = [
            'Visible' => true
        ]
    ;

    protected $wasNew = false;

    public static function has_modified_element($elements)
    {
        if ($elements->Count() > 0)
        {
            foreach($elements as $element)
            {
                if ($element->stagesDiffer(Versioned::DRAFT, Versioned::LIVE)) return true;
                if ($element->getSchema()->hasManyComponent(__CLASS__, 'Elements'))
                {
                    ElementBase::has_modified_element($element->Elements());
                }
            }
        }
    }

    public function generateElementSortForHolder()
    {
        if (!$this->Sort)
        {
            $holderFilter = ['PageID' => $this->PageID];
            if ($this->ElementID) $holderFilter = ['ElementID' => $this->ElementID];

            $this->Sort = self::get()
                ->filter($holderFilter)
                ->max('Sort') + 1
            ;
        }
        return $this;
    }

    public function generateUniqueURLSegment()
    {
        $filter = URLSegmentFilter::create();

        if (!$this->URLSegment) $this->URLSegment = $this->Title;

        $this->URLSegment = $filter->filter($this->URLSegment);

        if (!$this->URLSegment) $this->URLSegment = uniqid();

        $count = 2;

        // add a -n to the URLSegment if it already existed
        while ($this->getByUrlSegment(__CLASS__, $this->URLSegment, $this->ID))
        {
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment) . '-' . $count;
            $count++;
        }
        return $this;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->wasNew = !$this->isInDB();
        $this
            ->generateUniqueURLSegment()
            ->generateElementSortForHolder()
        ;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->populateLocales();

    }

    public function populateLocales()
    {
        if (
            $this->wasNew
            && $this->hasExtension(self::FLUENT_CLASS)
        ) {
            $locales = \TractorCow\Fluent\Model\Locale::get();
            foreach ($locales as $locale)
            {
                $this->FilteredLocales()->add($locale);
            }
        }
        return $this;
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        // This is done in order to unpublish sub-elements when unpublishing an element,
        // since unpublishing an element also calls the onAfterDelete callback
        if (Versioned::get_reading_mode() !== Versioned::DRAFT)
        {
            foreach($this->owner->Elements() as $element)
            {
                $element->deleteFromStage(Versioned::LIVE);
            }
            return;
        }

        // Delete own element from live if called from GridFieldDeleteAction
        $this->deleteFromStage(Versioned::LIVE);
        foreach($this->owner->Elements() as $element)
        {
            $element->deleteFromStage(Versioned::LIVE);
            $element->deleteFromStage(Versioned::DRAFT);
            $element->delete();
        }
    }

    public function addCMSFieldsHeader($fields)
    {
        $relationName = Controller::curr()->request->param('FieldName');

        $description = "<div class='cms-page-info'><b>{$this->i18n_singular_name()}&nbsp;[{$this->ID}]</b>&nbsp;";

        if ($this->hasExtension(self::FLUENT_CLASS))
        {
            $locale = $this->LocaleInformation(
                \TractorCow\Fluent\State\FluentState::singleton()->getLocale()
            );

            $description .= " <span class='element-state element-state-{$locale->URLSegment}'>{$locale->URLSegment}</span> &nbsp;";
        }

        $description .= "{$this->getStatusFlags('')} </div>";

        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create('ClassNameDescription', $description),
            TextField::create('Title', _t('ElementBase.Title', 'Title'), null, 255),
            HiddenField::create('RelationName', $relationName, $relationName)
        ]);

        if ($this->config()->show_urlsegment_field)
        {
            $fields->addFieldsToTab(
                'Root.Main',
                TextField::create('URLSegment', _t(__CLASS__ . '.URLSegment', 'URLSegment'), null, 255)
            );
        }

        if (!$this->hasExtension(self::FLUENT_CLASS))
        {
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
            if (isset($relationNames[$elementRelation]))
            {
                $fields->addFieldToTab(
                    'Root.Main',
                    DropdownField::create(
                        'ClassName',
                        _t('ElementBase.Typ', 'Type'),
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
        if ($this->Element()->exists()) return $this->Element();
        if ($this->Page()->exists()) return $this->Page();
        return false;
    }

    public function getCMSActions()
    {
        $fields = parent::getCMSActions();

        if (
            $this->ID
            && is_a(Controller::curr(), CMSPageEditController::class)
            && ($this->stagesDiffer(Versioned::DRAFT,Versioned::LIVE)
            || self::has_modified_element($this->Elements()))
        ) {
            $fields->push(
                FormAction::create(
                    'publishPage',
                    _t(__CLASS__ . 'PublishPage', 'Publish page')
                )
                ->setUseButtonTag(true)
                ->addExtraClass('btn action btn btn-primary font-icon-rocket')
            );
        }
        return $fields;
    }

    public function publishPage()
    {
        $look = true;
        $parent = $this;
        while($look)
        {
            if ($parent = $parent->getHolder())
            {
                if (is_a($parent, SiteTree::class))
                {
                    $look = false;
                }
            } else {
                $look = false;
            }
        }

        if ($parent->doPublish())
        {
            return _t(__CLASS__ . '.PageAndElementsPublished', "Page & elements published");
        }
        return _t(__CLASS__ . '.PageAndElementsPublishError', "There was an error publishing the page");
    }

    public function getType()
    {
        return _t(__CLASS__ . '.SINGULARNAME', $this->singular_name());
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

        if ($this->stagesDiffer('Stage', 'Live')) $modified = true;

        if (ElementBase::has_modified_element($this->owner->Elements())) $modified = true;

        if ($modified) $state[] = "<span class='element-state modified'>{$modifiedContent}</span>";

        if (!$this->hasExtension(self::FLUENT_CLASS))
        {
            if (!$this->Visible) $state[] = "<span class='element-state inactive'>{$notVisible}</span>";
        }

        return DBField::create_field('HTMLVarchar', implode($state, $separator));
    }

    public function getLanguages()
    {
        $pills = '';
        if ($this->hasExtension(self::FLUENT_CLASS))
        {
            if ($locales = \TractorCow\Fluent\Model\Locale::get())
            {
                foreach ($locales as $locale)
                {
                    $class = $this->isAvailableInLocale($locale) ? 'active' : 'inactive';
                    $pills .= "<span class='element-state $class'>{$locale->URLSegment}</span><br>";
                }
            }
        }
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
        return $this->Page()->Link($this->URLSegment);
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
     * @param  string $class
     * @param  string $str
     * @param  int $excludeID
     * @return bool
     */
    protected function getByUrlSegment(
        string $class,
        string $str,
        $excludeID = null
    ): bool
    {
        if (!isset(static::$_cached_get_by_url[$str]))
        {
            $list = $class::get()->filter('URLSegment', $str);
            if ($excludeID) $list = $list->exclude('ID', $excludeID);

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
