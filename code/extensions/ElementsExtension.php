<?php

class ElementsExtension extends DataExtension {


	protected $elementClass;

	public function __construct($elementClass = 'ElementBase') {
		parent::__construct();
		$this->elementClass = $elementClass;
	}

	// define has_many based on elementClass
	public function extraStatics($class = null, $extension = null) {
		return array('has_many' => array('Elements' => $this->elementClass));
	}

	// private static $has_many = array(
	// 	'Elements' => 'Element'
	// 	);
	// public function Elements(){
	// 	$class = $this->elementClass;
	// 	return $class::get()->filter(array('PageID' => $this->owner->ID))->sort(array('Sort'=>'ASC'));
	// }

	public static function parseClasses($relations) {
		if (!isset($relations)) {
			user_error('you have to define the list of elements in your .yml config file');
		}
		$baseClass ='ElementBase';
		$classes = array();
		foreach ($relations as $key => $value) {
			if (ClassInfo::exists($value) && (is_a(singleton($value), $baseClass))) {
				if($label = singleton($value)->stat('singular_name')){
					$classes[$value] = $label;
				} else {
					$classes[$value] = $value;
				}
			}
		}
		return $classes;
	}

	public function updateCMSFields(FieldList $fields) {
		if (!$this->owner->exists()) return;

		// if (in_array($this->owner->ClassName, Config::inst()->get('OrganismExtension', 'excludeClasses'))) {
		// 	$fields->addFieldToTab(
		// 		'Root.Main',
		// 		NoticeField::create(
		// 			'NoOrganismsNotice',
		// 			_t(
		// 				'Page.NoOrganismsMessage',
		// 				'This page generates content automatically and therefore does not allow creation of organisms'
		// 				)
		// 			)
		// 		);
		// 	return;
		// }

		if ($relations = ElementsExtension::parseClasses($this->owner->stat('elements'))) {

			$config = GridFieldConfig_RelationEditor::create()
			->removeComponentsByType('GridFieldDeleteAction')
			->removeComponentsByType('GridFieldAddNewButton')
			->removeComponentsByType('GridFieldAddExistingAutocompleter')
			->addComponents(
				new GridFieldOrderableRows('Sort'),
				$multiClass = new GridFieldAddNewMultiClass()
				);

      		// sort relations
			asort($relations);
			$multiClass->setClasses($relations);

			$paginator = $config->getComponentByType('GridFieldPaginator');
			$paginator->setItemsPerPage(50);

			$dataColumns = $config->getComponentByType('GridFieldDataColumns');

			$dataColumns->setDisplayFields(array(
				// 'Icon' => 'Icon',
				'singular_name'=> 'Type',
				'Title' => 'Title',
				'Languages' => 'Lang'
				));

			$fields->addFieldToTab('Root.Elements',
				$gridfieldItems = GridField::create(
					'Elements',
					_t('ElementsExtension.Elements', 'Elements'),
					$this->owner->getCMSElements(),
					$config
					)
				);

		}
	}

	public function getCMSElements() {
		return $this->owner->Elements()->sort(array('Sort'=>'ASC'));
	}

	public function onAfterPublish() {
		foreach($this->owner->Elements() as $element) {
			$element->write();
			$element->publish('Stage', 'Live');
		}
	}
}
