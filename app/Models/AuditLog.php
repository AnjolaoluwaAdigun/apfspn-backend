<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_uuid',
        'action',
        'auditable_type',
        'auditable_uuid',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'request_body',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'request_body' => 'array',
            'changes' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo(id: 'auditable_uuid');
    }
}
