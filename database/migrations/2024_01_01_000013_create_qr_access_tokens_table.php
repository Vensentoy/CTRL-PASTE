<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR-gated access (user request: "only by scanning the qr can they
 * access the website, not by typing the url"). Coordinator-only
 * "Generate QR" button creates a short-lived single-use token;
 * scanning it via GET /qr/enter sets a session flag that the
 * EnsureQrAccess middleware checks before allowing GET/POST /login.
 * Decided: 2-min TTL, single-use, friendly blocked view, local bypass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique(); // sha256 of plain token
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('max_uses')->default(1);
            $table->unsignedTinyInteger('used_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_access_tokens');
    }
};
