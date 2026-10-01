<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->foreignId('tramite_id')->nullable()->constrained('tramites_estudiante')->nullOnDelete();
            $table->string('numero_comprobante', 50);
            $table->string('tipo_comprobante', 30)->default('RECIBO'); // RECIBO, BOLETA, FACTURA, TICKET
            $table->string('concepto', 180);
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 30)->default('EFECTIVO'); // EFECTIVO, TRANSFERENCIA, YAPE, PLIN, TARJETA_DEBITO, TARJETA_CREDITO, DEPOSITO_BANCARIO
            $table->string('numero_operacion', 60)->nullable();
            $table->dateTime('fecha_pago')->useCurrent();
            $table->string('estado', 30)->default('PAGADO'); // PAGADO, ANULADO
            $table->text('motivo_anulacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institucion_id', 'numero_comprobante']);
            $table->index(['institucion_id', 'fecha_pago']);
            $table->index(['institucion_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
