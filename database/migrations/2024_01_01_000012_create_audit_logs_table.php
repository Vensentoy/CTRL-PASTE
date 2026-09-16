<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AuditLog (data-model.md): append-only, never edited or deleted. One
 * row per logged action across the app — login, submissions, review
 * decisions, reassignment, company switches, reopens, account changes.
 *
 * `user_id` is nullable per data-model.md ("some system events may not
 * have one") even though every hook wired in this delivery always
 * passes an authenticated user — left nullable so a future system-level
 * event (e.g. a scheduled job) doesn't need a migration change to log
 * one. `nullOnDelete` rather than `cascadeOnDelete`: a deleted user
 * account must never silently erase the audit trail of what that
 * account did.
 *
 * No `updated_at` — data-model.md's `timestamp` field is `created_at`
 * only; the table is append-only so there is nothing to update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('action_type', [
                'Login', 'Submit', 'Approve', 'Return', 'Update', 'AccountChange',
            ]);
            $table->text('action_details')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
