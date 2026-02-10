<?php

namespace Quentin\InfisicalSync;

final readonly class DiffResult
{
    /**
     * @param  array<string, string>  $localOnly
     * @param  array<string, string>  $remoteOnly
     * @param  array<string, array{local: string, remote: string}>  $different
     * @param  string[]  $same
     */
    public function __construct(
        public array $localOnly,
        public array $remoteOnly,
        public array $different,
        public array $same,
    ) {}

    public function hasDifferences(): bool
    {
        return count($this->localOnly) > 0
            || count($this->remoteOnly) > 0
            || count($this->different) > 0;
    }
}
