<?php

namespace Modules\Groups\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Groups\Models\Group;

/**
 * Runs when the Groups module is installed: seeds super-admin, admin and user.
 */
class GroupsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Group::ensureDefaults();
    }
}
