<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuidPrimaryKey;
}
