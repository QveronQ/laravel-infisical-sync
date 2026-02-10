<?php

namespace Quentin\InfisicalSync;

final readonly class PushResult
{
    /**
     * @param  string[]  $created
     * @param  string[]  $updated
     * @param  string[]  $unchanged
     */
    public function __construct(
        public array $created,
        public array $updated,
        public array $unchanged,
    ) {}

    public function hasChanges(): bool
    {
        return count($this->created) > 0 || count($this->updated) > 0;
    }
}
