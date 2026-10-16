<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MemberIdSequence extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'state_code',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
        ];
    }
}
