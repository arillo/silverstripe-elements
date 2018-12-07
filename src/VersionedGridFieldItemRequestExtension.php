<?php
namespace Arillo\Elements;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

class VersionedGridFieldItemRequestExtension extends Extension
{
    /**
     * Remove unpublish action
     * @param  FieldList $actions
     * @return FieldList
     */
    public function updateFormActions(FieldList $actions)
    {
        if (is_a($this->owner->getRecord(), ElementBase::class))
        {
            $actions->removeByName('action_doUnpublish');

            if ($archive = $actions->fieldByName('action_doArchive'))
            {
                $archive
                    ->setTitle(_t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Delete', 'Delete'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-danger btn-hide-outline font-icon-trash-bin action-delete')
                ;
            }
        }

        return $actions;
    }
}

