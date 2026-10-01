<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->string('username', 80);
            $table->string('email', 150);
            $table->string('password', 255);
            $table->string('nombres', 120);
            $table->string('apellido_paterno', 80);
            $table->string('apellido_materno', 80)->nullable();
            $table->string('documento_tipo', 20)->nullable();
            $table->string('documento_numero', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('foto', 255)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();
            
            $table->unique(['institucion_id', 'username']);
            $table->unique(['institucion_id', 'email']);
            $table->unique(['institucion_id', 'documento_numero']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->nullable()->constrained('instituciones');
            $table->string('nombre', 50);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_sistema')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 120);
            $table->string('modulo', 80);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['usuario_id', 'rol_id']);
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
            $table->primary(['rol_id', 'permiso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('usuario_rol');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('usuarios');
    }
};
