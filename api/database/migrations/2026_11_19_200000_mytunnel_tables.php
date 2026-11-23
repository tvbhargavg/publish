<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
            $t->unsignedInteger('max_tunnels')->default(5);
            $t->unsignedInteger('max_reservations')->default(3);
            $t->unsignedBigInteger('monthly_bytes_limit')->nullable();
            $t->timestamp('disabled_at')->nullable();
        });

        Schema::create('subdomain_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('subdomain', 63)->unique();
            $t->timestamps();
        });

        Schema::create('tunnels', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('token_id')->nullable();
            $t->string('subdomain', 63)->index();
            $t->unsignedInteger('local_port');
            $t->string('status', 16)->default('active');
            $t->timestamp('started_at');
            $t->timestamp('closed_at')->nullable();
            $t->string('close_reason', 64)->nullable();
            $t->unsignedBigInteger('requests')->default(0);
            $t->unsignedBigInteger('bytes_in')->default(0);
            $t->unsignedBigInteger('bytes_out')->default(0);
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'started_at']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 64)->index();
            $t->string('target_type', 64)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->json('metadata')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tunnels');
        Schema::dropIfExists('subdomain_reservations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_admin', 'max_tunnels', 'max_reservations', 'monthly_bytes_limit', 'disabled_at']));
    }
};
