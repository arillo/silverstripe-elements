<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
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
    /** Re-entry guard so the cascade-bump from a publish doesn't recurse. */
    private static bool $bumping = false;

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

    /**
     * After an element is published, bump the holder page so the page
     * history list gets a new entry that captures this element change.
     * Without this, an element-only publish leaves no trace in the page
     * version list, and the diff between the most recent two page versions
     * would not include the element change.
     *
     * Gated by history_enabled and history_bump_holder_page_on_publish.
     */
    public function onAfterPublish($original): void
    {
        if (!ElementBase::config()->get('history_enabled')) {
            return;
        }
        if (!ElementBase::config()->get('history_bump_holder_page_on_publish')) {
            return;
        }
        if (self::$bumping) {
            // Avoid recursion when the page's publishRecursive cascades back
            // through owned elements that re-trigger this hook.
            return;
        }

        $page = $this->owner->getHolderPage();
        if (!$page || !is_a($page, SiteTree::class)) {
            return;
        }
        if (!$page->isPublished()) {
            return;
        }

        self::$bumping = true;
        try {
            $page->LastEdited = DBDatetime::now()->format(DBDatetime::ISO_DATETIME);
            $page->write();
            $page->publishRecursive();
        } finally {
            self::$bumping = false;
        }
    }
}
