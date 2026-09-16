<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Single write path into AuditLog (data-model.md: append-only). Every
 * controller that wants to record an action calls this rather than
 * touching AuditLog directly, so there's exactly one place that ever
 * inserts a row.
 *
 * action_type is constrained to data-model.md's fixed set (Login /
 * Submit / Approve / Return / Update / AccountChange) via the
 * migration's enum column — this class doesn't re-validate that list,
 * it trusts callers to pass one of the six.
 */
class AuditLogger
{
    public function log(?User $user, string $actionType, ?string $details = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action_type' => $actionType,
            'action_details' => $details,
        ]);
    }
}
