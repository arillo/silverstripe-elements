<?php
class ElementBase extends DataObject implements CMSPreviewable {

	private static $db = array(
		'URLSegment' => 'Varchar(255)',
		'Sort' => 'Int'
		);

	private static $has_one = array(
		'Page' => 'Page'
		);

	private static $default_sort = 'Sort ASC';

	private static $extensions = array(
		'GridFieldIsPublishedExtension',
		'Versioned("Stage","Live")'
		);

	private static $translate = 'none';

	private static $searchable_fields = array(
		'ClassName',
		'URLSegment'
		);

	public function onBeforeWrite(){
		parent::onBeforeWrite();
		if(!$this->Sort){
			$this->Sort = ElementBase::get()
			->filter(array('PageID' => $this->PageID))
			->max('Sort') + 1;
		}
	}

	public function addCMSFieldsHeader($fields, $page){
		// TODO: check fluent dependency
		$recordClassesMap = ElementsExtension::parseClasses($page->stat('elements'));
		$fields->addFieldToTab('Root.Main', LiteralField::create('ClassNameDescription',
			'<div class="cms-page-info"><b>'. $this->i18n_singular_name() . '</b> – ID: ' . $this->ID . ' – Locale: <img src="'.ELEMENTS_DIR.'/images/languages/'. Fluent::current_locale() .'.gif"> ' . Fluent::current_locale() . '</div>')
		);
		$fields->addFieldToTab('Root.Main', DropdownField::create("ClassName", _t('ElementBase.Type', 'Type'), $recordClassesMap));
		$fields->addFieldToTab('Root.Main', TextField::create('Title', _t('ElementBase.Title', 'Title'), null, 255));
	}

	public function getCMSFields() {

		$fields = FieldList::create(TabSet::create('Root'));

		$page = $this->getHolder();

	    // if no page reference available, try to get it from the session
		if(!$page && $currentPageID = Session::get('CMSMain.currentPage')){
			$page = Page::get_by_id('Page', $currentPageID);
		}

		if($page){
			$this->addCMSFieldsHeader($fields, $page);
		}
		return $fields;
	}

	public function getHolder() {
		if($this->Page()->exists()) return $this->Page();
		return false;
	}

	// remove Save & Publish to make the handling easier for the editor. Elements get published when the page gets published.
	public function getBetterButtonsActions() {
		$fields = parent::getBetterButtonsActions();
		$fields->removeByName('action_publish');
		$fields->removeByName('action_unpublish');
		return $fields;
	}

	public function getLanguages(){
		// TODO: check fluent dependency
		$locales = $this->getFilteredLocales();
		$icons = '';
		foreach ($locales as $key) {
			$icons .= '<img src="'.ELEMENTS_DIR.'/images/languages/'.$key.'.gif"/><br>';
		}
		return DBField::create_field('HTMLVarchar', $icons);
	}

	// public function Parent(){
	// 	return DataObject::get_by_id('SiteTree', $this->PageID);
	// }
	// public function Link($action = null) {
	// 	return Controller::join_links(Director::baseURL(), $this->RelativeLink($action));
	// }

	public function PreviewLink($action = null){
		return Controller::join_links(Director::baseURL(), 'cms-preview', 'show', $this->ClassName, $this->ID);
	}

	public function Link() {
		return $this->Page()->Link($this->URLSegment);
	}

	public function CMSEditLink(){
		return $this->Link();
	}

	public function Render() {
		$controller = Controller::curr();
		return $controller->customise($this)->renderWith($this->ClassName);
	}

}
