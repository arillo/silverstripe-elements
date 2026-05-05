<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;

/**
 * DataExtension applied to ElementBase. Adds a Root.History tab to the
 * element edit form containing the standard React HistoryViewerField, so
 * editors can drill down into a single element's version history.
 *
 * Gated by ElementBase.history_enabled and history_per_element_tab.
 */
class ElementHistoryExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields): void
    {
        if (!$this->owner->isInDB()) {
            return;
        }
        if (!$this->owner->hasExtension(Versioned::class)) {
            return;
        }
        if (!ElementBase::config()->get('history_enabled')) {
            return;
        }
        if (!ElementBase::config()->get('history_per_element_tab')) {
            return;
        }

        $fields->addFieldToTab(
            'Root.History',
            HistoryViewerField::create('ElementHistory')
                ->setContextKey('arillo-element-' . $this->owner->ID),
        );
    }
}
