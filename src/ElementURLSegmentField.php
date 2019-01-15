<?php
namespace Arillo\Elements;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;

class ElementURLSegmentField extends SiteTreeURLSegmentField
{
    private static $allowed_actions = [
        'suggest'
    ];

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

    public function suggest($request)
    {
        if (!$request->getVar('value')) {
            return $this->httpError(
                405,
                _t('SilverStripe\\CMS\\Forms\\SiteTreeURLSegmentField.EMPTY', 'Please enter a URL Segment or click cancel')
            );
        }

        $page = $this
            ->getPage()
            ->generateUniqueURLSegment($request->getVar('value'))
        ;

        Controller::curr()
            ->getResponse()
            ->addHeader('Content-Type', 'application/json')
        ;
        return Convert::raw2json(array('value' => $page->URLSegment));
    }
}
