<?php


/**
 * Defines the button that unpublishes a record
 *
 * @author  Uncle Cheese <unclecheese@leftandmain.com>
 * @package  silverstripe-gridfield-betterbuttons
 */
class BetterButton_UnpublishAndDelete extends BetterButtonCustomAction {


	/**
	 * Builds the button
	 */
	public function __construct() {
		parent::__construct('unpublishAndDelete', _t('SiteTree.BUTTONUNPUBLISHANDDELETE', 'Unpublish & delete'));
	}


	/**
	 * Adds a class to identify this as a destructive action
	 * @return void
	 */
	public function baseTransform() {
		parent::baseTransform();
		return $this->addExtraClass('ss-ui-action-destructive');
	}


	/**
	 * Determines if the button should show
	 * @return boolean
	 */
	public function shouldDisplay() {
		return $this->gridFieldRequest->recordIsPublished() && $this->gridFieldRequest->record->canEdit();
	}
}
