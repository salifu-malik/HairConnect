<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;
use Throwable;

class AuditLogService
{
    private AuditLogRepository $auditLogRepository;

    public function __construct(AuditLogRepository $auditLogRepository)
    {
        $this->auditLogRepository = $auditLogRepository;
    }

    /**
     * Record a security or business audit event.
     *
     * Audit failures should not normally break the main application flow.
     */
    public function log(array $data): ?int
    {
        try {
            $data['ip_address'] = $data['ip_address']
                ?? ($_SERVER['REMOTE_ADDR'] ?? null);

            $data['user_agent'] = $data['user_agent']
                ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

            return $this->auditLogRepository->create($data);
        } catch (Throwable $e) {
            /*
             * Audit logging must not cause authentication or business
             * operations to fail.
             *
             * Technical logging will be added through Monolog separately.
             */
            error_log(
                'Audit logging failed: ' . $e->getMessage()
            );

            return null;
        }
    }

    /**
     * Record an authentication event.
     */
    public function auth(
        string $action,
        ?object $user = null,
        ?string $description = null,
        array $metadata = []
    ): ?int {
        $roles = [];

        if ($user !== null && isset($user->roles)) {
            $roles = is_array($user->roles)
                ? $user->roles
                : [$user->roles];
        }

        return $this->log([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => !empty($roles)
                ? implode(',', $roles)
                : null,
            'action' => $action,
            'module' => 'AUTH',
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
