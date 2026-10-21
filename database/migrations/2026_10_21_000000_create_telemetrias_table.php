<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetrias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultivo_id')->nullable()->constrained('cultivos')->nullOnDelete();
            $table->string('device_id', 120);
            $table->decimal('temperatura_suelo_C', 6, 2)->nullable();
            $table->decimal('humedad_relativa_calibrada_pct', 6, 2)->nullable();
            $table->timestamp('observed_at');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'observed_at']);
            $table->index(['cultivo_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetrias');
    }
};
