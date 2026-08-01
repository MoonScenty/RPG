<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** condition_kind === 'position'일 때 사용 - 'front'|'back'. self/ally/enemy를 가리키는 condition_scope와는 별개 축이라 컬럼을 분리한다. */
    public function up(): void
    {
        Schema::table('mercenary_gambits', function (Blueprint $table) {
            $table->string('condition_position', 10)->nullable()->after('condition_debuff_key');
        });
    }

    public function down(): void
    {
        Schema::table('mercenary_gambits', function (Blueprint $table) {
            $table->dropColumn('condition_position');
        });
    }
};
