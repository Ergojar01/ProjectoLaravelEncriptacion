<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereNotIn('role_level', [300, 499, 500])->exists()) {
            throw new RuntimeException('Corrige los niveles desconocidos antes de aplicar la migración.');
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('description');
        });
        DB::table('roles')->insert([
            ['id' => 300, 'description' => 'Usuario básico'],
            ['id' => 499, 'description' => 'Visualizador'],
            ['id' => 500, 'description' => 'Administrador'],
        ]);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique();
            $table->foreign('role_level')->references('id')->on('roles')->restrictOnDelete();
        });
        DB::table('users')->select('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['username' => 'user-'.$user->id]);
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable(false)->change();
        });

        Schema::create('account_locks', function (Blueprint $table): void {
            $table->string('account_key', 64)->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('updated_at')->index();
        });
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_key', 64);
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('attempted_at')->index();
            $table->index(['account_key', 'attempted_at']);
        });
        Schema::create('password_resets', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('token_hash')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable();
        });
        DB::table('users')->where(function ($query): void {
            $query->whereNotNull('temp_requested_at')->orWhere('must_change_password', true);
        })->select('id', 'temp_requested_at', 'temp_password_hash', 'temp_password_expires_at', 'temp_password_used_at')
            ->chunkById(500, function ($users): void {
                DB::table('password_resets')->insert($users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'token_hash' => $user->temp_password_hash,
                    'requested_at' => $user->temp_requested_at,
                    'expires_at' => $user->temp_password_expires_at,
                    'used_at' => $user->temp_password_used_at,
                ])->all());
            });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
            $table->integer('actor_level')->change();
            $table->string('result')->default('success');
            $table->ipAddress('ip_address')->nullable();
            $table->foreign('actor_level')->references('id')->on('roles')->restrictOnDelete();
        });
        DB::table('audit_logs')->whereNotIn('actor_id', DB::table('users')->select('id'))->update(['actor_id' => null]);
        DB::table('sessions')->whereNotIn('user_id', DB::table('users')->select('id'))->delete();
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_id']);
            $table->dropForeign(['actor_level']);
            $table->dropColumn(['result', 'ip_address']);
        });
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('account_locks');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_level']);
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
        Schema::dropIfExists('roles');
    }
};
