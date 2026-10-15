<?php

namespace Modules\Groups\Models;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A user group: a spatie role with a translatable title.
 *
 * @property int $id
 * @property string $name Identifier used in code (kebab-case), e.g. "super-admin"
 * @property string|null $title Translated to the current locale
 * @property string $guard_name
 */
#[Translatable('title')]
class Group extends Role
{
    use HasTranslations;

    /**
     * The seeded groups, with their titles. They cannot be deleted or renamed.
     *
     * @var array<string, array<string, string>>
     */
    public const DEFAULTS = [
        'super-admin' => ['en' => 'Super admins', 'ru' => 'Суперадминистраторы', 'ky' => 'Супер-администраторлор'],
        'admin' => ['en' => 'Administrators', 'ru' => 'Администраторы', 'ky' => 'Администраторлор'],
        'user' => ['en' => 'Users', 'ru' => 'Пользователи', 'ky' => 'Колдонуучулар'],
    ];

    /**
     * Create the default groups if they are missing. The admin group gets admin.access.
     */
    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $name => $title) {
            $group = self::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (empty($group->getTranslations('title'))) {
                $group->setTranslations('title', $title)->save();
            }
        }

        self::findByName('admin', 'web')->givePermissionTo(
            Permission::findOrCreate('admin.access', 'web')
        );
    }

    /**
     * The title in the current locale, or the name as a headline ("tour-guides" → "Tour Guides").
     */
    public function label(): string
    {
        return $this->getTranslation('title', app()->getLocale(), true) ?: Str::headline($this->name);
    }

    public function isProtected(): bool
    {
        return in_array($this->name, (array) config('cms.protected_groups'), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === config('cms.super_admin_role');
    }
}
