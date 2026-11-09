<?php

namespace Modules\Contacts\Models;

use App\Cms\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Contacts\Database\Factories\ContactMessageFactory;
use Modules\Contacts\Policies\ContactMessagePolicy;

/**
 * A message sent with the form on the contacts page. New until the team marks it handled.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property string $locale
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('contact_messages')]
#[Fillable(['name', 'email', 'phone', 'subject', 'message', 'locale', 'status'])]
#[UseFactory(ContactMessageFactory::class)]
#[UsePolicy(ContactMessagePolicy::class)]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory, Searchable;

    public const NEW = 'new';

    public const HANDLED = 'handled';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::NEW, self::HANDLED];
    }

    public function isNew(): bool
    {
        return $this->status === self::NEW;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUnhandled(Builder $query): void
    {
        $query->where('status', self::NEW);
    }
}
