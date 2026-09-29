<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 160);
            $table->text('contenido');
            $table->date('fecha_asociada')->nullable();
            $table->boolean('completada')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'fecha_asociada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
