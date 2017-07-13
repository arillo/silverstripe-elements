<?php
/**
 * This class is a {@link GridField} component that adds a delete action for
 * objects.
 *
 * This component also supports unlinking a relation instead of deleting the
 * object.
 *
 * Use the {@link $removeRelation} property set in the constructor.
 *
 * <code>
 * $action = new GridFieldDuplicateAction(); // delete objects permanently
 *
 * // removes the relation to object instead of deleting
 * $action = new GridFieldDuplicateAction(true);
 * </code>
 *
 * @package forms
 * @subpackage fields-gridfield
 */
class GridFieldDuplicateAction implements GridField_ColumnProvider, GridField_ActionProvider {

    /**
     * Add a column 'Delete'
     *
     * @param GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns) {
        if(!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * Return any special attributes that will be used for FormField::create_tag()
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName) {
        return array('class' => 'col-buttons');
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName) {
        if($columnName == 'Actions') {
            return array('title' => '');
        }
    }

    /**
     * Which columns are handled by this component
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField) {
        return array('Actions');
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField) {
        return array('duplicaterecord');
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return string - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName) {

        if(!$record->canCreate()) return;

        $field = GridField_FormAction::create($gridField,  'DuplicateRecord'.$record->ID, false, "duplicaterecord",
                array('RecordID' => $record->ID))
            ->addExtraClass('gridfield-button-duplicate')
            ->setAttribute('title', _t('GridAction.Duplicate', "Duplicate"))
            ->setAttribute('data-icon', 'addpage')
            ->setDescription(_t('GridAction.DUPLICATE_DESCRIPTION','Duplicate'));
        return $field->Field();
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data - form data
     * @return void
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
        if($actionName == 'duplicaterecord') {
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if(!$item) {
                return;
            }

            if(!$item->canCreate()) {
                throw new ValidationException(
                    _t('GridFieldAction_Delete.DeletePermissionsFailure',"No delete permissions"),0);
            }

            $clone = $item->duplicate(true);
            if (!$clone || $clone->ID < 1) {
                user_error("Error Duplicating!", E_USER_ERROR);
            }
        }
    }
}
