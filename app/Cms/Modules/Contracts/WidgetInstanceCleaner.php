<?php

namespace App\Cms\Modules\Contracts;

/**
 * Deletes a module's widget instances on uninstall (spec §9). Implemented in Phase 5.
 */
interface WidgetInstanceCleaner
{
    /**
     * Delete every widget instance whose widget key belongs to the module ("{slug}.*").
     */
    public function remove(string $slug): void;
}
