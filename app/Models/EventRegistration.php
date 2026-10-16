<?php

namespace App\Models;

use App\Enums\EventRegistrationStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\EventRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    /** @use HasFactory<EventRegistrationFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'event_uuid',
        'user_uuid',
        'status',
        'waitlist_position',
        'registered_at',
        'promoted_at',
        'cancelled_at',
        'attended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRegistrationStatus::class,
            'waitlist_position' => 'integer',
            'registered_at' => 'datetime',
            'promoted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_uuid')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
