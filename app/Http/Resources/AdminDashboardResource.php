<?php

namespace App\Http\Resources;

use App\Enums\ArticleStatus;
use App\Enums\DashboardSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $members = $this->resource[DashboardSection::Members->value] ?? null;
        $events = $this->resource[DashboardSection::Events->value] ?? null;
        $articles = $this->resource[DashboardSection::Articles->value] ?? null;
        $activity = $this->resource[DashboardSection::RecentActivity->value] ?? null;

        return [
            'members' => $this->when($members !== null, fn () => [
                'counts_by_status' => new MemberOverviewResource($members['counts_by_status']),
                'pending_approvals' => $members['pending_approvals'],
                'new_registrations' => $members['new_registrations'],
            ]),
            'events' => $this->when($events !== null, fn () => array_filter([
                'upcoming' => isset($events['upcoming']) ? DashboardEventResource::collection($events['upcoming']) : null,
                'registrations_this_week' => $events['registrations_this_week'] ?? null,
            ], fn (mixed $value) => $value !== null)),
            'articles' => $this->when($articles !== null, fn () => [
                'counts_by_status' => $articles['counts_by_status'],
                'awaiting_review' => [
                    'total' => $articles['counts_by_status'][ArticleStatus::InReview->value],
                    'items' => DashboardArticleResource::collection($articles['awaiting_review']),
                ],
            ]),
            'recent_activity' => $this->when($activity !== null, fn () => ActivityFeedResource::collection($activity)),
        ];
    }
}
