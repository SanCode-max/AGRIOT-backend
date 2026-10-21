<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultivo_id')->nullable()->constrained('cultivos')->nullOnDelete();
            $table->string('device_id', 120)->index();
            $table->string('tipo', 40);
            $table->string('severidad', 20)->default('aviso');
            $table->decimal('valor', 8, 2)->nullable();
            $table->string('unidad', 20)->nullable();
            $table->string('mensaje', 255);
            $table->text('recomendacion');
            $table->string('estado', 20)->default('activa');
            $table->timestamp('primera_deteccion_en');
            $table->timestamp('ultima_deteccion_en');
            $table->timestamp('resuelta_en')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['cultivo_id', 'estado', 'created_at']);
            $table->index(['device_id', 'tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
