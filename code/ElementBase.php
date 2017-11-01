<?php

use arillo\elements\ElementsExtension;

class ElementBase extends DataObject implements CMSPreviewable
{
    protected static $_cached_get_by_url = array();

    private static $db = array(
        'Title' => 'Text',
        'URLSegment' => 'Varchar(255)',
        'RelationName' => 'Varchar(255)',
        'Visible' => 'Boolean(1)',
        'Sort' => 'Int'
        );

    private static $has_one = array(
        'Page' => 'Page',
        'Element' => 'ElementBase'
        );

    private static $default_sort = 'Sort ASC';

    private static $extensions = array(
        'Versioned("Stage","Live")'
        );

    private static $translate = array(
        'Title'
        );

    private static $searchable_fields = array(
        'ClassName',
        'URLSegment'
        );

    private static $summary_fields = array(
        'ClassName',
        'Title',
        'StatusFlags'
        );

    private static $defaults = [
        'Visible' => true
    ];

    private static $better_buttons_actions = array (
        'publishPage'
    );

    public static function hasModifiedElement($elements)
    {
        if ($elements->Count() > 0)
        {
            foreach($elements as $element)
            {
                if ($element->stagesDiffer('Stage','Live')) return true;
                if ($element->hasManyComponent('Elements'))
                {
                    ElementBase::hasModifiedElement($element->Elements());
                }
            }
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this
            ->generateUniqueURLSegment()
            ->generateElementSortForHolder();

    }

    public function generateElementSortForHolder(){
        if (!$this->Sort)
        {
            $holder_filter = array('PageID' => $this->PageID);
            if($this->ElementID) $holder_filter = array('ElementID' => $this->ElementID);
            $this->Sort = ElementBase::get()
            ->filter($holder_filter)
            ->max('Sort') + 1;
        }
        return $this;
    }

    public function generateUniqueURLSegment(){
        $filter = URLSegmentFilter::create();

        if (!$this->URLSegment) {
            $this->URLSegment = $this->Title;
        }
        $this->URLSegment = $filter->filter($this->URLSegment);

        if (!$this->URLSegment) {
            $this->URLSegment = uniqid();
        }

        $count = 2;
        while ($this->getByUrlSegment('ElementBase', $this->URLSegment, $this->ID)) {
            // add a -n to the URLSegment if it already existed
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment) . '-' . $count;
            $count++;
        }
        return $this;
    }

    public function onAfterDelete() {

        // This is done in order to unpublish sub-elements when unpublishing an element,
        // since unpublishing an element also calls the onAfterDelete callback
        if(Versioned::current_stage() !== 'Stage') {
            foreach($this->owner->Elements() as $element)
            {
                $element->deleteFromStage('Live');
            }
            return;
        }

        // Delete own element from live if called from GridFieldDeleteAction
        $this->deleteFromStage('Live');
        foreach($this->owner->Elements() as $element)
        {
            $element->deleteFromStage('Live');
            $element->deleteFromStage('Stage');
            $element->delete();
        }

        parent::onAfterDelete();
    }

    public function addCMSFieldsHeader($fields)
    {

        $relationName = Controller::curr()->request->param('FieldName');

        $description = '<div class="cms-page-info"><b>'. $this->i18n_singular_name() . '</b> – ID: ' . $this->ID;
        $description .= ' PageID: ' . $this->PageID . ' ElementID: ' . $this->ElementID;

        if (ClassInfo::exists('Fluent'))
        {
            $locale = Fluent::alias(Fluent::current_locale());
            $description .= ' – Locale: <span class="element-state element-state-'.$locale.'">'.$locale.'</span>';
        }

        $description .= " " . $this->getStatusFlags('') . '</div>';


        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create('ClassNameDescription', $description),
            // DropdownField::create('ClassName', _t('ElementBase.Type', 'Type'), $recordClassesMap),
            TextField::create('Title', _t('ElementBase.Title', 'Title'), null, 255),
            TextField::create('URLSegment', _t('Element.URLSegment', 'URLSegment'), null, 255),
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
        $this->addCMSFieldsHeader($fields);

        if (!$this->isInDB()
            && $this->class === "ElementBase"
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
        if(is_a(Controller::curr(),'CMSPageEditController')){
            $fields->removeByName('action_publish');
        }
        return $fields;
    }

    public function getBetterButtonsUtils(){
        $fields = parent::getBetterButtonsUtils();
        if($this->ID && is_a(Controller::curr(),'CMSPageEditController') && ($this->stagesDiffer('Stage','Live') || $this->hasModifiedElement($this->Elements()))){
            $fields->unshift($publish_action = BetterButtonCustomAction::create('publishPage', 'Publish page'));
            $publish_action
            ->addExtraClass("ss-ui-action-constructive")
            ->setAttribute('data-icon', 'disk')
            // ->setAttribute('data-icon', 'accept')
            // ->setAttribute('data-icon-alternate', 'disk')
            ->setAttribute('data-text-alternate', _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & publish'));
        }
        return $fields;
    }

    public function publishPage() {
        $look = true;
        $parent = $this;
        while($look){
            if($parent = $parent->getHolder()){
                if(is_a($parent, 'SiteTree')){
                    $look = false;
                }
            } else {
                $look = false;
            }
        }
        if($parent->doPublish()){
            return _t('ElementBase.PageAndElementsPublished', "Page & elements published");
        }
        return _t('ElementBase.PageAndElementsPublishError', "There was an error publishing the page");
    }

    public function getType(){
        return _t($this->class.'.SINGULARNAME', $this->singular_name());
    }

    public function getStatusFlags($separator = '<br>')
    {
        $modified = false;
        $state = [];
        $published = _t('ElementBase.State_published', 'published');
        $draft = _t('ElementBase.State_draft', 'draft');
        $modifiedContent = _t('ElementBase.State_modified', 'modified');
        $notVisible = _t('ElementBase.State_hidden', 'hidden');

        $state[] = $this->isPublished()
            ? "<span class='element-state active'>{$published}</span>"
            : "<span class='element-state modified'>{$draft}</span>"
        ;

        if ($this->stagesDiffer('Stage', 'Live')) $modified = true;

        if (ElementBase::hasModifiedElement($this->owner->Elements())) $modified = true;

        if ($modified) $state[] = "<span class='element-state modified'>$modifiedContent</span>";

        if (!ClassInfo::exists('Fluent'))
        {
            if (!$this->Visible) $state[] = "<span class='element-state inactive'>{$notVisible}</span>";
        }

        return DBField::create_field('HTMLVarchar', implode($state, $separator));
    }

    public function isPublished()
    {
        if (!$this->hasExtension('Versioned')) return false;
        if (!$this->isInDB()) return false;

        $table = $this->class;
        while (($p = get_parent_class($table)) !== 'DataObject')
        {
            $table = $p;
        }
        return (bool) DB::query("SELECT \"ID\" FROM \"{$table}_Live\" WHERE \"ID\" = {$this->ID}")->value();
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
                    $pills .= "<span class='element-state $class'>{$lang}</span><br>";
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

    // Permissions
    public function canView($member = null) {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canEdit($member = null) {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canDelete($member = null) {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canCreate($member = null) {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

}
