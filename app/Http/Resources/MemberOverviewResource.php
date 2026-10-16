<?php

namespace App\Http\Resources;

use App\Enums\MembershipStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $counts = ['all' => $this->resource['all'] ?? 0];

        foreach (MembershipStatus::values() as $status) {
            $counts[$status] = $this->resource[$status] ?? 0;
        }

        return $counts;
    }
}
