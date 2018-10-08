<?php
namespace Arillo\Elements;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;

class ElementURLSegmentField extends SiteTreeURLSegmentField
{
    public function getPage()
    {
        $idField = $this
            ->getForm()
            ->Fields()
            ->dataFieldByName('ID')
        ;

        return ($idField && $idField->Value())
            ? ElementBase::get()->byID($idField->Value())
            : ElementBase::singleton()
        ;
    }
}
