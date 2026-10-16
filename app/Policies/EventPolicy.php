<?php

namespace App\Policies;

use App\Enums\ePermission;
use App\Enums\EventStatus;
use App\Models\Admin;
use App\Models\Event;

class EventPolicy
{
    public function update(Admin $admin, Event $event): bool
    {
        if (! $admin->hasPermissionTo(ePermission::EventsUpdate->value)) {
            return false;
        }

        if ($admin->hasPermissionTo(ePermission::EventsPublish->value)) {
            return true;
        }

        return $event->status === EventStatus::Draft && $event->created_by_admin_uuid === $admin->getKey();
    }
}
