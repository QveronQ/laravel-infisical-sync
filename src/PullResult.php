<?php

namespace Quentin\InfisicalSync;

final readonly class PullResult
{
    /**
     * @param  string[]  $created
     * @param  string[]  $updated
     * @param  string[]  $unchanged
     * @param  array<string, string>  $remoteValues
     * @param  array<string, string>  $localValues
     */
    public function __construct(
        public array $created,
        public array $updated,
        public array $unchanged,
        public array $remoteValues,
        public array $localValues,
    ) {}

    public function hasChanges(): bool
    {
        return count($this->created) > 0 || count($this->updated) > 0;
    }
}
