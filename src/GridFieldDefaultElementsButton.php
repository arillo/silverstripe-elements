<?php
namespace Arillo\Elements;

use SilverStripe\Forms\GridField\{
    GridField,
    GridField_HTMLProvider,
    GridField_ActionProvider,
    GridField_URLHandler,
    GridField_FormAction
};

// use \Controller;

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 *
 * @package forms
 * @subpackage fields-gridfield
 */

class GridFieldDefaultElementsButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler {

    /**
     * Fragment to write the button to
     */
    protected $targetFragment;

    /**
     * @param string $targetFragment The HTML fragment to write the button into
     * @param array $exportColumns The columns to include in the createdefaults
     */
    public function __construct($targetFragment = "after") {
        $this->targetFragment = $targetFragment;
    }

    /**
     * Place the createdefaults button in a <p> tag below the field
     */
    public function getHTMLFragments($gridField) {
        $button = new GridField_FormAction(
            $gridField,
            'createdefaults',
            _t('TableListField.CREATEDEFAULTELEMENTS', 'Create default elements'),
            'createdefaults',
            null
        );
        $button->setAttribute('data-icon', 'add');
        $button->addExtraClass('action_createdefaults');
        $button->setForm($gridField->getForm());
        return array(
            $this->targetFragment => '<p class="grid-createdefaults-button">' . $button->Field() . '</p>',
        );
    }

    /**
     * createdefaults is an action button
     */
    public function getActions($gridField) {
        return array('createdefaults');
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
        if($actionName == 'createdefaults') {
            return $this->handleCreateDefaults($gridField);
        }
    }

    /**
     * it is also a URL
     */
    public function getURLHandlers($gridField) {
        return array(
            'createdefaults' => 'handleCreateDefaults',
        );
    }

    /**
     * Handle the export, for both the action button and the URL
     */
    public function handleCreateDefaults($gridField, $request = null) {
        $count = ElementsExtension::create_default_elements($gridField->getForm()->getRecord());
        Controller::curr()->getResponse()->addHeader('X-Status', rawurlencode("Created {$count} elements."));
    }

}
