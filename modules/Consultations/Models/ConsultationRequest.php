<?php

namespace Modules\Consultations\Models;

use App\Cms\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Consultations\Database\Factories\ConsultationRequestFactory;
use Modules\Consultations\Policies\ConsultationRequestPolicy;

/**
 * A request sent with the "Request a free consultation" form. New until the team marks it handled.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $service
 * @property string|null $message
 * @property string $locale
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('consultation_requests')]
#[Fillable(['name', 'email', 'service', 'message', 'locale', 'status'])]
#[UseFactory(ConsultationRequestFactory::class)]
#[UsePolicy(ConsultationRequestPolicy::class)]
class ConsultationRequest extends Model
{
    /** @use HasFactory<ConsultationRequestFactory> */
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
