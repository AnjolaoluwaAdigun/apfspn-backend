<?php

namespace Tests\Feature\Foundation;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitise_redacts_secrets_hashes_contacts_and_drops_files(): void
    {
        $clean = app(AuditLogService::class)->sanitise([
            'name' => 'Ada',
            'password' => 'secret123',
            'challenge_token' => 'abc',
            'email' => 'Ada@Example.com',
            'contact' => ['phone' => '08012345678'],
            'photo' => 'data:image/png;base64,iVBORw0KGgo=',
        ]);

        $this->assertSame('Ada', $clean['name']);
        $this->assertSame('[REDACTED]', $clean['password']);
        $this->assertSame('[REDACTED]', $clean['challenge_token']);
        $this->assertSame('sha256:'.hash('sha256', 'ada@example.com'), $clean['email']);
        $this->assertSame('sha256:'.hash('sha256', '08012345678'), $clean['contact']['phone']);
        $this->assertArrayNotHasKey('photo', $clean);
    }

    public function test_record_is_skipped_without_an_admin_token(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['member']);

        $this->assertNull(app(AuditLogService::class)->record('member.updated'));
        $this->assertSame(0, AuditLog::count());
    }

    public function test_record_stores_admin_write_and_skips_no_op_changes(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin, ['admin']);
        $service = app(AuditLogService::class);

        $this->assertNull($service->record('event.updated', $admin, ['changed_fields' => [], 'previous' => []]));

        $log = $service->record('event.updated', $admin, ['changed_fields' => ['title'], 'previous' => ['title' => 'Old']]);

        $this->assertSame($admin->uuid, $log->admin_uuid);
        $this->assertSame(['changed_fields' => ['title'], 'previous' => ['title' => 'Old']], $log->changes);
    }
}
