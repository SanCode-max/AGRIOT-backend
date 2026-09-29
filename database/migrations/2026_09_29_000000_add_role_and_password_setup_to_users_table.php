<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('user')->after('password');
            $table->boolean('must_change_password')->default(false)->after('rol');
            $table->string('telefono', 10)->nullable()->change();
        });

        // Existing accounts were the platform operators before roles existed.
        DB::table('users')->update(['rol' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'must_change_password']);
            $table->string('telefono', 10)->nullable(false)->change();
        });
    }
};
