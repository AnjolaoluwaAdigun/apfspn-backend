<?php

namespace App\Models;

use App\Enums\MemberType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationProfile extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_uuid',
        'type',
        'name',
        'registration_number',
        'year_established',
        'business_type',
        'institution_type',
        'department',
        'address',
        'state_code',
        'city',
        'website',
        'document_public_id',
    ];

    protected $hidden = [
        'document_public_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MemberType::class,
            'year_established' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
