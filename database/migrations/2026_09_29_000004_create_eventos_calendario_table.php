<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_calendario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->string('tipo', 40)->default('general');
            $table->timestamps();
            $table->index(['user_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_calendario');
    }
};
