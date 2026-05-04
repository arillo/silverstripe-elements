<?php
namespace Arillo\Elements\History\Diff;

class DiffTree
{
    /** @var ElementDiff[] ordered as in newer version, removed pinned at old position. */
    public array $changes = [];

    /** Optional reorder marker, emitted once at the top if reordering detected. */
    public ?ElementDiff $reorder = null;
}
