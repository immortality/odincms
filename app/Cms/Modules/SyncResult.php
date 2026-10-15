<?php

namespace App\Cms\Modules;

/**
 * What ModuleManager::sync() changed.
 */
final readonly class SyncResult
{
    /**
     * @param  list<string>  $added  slugs of newly discovered modules
     * @param  list<string>  $updated  slugs whose name, version or core flag changed
     * @param  list<string>  $restored  slugs whose folder is back after being missing
     * @param  list<string>  $missing  slugs newly flagged as missing (folder gone or manifest invalid)
     * @param  array<string, string>  $invalid  manifest file => error message
     */
    public function __construct(
        public array $added = [],
        public array $updated = [],
        public array $restored = [],
        public array $missing = [],
        public array $invalid = [],
    ) {}

    public function hasChanges(): bool
    {
        return $this->added !== [] || $this->updated !== [] || $this->restored !== [] || $this->missing !== [];
    }

    public function hasErrors(): bool
    {
        return $this->invalid !== [];
    }
}
