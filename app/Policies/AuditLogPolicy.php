<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function before(
        User $user,
        string $ability
    ): ?bool {
        $isActive = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        if (! $isActive) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(
        User $user,
        AuditLog $auditLog
    ): bool {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        AuditLog $auditLog
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        AuditLog $auditLog
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        AuditLog $auditLog
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        AuditLog $auditLog
    ): bool {
        return false;
    }

    private function isAdministrator(
        User $user
    ): bool {
        return $user->role()
            ->where(
                'role_name',
                'ADMINISTRATOR'
            )
            ->exists();
    }
}