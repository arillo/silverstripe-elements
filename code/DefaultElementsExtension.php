<?php
use arillo\elements\ElementsExtension;

class DefaultElementsExtension extends LeftAndMainExtension
{
	private static $allowed_actions = array(
		'doCreateDefaults'
	);

	public function doCreateDefaults($data, $form){

		$className = $this->owner->stat('tree_class');
		$SQL_id = Convert::raw2sql($data['ID']);
		$record = DataObject::get_by_id($className, $SQL_id);

		if(!$record || !$record->ID){
			throw new SS_HTTPResponse_Exception("Bad record ID #" . (int)$data['ID'], 404);
		}

		$definedElements = $record->Elements()->map('ClassName','ClassName');

		$relationNames = ElementsExtension::relation_names($record);

		$count = 0;
		if(count($relationNames)>0){
			foreach ($relationNames as $relationName) {
				$elementClasses = ElementsExtension::relation_classes_map($record, $relationName, 'element_defaults');
				foreach ($elementClasses as $key => $value) {
					if(!isset($definedElements[$key])){
						$element = new $key;
						$element->Title = $value.' title';
						$element->PageID = $SQL_id;
						$element->RelationName = $relationName;
						$element->write();
						$count++;
					}
				}
			}
		}

		$this->owner->response->addHeader(
			'X-Status',
			rawurlencode('Created '.$count.' elements.')
		);

		return $this->owner
		->getResponseNegotiator()
		->respond($this->owner->request);
	}
}
