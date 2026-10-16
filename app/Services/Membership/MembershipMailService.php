<?php

namespace App\Services\Membership;

use App\Enums\MembershipNotice;
use App\Mail\ApplicationReceivedMail;
use App\Mail\MembershipStatusMail;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class MembershipMailService
{
    public function __construct(private readonly MembershipRepositoryInterface $memberships) {}

    public function sendApplicationReceived(string $membershipUuid): void
    {
        $membership = $this->memberships->findWithUser($membershipUuid);

        if (! $membership) {
            return;
        }

        Mail::to($membership->user->email)->send(new ApplicationReceivedMail(
            $membership->user->name,
            $membership->user->organisationProfile?->name,
            $membership->member_type,
        ));
    }

    public function sendStatusNotice(string $membershipUuid, MembershipNotice $notice): void
    {
        $membership = $this->memberships->findWithUser($membershipUuid);

        if (! $membership?->user) {
            return;
        }

        Mail::to($membership->user->email)->send(new MembershipStatusMail(
            $notice,
            $membership->user->name,
            $membership->member_type,
            $membership->member_id,
            match ($notice) {
                MembershipNotice::Rejected => $membership->rejection_reason,
                MembershipNotice::Suspended => $membership->suspension_reason,
                default => null,
            },
            $membership->expires_at?->toFormattedDateString(),
        ));
    }
}
