<?php
namespace Arillo\Elements\History\Diff;

class FieldDiff
{
    public string $fieldName = '';
    public string $fieldLabel = '';
    /** 'text' | 'html' | 'has_one' | 'has_one_image' | 'has_one_file' | 'many_many' | 'settings' */
    public string $kind = 'text';
    public mixed $oldValue = null;
    public mixed $newValue = null;
}
