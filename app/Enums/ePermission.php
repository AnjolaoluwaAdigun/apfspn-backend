<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ePermission: string
{
    use EnumHelpers;

    case DashboardView = 'dashboard.view';

    case MembersView = 'members.view';
    case MembersUpdate = 'members.update';
    case MembersApprove = 'members.approve';
    case MembersSuspend = 'members.suspend';
    case MembersExport = 'members.export';
    case MembersDelete = 'members.delete';

    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsPublish = 'events.publish';
    case EventsCancel = 'events.cancel';

    case EventRegistrationsView = 'event_registrations.view';
    case EventRegistrationsUpdate = 'event_registrations.update';
    case EventRegistrationsExport = 'event_registrations.export';

    case ArticlesView = 'articles.view';
    case ArticlesCreate = 'articles.create';
    case ArticlesUpdate = 'articles.update';
    case ArticlesSubmit = 'articles.submit';
    case ArticlesPublish = 'articles.publish';
    case ArticlesArchive = 'articles.archive';
    case ArticlesDelete = 'articles.delete';

    case AdminsView = 'admins.view';
    case AdminsCreate = 'admins.create';
    case AdminsUpdate = 'admins.update';
    case AdminsDelete = 'admins.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case AuditLogsView = 'audit_logs.view';
}
