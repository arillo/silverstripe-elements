<?php
namespace Arillo\Elements\History\Diff;

class ElementDiff
{
    /** 'added' | 'removed' | 'modified' | 'unchanged' | 'reordered' | 'depth_limit' | 'error' */
    public string $status = 'unchanged';
    public int $elementId = 0;
    public string $elementClass = '';
    public string $elementSummary = '';
    /** @var FieldDiff[] */
    public array $fieldChanges = [];
    /** @var ElementDiff[] */
    public array $childChanges = [];
    public ?int $oldSort = null;
    public ?int $newSort = null;
    public ?string $errorMessage = null;
}
