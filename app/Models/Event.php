<?php

namespace App\Models;

use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    public ?array $registrationCounts = null;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'image_url',
        'starts_at',
        'ends_at',
        'venue',
        'address',
        'city',
        'state_code',
        'is_virtual',
        'virtual_link',
        'audience',
        'capacity',
        'registration_closes_at',
        'schedule',
        'status',
        'cancellation_reason',
        'published_at',
        'cancelled_at',
        'created_by_admin_uuid',
        'updated_by_admin_uuid',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_virtual' => 'boolean',
            'capacity' => 'integer',
            'schedule' => 'array',
            'audience' => EventAudience::class,
            'status' => EventStatus::class,
        ];
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class, 'event_uuid')->orderBy('sort_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_uuid');
    }

    public function registrationClosesAt(): Carbon
    {
        return $this->registration_closes_at ?? $this->starts_at;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    public function registrationOpen(): bool
    {
        return $this->status === EventStatus::Published
            && ! $this->hasStarted()
            && now()->lt($this->registrationClosesAt());
    }
}
