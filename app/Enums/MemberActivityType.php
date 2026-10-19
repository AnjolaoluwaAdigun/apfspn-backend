<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MemberActivityType: string
{
    use EnumHelpers;

    case ApplicationSubmitted = 'application_submitted';
    case EmailVerified = 'email_verified';
    case MembershipApproved = 'membership_approved';
    case MembershipRejected = 'membership_rejected';
    case MembershipSuspended = 'membership_suspended';
    case MembershipReactivated = 'membership_reactivated';
    case EventRegistered = 'event_registered';
    case EventWaitlisted = 'event_waitlisted';
    case EventPromoted = 'event_promoted';
    case EventRegistrationCancelled = 'event_registration_cancelled';
    case EventAttended = 'event_attended';
    case ProfileUpdated = 'profile_updated';
    case ProfileUpdatedByAdmin = 'profile_updated_by_admin';
    case PasswordChanged = 'password_changed';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';

    public static function forMembershipNotice(MembershipNotice $notice): self
    {
        return match ($notice) {
            MembershipNotice::Approved => self::MembershipApproved,
            MembershipNotice::Rejected => self::MembershipRejected,
            MembershipNotice::Suspended => self::MembershipSuspended,
            MembershipNotice::Reactivated => self::MembershipReactivated,
        };
    }
}
