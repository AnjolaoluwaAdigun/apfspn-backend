<?php

namespace App\Services\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuditLogService
{
    private const SECRET_KEYS = ['password', 'secret', 'token', 'otp', 'api_key'];

    private const HASHED_KEYS = ['email', 'phone'];

    private const IGNORED_FIELDS = ['updated_at', 'updated_by_admin_uuid'];

    public function __construct(private readonly AuditLogRepositoryInterface $auditLogs) {}

    public function record(string $action, ?Model $subject = null, ?array $changes = null, ?Admin $actor = null): ?AuditLog
    {
        $adminUuid = $actor?->getKey() ?? $this->currentAdminUuid();

        if ($adminUuid === null || ($changes !== null && $changes['changed_fields'] === [])) {
            return null;
        }

        $request = request();

        return $this->auditLogs->create([
            'admin_uuid' => $adminUuid,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_uuid' => $subject?->getKey(),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_body' => $this->sanitise($request->all()),
            'changes' => $changes,
        ]);
    }

    public function changeSet(Model $model): array
    {
        $changed = array_values(array_diff(array_keys($model->getDirty()), self::IGNORED_FIELDS));

        return [
            'changed_fields' => $changed,
            'previous' => $this->sanitise(Arr::only($model->getRawOriginal(), $changed)),
        ];
    }

    public function currentAdminUuid(): ?string
    {
        $user = auth()->user();
        $token = $user?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->can('admin')) {
            return null;
        }

        return $user->getKey();
    }

    public function sanitise(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile || $this->isBase64File($value)) {
                continue;
            }

            $name = Str::lower((string) $key);

            $clean[$key] = match (true) {
                Str::contains($name, self::SECRET_KEYS) => '[REDACTED]',
                is_array($value) => $this->sanitise($value),
                Str::contains($name, self::HASHED_KEYS) && is_scalar($value) && $value !== '' => 'sha256:'.hash('sha256', Str::lower(trim((string) $value))),
                default => $value,
            };
        }

        return $clean;
    }

    private function isBase64File(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (Str::startsWith($value, 'data:') && Str::contains($value, ';base64,')) {
            return true;
        }

        return strlen($value) > 1024 && preg_match('/^[A-Za-z0-9+\/\r\n]+={0,2}$/', $value) === 1;
    }
}
