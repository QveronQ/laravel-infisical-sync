<?php

namespace Quentin\InfisicalSync;

final readonly class SyncResult
{
    /**
     * @param  string[]  $pushed
     * @param  string[]  $pulled
     * @param  string[]  $conflictsResolved
     * @param  string[]  $conflictsSkipped
     * @param  string[]  $unchanged
     * @param  array<string, string>  $localValues
     * @param  array<string, string>  $remoteValues
     */
    public function __construct(
        public array $pushed,
        public array $pulled,
        public array $conflictsResolved,
        public array $conflictsSkipped,
        public array $unchanged,
        public array $localValues,
        public array $remoteValues,
    ) {}

    public function hasChanges(): bool
    {
        return count($this->pushed) > 0
            || count($this->pulled) > 0
            || count($this->conflictsResolved) > 0;
    }
}
