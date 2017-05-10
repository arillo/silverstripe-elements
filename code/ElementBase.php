<?php

use arillo\elements\ElementsExtension;

class ElementBase extends DataObject implements CMSPreviewable
{
    protected static $_cached_get_by_url = array();

    private static $db = array(
        'Title' => 'Text',
        'URLSegment' => 'Varchar(255)',
        'RelationName' => 'Varchar(255)',
        'Visible' => 'Boolean',
        'Sort' => 'Int'
        );

    private static $has_one = array(
        'Page' => 'Page',
        'Element' => 'ElementBase'
        );

    private static $default_sort = 'Sort ASC';

    private static $extensions = array(
        'GridFieldIsPublishedExtension',
        'Versioned("Stage","Live")'
        );

    private static $translate = array(
        'Title'
        );

    private static $searchable_fields = array(
        'ClassName',
        'URLSegment'
        );

    private static $defaults = [
        'Visible' => true
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $filter = URLSegmentFilter::create();

        if (!$this->URLSegment) {
            $this->URLSegment = $this->Title;
        }
        $this->URLSegment = $filter->filter($this->URLSegment);

        if (!$this->URLSegment) {
            $this->URLSegment = uniqid();
        }

        $class = $this->ClassName;
        $count = 2;
        while ($this->getByUrlSegment($class, $this->URLSegment, $this->ID)) {
            // add a -n to the URLSegment if it already existed
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment) . '-' . $count;
            $count++;
        }

        if (!$this->Sort)
        {
            $holder_filter = array('PageID' => $this->PageID);
            if($this->ElementID) $holder_filter = array('ElementID' => $this->ElementID);
            $this->Sort = ElementBase::get()
            ->filter($holder_filter)
            ->max('Sort') + 1;
        }
    }

    public function onAfterDelete() {

        if(Versioned::current_stage() !== 'Stage') {
            foreach($this->owner->Elements() as $element)
            {
                $element->deleteFromStage('Live');
            }
            return;
        }

        foreach($this->owner->Elements() as $element)
        {
            $element->deleteFromStage('Live');
            $element->deleteFromStage('Stage');
            $element->delete();
        }

        parent::onAfterDelete();
    }

    public function addCMSFieldsHeader($fields, $pageOrElement)
    {

        $relationName = Controller::curr()->request->param('FieldName');
        // $recordClassesMap = ElementsExtension::relation_classes_map($pageOrElement, $relationName);

        $description = '<div class="cms-page-info"><b>'. $this->i18n_singular_name() . '</b> – ID: ' . $this->ID;
        $description .= ' PageID: ' . $this->PageID . ' ElementID: ' . $this->ElementID;

        if (ClassInfo::exists('Fluent'))
        {
            $locale = Fluent::alias(Fluent::current_locale());
            $description .= ' – Locale: <span class="element-lang element-lang-'.$locale.'">'.$locale.'</span></div>';
        }


        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create('ClassNameDescription', $description),
            // DropdownField::create('ClassName', _t('ElementBase.Type', 'Type'), $recordClassesMap),
            TextField::create('Title', _t('ElementBase.Title', 'Title'), null, 255),
            HiddenField::create('RelationName', $relationName, $relationName)
        ]);

        if (!ClassInfo::exists('Fluent'))
        {
            $fields->addFieldToTab(
                'Root.Main',
                CheckboxField::create('Visible', _t('ElementBase.Visible', 'Is element visible'))
            );
        }

    }

    public function populate($type, $id, $relation){
        $this->Title = $this->i18n_singular_name() . ' title';
        $this->PageID = $id;
        $this->RelationName = $relation;
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));
        $pageOrElement = $this->getHolder();
        if ($pageOrElement) $this->addCMSFieldsHeader($fields, $pageOrElement);
        return $fields;
    }

    public function getHolder()
    {
        if ($this->Element()->exists()) return $this->Element();
        if ($this->Page()->exists()) return $this->Page();
        return false;
    }

    /**
     * Remove Save & Publish to make the handling easier for the editor.
     * Elements get published when the page gets published.
     */
    public function getBetterButtonsActions()
    {
        $fields = parent::getBetterButtonsActions();
        $fields->removeByName('action_publish');
        return $fields;
    }

    public function getLanguages()
    {
        $pills = '';
        if (ClassInfo::exists('Fluent'))
        {
            $activeLocales = $this->owner->getFilteredLocales();
            if ($locales = Fluent::locales())
            {
                foreach ($locales as $key)
                {
                    $class = in_array($key, $activeLocales) ? 'active' : 'inactive';
                    $lang = Fluent::alias($key);
                    $pills .= "<span class='element-lang $class'>{$lang}</span><br>";
                }
            }
        }
        return DBField::create_field('HTMLVarchar', $pills);
    }


    public function PreviewLink($action = null)
    {
        return Controller::join_links(Director::baseURL(), 'cms-preview', 'show', $this->ClassName, $this->ID);
    }

    public function Link()
    {
        return $this->Page()->Link($this->URLSegment);
    }

    public function CMSEditLink()
    {
        return $this->Link();
    }

    public function Render($IsPos = null, $IsFirst = null, $IsLast = null, $IsEvenOdd = null)
    {
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
     * @param $str
     * @return Product|Boolean
     */
    protected function getByUrlSegment($class, $str, $excludeID = null) {
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

}
