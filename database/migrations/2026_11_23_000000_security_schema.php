<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Niveles de acceso (tabla roles)
        Schema::create('roles', function (Blueprint $table) {
            $table->integer('level')->primary();
            $table->string('name', 60);
            $table->string('description', 255);
        });

        DB::table('roles')->insert([
            ['level' => 500, 'name' => 'Administrador general', 'description' => 'Crea usuarios, asigna niveles 499 y 300, desactiva cuentas y autoriza restablecimientos.'],
            ['level' => 499, 'name' => 'Supervisor de consulta', 'description' => 'Consulta usuarios, niveles, estados y registros de auditoría. No modifica nada.'],
            ['level' => 300, 'name' => 'Usuario estándar', 'description' => 'Accede a su página personal y cambia su propia contraseña.'],
        ]);

        // 2. Usuarios: estado (desactivar sin borrar) y sal opcional (Argon2id la incluye en el hash)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role_level');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->string('salt', 64)->nullable()->change();
        });

        // Integridad referencial users.role_level -> roles.level (SQLite no admite ALTER ... FOREIGN KEY)
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('role_level')->references('level')->on('roles')->restrictOnDelete();
            });
        }

        // 3. Intentos fallidos de inicio de sesión
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('identifier', 255)->index();
            $table->string('ip_address', 45)->index();
            $table->timestamp('cleared_at')->nullable();   // se llena al bloquear o al autenticarse bien
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 4. Bloqueos temporales
        Schema::create('account_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 10);                    // pair (cuenta+IP) | account | ip
            $table->string('identifier', 255)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('reason', 80);
            $table->timestamp('locked_until')->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. Restablecimiento de contraseña: solo se guarda el HASH del token
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('account_locks');
        Schema::dropIfExists('login_attempts');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['role_level']));
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_active', 'deactivated_at']));
        Schema::dropIfExists('roles');
    }
};
