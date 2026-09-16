<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base identity table for the whole system.
 *
 * Every account is a User first, then EITHER a Student OR a Coordinator
 * (never both, never neither) via the `role` column + a matching row in
 * the students or coordinators table. See data-model.md.
 *
 * Notes:
 * - `username`, not email — this system has no email verification flow
 *   (confirmed in data-model.md).
 * - `password` starts as a coordinator-issued temp password; the
 *   `must_change_password` flag forces a reset on first login.
 * - `status` covers the account lifecycle: Active / Inactive / Completed /
 *   Archived (Completed/Archived mainly apply to Students, but the column
 *   lives here since it's a User-level concept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['student', 'coordinator']);
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->enum('status', ['Active', 'Inactive', 'Completed', 'Archived'])
                ->default('Active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
