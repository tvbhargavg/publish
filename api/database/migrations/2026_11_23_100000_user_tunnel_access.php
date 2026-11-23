<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Premium flag, admin-controlled only. Free users get FREE_MAX_TUNNELS; premium users get users.max_tunnels.
            $t->boolean('tunnel_access')->default(false);
        });
        // Accounts that existed before this flag were created by an operator: keep them working.
        DB::table('users')->update(['tunnel_access' => true]);
        DB::statement('UPDATE users SET email = lower(email)');
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('max_tunnels')->default(4)->change());
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('max_tunnels')->default(5)->change());
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('tunnel_access'));
    }
};
