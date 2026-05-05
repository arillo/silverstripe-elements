<?php
namespace Arillo\Elements\History;

use Arillo\Elements\ElementBase;
use Arillo\Elements\ElementsExtension;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Extension on DataObjectVersionFormFactory. When the version form is built
 * for a page that has ElementsExtension, swap each element-relation GridField
 * for an ElementsHistoryField so the history viewer can render version diffs
 * inline.
 *
 * Only fires inside the version form factory — normal page editing is
 * untouched.
 */
class PageHistoryFormFactoryExtension extends Extension
{
    public function updateFormFields(
        FieldList $fields,
        ?RequestHandler $controller,
        $name,
        array $context,
    ): void {
        $record = $context['Record'] ?? null;
        if (!$record) {
            return;
        }
        if (!$record->hasExtension(ElementsExtension::class)) {
            return;
        }
        if (!ElementBase::config()->get('history_enabled')) {
            return;
        }

        $relationNames = ElementsExtension::page_element_relation_names($record);
        if (!is_array($relationNames)) {
            return;
        }

        foreach (array_keys($relationNames) as $relationName) {
            $existing = $fields->dataFieldByName($relationName);
            if (!$existing) {
                continue;
            }

            $newField = ElementsHistoryField::create($relationName, $existing->Title())
                ->setHolder($record)
                ->setRelationName($relationName);

            $fields->replaceField($relationName, $newField);
        }
    }
}
