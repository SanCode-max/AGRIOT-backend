<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cultivos', 'variedad')) {
            Schema::table('cultivos', function (Blueprint $table) {
                $table->string('variedad', 120)->default('Arándano')->after('nombre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cultivos', 'variedad')) {
            Schema::table('cultivos', function (Blueprint $table) {
                $table->dropColumn('variedad');
            });
        }
    }
};
