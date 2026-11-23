<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Quién realizó (o sufrió) la acción, cuando se conoce
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Cuenta afectada cuando un administrador actúa sobre otra persona
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Correo escrito o afectado (se conserva aunque la cuenta se elimine)
            $table->string('identifier', 255)->nullable()->index();
            $table->string('event', 40)->index();
            $table->string('result', 10)->index();      // success | failure | denied
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->string('details', 500)->nullable(); // nunca contraseñas, tokens ni cookies
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
