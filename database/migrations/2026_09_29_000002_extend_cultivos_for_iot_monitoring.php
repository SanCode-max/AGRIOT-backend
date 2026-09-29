<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cultivos', function (Blueprint $table) {
            if (!Schema::hasColumn('cultivos', 'device_id')) {
                $table->string('device_id', 120)->nullable()->unique()->after('user_id');
            }
            if (!Schema::hasColumn('cultivos', 'fecha_estimada_cosecha')) {
                $table->date('fecha_estimada_cosecha')->nullable()->after('fecha_siembra');
            }
            if (!Schema::hasColumn('cultivos', 'estado_actual')) {
                $table->string('estado_actual', 80)->nullable()->after('fecha_estimada_cosecha');
            }
        });

        if (Schema::hasColumn('cultivos', 'fecha_cosecha')) {
            DB::table('cultivos')->whereNull('fecha_estimada_cosecha')
                ->update(['fecha_estimada_cosecha' => DB::raw('fecha_cosecha')]);
        }
        if (Schema::hasColumn('cultivos', 'estado')) {
            DB::table('cultivos')->whereNull('estado_actual')
                ->update(['estado_actual' => DB::raw('estado')]);
        }
    }

    public function down(): void
    {
        Schema::table('cultivos', function (Blueprint $table) {
            $table->dropUnique(['device_id']);
            $table->dropColumn(['device_id', 'fecha_estimada_cosecha', 'estado_actual']);
        });
    }
};
