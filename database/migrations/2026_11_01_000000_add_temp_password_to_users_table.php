<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('crypto_version');
            $table->timestamp('temp_password_expires_at')->nullable()->after('must_change_password');
            $table->timestamp('temp_password_used_at')->nullable()->after('temp_password_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'temp_password_expires_at', 'temp_password_used_at']);
        });
    }
};
