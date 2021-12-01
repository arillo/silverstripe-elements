<?php
namespace Arillo\Elements;

use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class VersionedElement_ItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = ['publishPage'];

    protected function getFormActions()
    {
        $fields = parent::getFormActions();
        $actions = $this->record->getCMSActions();
        if ($actions->exists()) {
            $fields->merge($actions);
        }
        return $fields;
    }

    public function publishPage($data, $form)
    {
        $this->record->update($data)->write();

        $this->record->publishPage();
        return $this->respond();
    }

    protected function respond()
    {
        $controller = $this->getToplevelController();
        $form = $this->ItemEditForm();

        return $this->customise([
            'Backlink' => $controller->hasMethod('Backlink')
                ? $controller->Backlink()
                : $controller->Link(),
            'ItemEditForm' => $form,
        ])->renderWith($this->getTemplates());
    }
}
