<?php
namespace Arillo\Elements;

use SilverStripe\Forms\GridField\{
    GridField,
    GridField_HTMLProvider,
    GridField_ActionProvider,
    GridField_URLHandler,
    GridField_FormAction
};
use SilverStripe\Control\Controller;

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 *
 * @package forms
 * @subpackage fields-gridfield
 */

class GridFieldDefaultElementsButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler
{
    protected $targetFragment;

    public function __construct($targetFragment = "after")
    {
        $this->targetFragment = $targetFragment;
    }

    public function getHTMLFragments($gridField)
    {
        $button = (new GridField_FormAction(
            $gridField,
            'createdefaults',
            _t(__CLASS__ . '.CreateDefaultElements', 'Create default elements'),
            'createdefaults',
            null
        ))
            ->setAttribute('data-icon', 'add')
            ->addExtraClass('btn action btn-primary font-icon-plus action_createdefaults')
            ->setForm($gridField->getForm())
        ;

        return [
            $this->targetFragment => '<p class="grid-createdefaults-button">' . $button->Field() . '</p>',
        ];
    }

    public function getActions($gridField)
    {
        return [ 'createdefaults' ];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'createdefaults')
        {
            return $this->handleCreateDefaults($gridField);
        }
    }

    public function getURLHandlers($gridField)
    {
        return [
            'createdefaults' => 'handleCreateDefaults',
        ];
    }

    public function handleCreateDefaults($gridField, $request = null)
    {
        $count = ElementsExtension::create_default_elements(
            $gridField->getForm()->getRecord()
        );
        Controller::curr()
            ->getResponse()
            ->addHeader('X-Status', rawurlencode("Created {$count} elements."))
        ;
    }
}
