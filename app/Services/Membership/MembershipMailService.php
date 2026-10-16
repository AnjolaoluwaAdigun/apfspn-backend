<?php

namespace App\Services\Membership;

use App\Mail\ApplicationReceivedMail;
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
}
