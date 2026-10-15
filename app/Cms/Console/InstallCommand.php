<?php

namespace App\Cms\Console;

use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('cms:install
    {--name= : Name of the first super admin}
    {--email= : Email of the first super admin}
    {--password= : Password of the first super admin (avoid on shared machines: it stays in the shell history)}
    {--no-admin : Do not create a super admin}')]
#[Description('Install OdinCMS: run the migrations, install the core modules and create the first super admin')]
class InstallCommand extends Command
{
    public function handle(ModuleManager $modules): int
    {
        $this->components->info('Installing '.config('app.name').'.');

        // 1. Database
        $this->components->task('Running the migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);

        // 2. Modules
        $sync = $modules->sync();
        foreach ($sync->invalid as $error) {
            $this->components->warn($error);
        }

        $core = array_values(array_intersect((array) config('cms.core_modules'), $modules->discover()->keys()->all()));

        try {
            $order = $modules->dependencyOrder($core);
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($order as $slug) {
            if (Module::where('slug', $slug)->first()?->isInstalled()) {
                $this->components->twoColumnDetail("Module <fg=cyan>{$slug}</>", '<fg=gray>already installed</>');

                continue;
            }

            try {
                $this->components->task("Installing the module <fg=cyan>{$slug}</>", fn () => $modules->install($slug));
            } catch (ModuleException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        // 3. The first super admin
        if (! $this->option('no-admin') && $this->createSuperAdmin() === self::FAILURE) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Done. Sign in at '.url(config('cms.admin_prefix').'/login'));

        return self::SUCCESS;
    }

    private function createSuperAdmin(): int
    {
        $roleName = (string) config('cms.super_admin_role');
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->components->error("The \"{$roleName}\" group does not exist. Is the Groups module in the modules folder?");

            return self::FAILURE;
        }

        if (User::role($roleName)->exists() && ! $this->hasAdminOptions()
            && ! confirm('A super admin already exists. Create another one?', default: false)) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <options=bold>The first super admin</>');

        $input = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'password' => $this->option('password') ?? password('Password (at least 8 characters)', required: true),
        ];
        if ($this->option('password') === null) {
            $input['password_confirmation'] = password('Repeat the password', required: true);
        } else {
            $input['password_confirmation'] = $input['password'];
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $input['name'], 'email' => $input['email']]);
        $user->forceFill([
            'password' => $input['password'],
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
        $user->assignRole($role);

        $this->components->info("Super admin {$user->email} created.");

        return self::SUCCESS;
    }

    private function hasAdminOptions(): bool
    {
        return $this->option('email') !== null;
    }
}
