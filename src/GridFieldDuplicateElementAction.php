<?php

namespace Arillo\Elements;

use SilverStripe\ORM\DB;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\ValidationException;

/**
 * GridField action that duplicates an element (recursively, all locales).
 * The duplicate is inserted directly below the original element,
 * shifting all subsequent elements down by one.
 */
class GridFieldDuplicateElementAction extends AbstractGridFieldComponent implements
    GridField_ColumnProvider,
    GridField_ActionProvider,
    GridField_ActionMenuItem
{
    public function getTitle($gridField, $record, $columnName)
    {
        return _t(__CLASS__ . '.Duplicate', 'Duplicate');
    }

    public function getGroup($gridField, $record, $columnName)
    {
        return GridField_ActionMenuItem::DEFAULT_GROUP;
    }

    public function getExtraData($gridField, $record, $columnName)
    {
        $field = $this->getDuplicateAction($gridField, $record);
        return $field ? $field->getAttributes() : null;
    }

    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns ?? [])) {
            $columns[] = 'Actions';
        }
    }

    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
        return [];
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        $field = $this->getDuplicateAction($gridField, $record);
        return $field ? $field->Field() : null;
    }

    public function getActions($gridField)
    {
        return ['duplicateelement'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName !== 'duplicateelement') {
            return;
        }

        $list = $gridField->getList();
        $item = $list->byID($arguments['RecordID']);

        if (!$item) {
            throw new ValidationException('Element not found.');
        }

        $originalSort = (int) $item->Sort;

        // Bump sort of all following siblings
        $table = $item->baseTable();
        DB::query(sprintf(
            "UPDATE \"%s\" SET \"Sort\" = \"Sort\" + 1 WHERE \"Sort\" > %d AND \"%s\" = %d AND \"%s\" = %s",
            $table,
            $originalSort,
            $item->PageID ? 'PageID' : 'ElementID',
            $item->PageID ?: $item->ElementID,
            'RelationName',
            DB::get_conn()->quoteString($item->RelationName),
        ));

        // duplicate() respects cascade_duplicates (child elements, links)
        // Fluent's onAfterDuplicate copies all localised table rows
        $clone = $item->duplicate();
        $clone->Sort = $originalSort + 1;
        $clone->write();
    }

    private function getDuplicateAction(GridField $gridField, $record): ?GridField_FormAction
    {
        $title = $this->getTitle($gridField, $record, 'Actions');

        return GridField_FormAction::create(
            $gridField,
            'DuplicateElement' . $record->ID,
            false,
            'duplicateelement',
            ['RecordID' => $record->ID],
        )
            ->addExtraClass('action--duplicate btn--icon-md font-icon-page-multiple btn--no-text grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--duplicate font-icon-page-multiple')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);
    }
}
