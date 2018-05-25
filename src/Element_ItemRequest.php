<?php
namespace Arillo\Elements;

use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use SilverStripe\Control\Controller;
use SilverStripe\CMS\Controllers\CMSPageEditController;

class Element_ItemRequest extends VersionedGridFieldItemRequest
{
    private static
        $allowed_actions = [
            'publishPage',
        ]
    ;

    protected function getFormActions()
    {
        $fields = parent::getFormActions();
        $actions = $this->record->getCMSActions();
        if ($actions->exists())
        {
            $fields->merge($actions);
        }

        // if (is_a(Controller::curr(), CMSPageEditController::class))
        // {
        //     $fields->removeByName('action_doPublish');
        // }
        return $fields;
    }

    public function publishPage($data, $form)
    {
        $this->record->publishPage();
        return $this->respond();
    }

    protected function respond()
    {
        $controller = $this->getToplevelController();
        $form = $this->ItemEditForm();

        return $this->customise([
            'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
            'ItemEditForm' => $form,
        ])->renderWith($this->getTemplates());
    }
}
