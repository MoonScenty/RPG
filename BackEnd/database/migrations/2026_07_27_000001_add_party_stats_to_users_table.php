<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1)->after('name');
            $table->unsignedInteger('exp')->default(0)->after('level');
            $table->unsignedInteger('gold')->default(1000)->after('exp');
            $table->unsignedInteger('honor_points')->default(0)->after('gold');
            $table->unsignedInteger('diamond')->default(0)->after('honor_points');
            $table->unsignedInteger('fatigue')->default(100)->after('diamond');
            $table->unsignedInteger('max_fatigue')->default(100)->after('fatigue');
            $table->string('icon_path')->nullable()->after('max_fatigue');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'level', 'exp', 'gold', 'honor_points', 'diamond',
                'fatigue', 'max_fatigue', 'icon_path',
            ]);
        });
    }
};
