<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * mz_* 카탈로그 테이블 전체를 RPGProject/data/*.json(RPGEditor가 관리하는 우리
 * 프로젝트 고유 스키마)에 맞게 재구축한다. 옛 마이그레이션들은 존재하지도 않는
 * mz_project/data(RPG Maker MZ 원본 raw 포맷)를 전제로 짜여 있었다.
 *
 * scope/hit_type/damage_type/stype_id는 우리 데이터에선 문자열 enum이지만, 여기선
 * 그대로 MZ 표준 숫자 코드로 변환해서 저장한다 - BattleEngine.php가 이미 이 숫자
 * 코드(hit_type===1 물리 등) 기준으로 촘촘하게 짜여 있어서, 문자열로 바꾸면 엔진
 * 코드 수십 곳을 전부 고쳐야 한다(시더 쪽에서 한 번만 변환하는 게 훨씬 안전함).
 * mz_weapons/mz_armors는 통합하지 않고 그대로 유지 - EquipmentController/
 * CraftingController/UserMercenary/UserWeapon/UserArmor 전체가 "무기·방어구는
 * 별도 테이블" 전제로 짜여 있어 통합하면 그 시스템 전체를 다시 손봐야 한다.
 *
 * units/battle_units/battle_unit_states는 실제 유저 데이터(용병, 진행 중인 전투)가
 * 들어있을 수 있는 테이블이라 이번엔 건드리지 않는다 - 이 카탈로그 테이블들을 가리키는
 * FK만 잠깐 끊었다가 새 테이블에 다시 건다.
 */
return new class extends Migration
{
    /**
     * 이 마이그레이션은 처음 서버에서 돌렸을 때 user_items/user_weapons/user_armors/
     * user_mercenaries의 FK를 빠뜨려서 "drop table mz_items" 도중 실패했다(재고/장비
     * 슬롯이 mz_items/mz_weapons/mz_armors를 참조 중이라). 그 실패 시점까지 이미
     * 실행된 DDL(FK 일부 drop, mz_troop_members/mz_skills drop)은 MySQL에서 각각
     * 즉시 커밋되므로 되돌아가지 않았다 - 그래서 재실행 시 "이미 없는 FK를 또 drop"
     * 하다 에러 나지 않도록, 모든 FK drop을 존재할 때만 시도하는 형태로 감싼다.
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });
        }
    }

    public function up(): void
    {
        // 1. 카탈로그 테이블을 가리키는 FK부터 끊는다 (없으면 드롭 시 에러). user_items/
        // user_weapons/user_armors/user_mercenaries도 mz_items/mz_weapons/mz_armors를
        // 가리키는 FK가 있다(재고/장비 슬롯) - 처음 작성할 때 빠뜨려서 서버에서
        // "Cannot delete or update a parent row" 에러가 났다(위 dropForeignIfExists() 참고).
        $this->dropForeignIfExists('units', 'class_id');
        $this->dropForeignIfExists('units', 'mz_enemy_id');
        $this->dropForeignIfExists('units', 'mz_actor_id');
        $this->dropForeignIfExists('battle_units', 'class_id');
        $this->dropForeignIfExists('battle_units', 'casting_skill_id');
        $this->dropForeignIfExists('battle_unit_states', 'state_id');
        $this->dropForeignIfExists('user_items', 'mz_item_id');
        $this->dropForeignIfExists('user_weapons', 'mz_weapon_id');
        $this->dropForeignIfExists('user_armors', 'mz_armor_id');
        $this->dropForeignIfExists('user_mercenaries', 'weapon_id');
        $this->dropForeignIfExists('user_mercenaries', 'shield_id');
        $this->dropForeignIfExists('user_mercenaries', 'body_armor_id');
        $this->dropForeignIfExists('user_mercenaries', 'accessory_id');

        // 2. mz_troop_members는 폐지 - Troops.json처럼 mz_troops에 6슬롯 컬럼을 직접 둔다.
        // 드롭 순서 주의: mz_actors/mz_skills가 mz_classes를, mz_troops가 mz_enemies를
        // 참조하므로(이번 마이그레이션 자신이 만드는 FK) 참조하는 쪽을 먼저 지워야 한다.
        // 재실행(직전 시도가 중간에 실패해 이 테이블들이 이미 새 스키마로 만들어져
        // 있는 경우)에도 안전하려면 이 순서가 꼭 지켜져야 한다.
        Schema::dropIfExists('mz_troop_members');
        Schema::dropIfExists('mz_skills');
        Schema::dropIfExists('mz_actors');
        Schema::dropIfExists('mz_troops');
        Schema::dropIfExists('mz_items');
        Schema::dropIfExists('mz_weapons');
        Schema::dropIfExists('mz_armors');
        Schema::dropIfExists('mz_states');
        Schema::dropIfExists('mz_classes');
        Schema::dropIfExists('mz_enemies');
        Schema::dropIfExists('mz_animations');
        Schema::dropIfExists('mz_types');

        // ---- 3. 재생성 (부모 먼저) ----

        // Classes.json: MZ 8스탯 2차원 params 대신 STR/VIT/MND/DEX/AGI/LUK/INT(+HP/MP)
        // 9스탯 레벨별 커브를 그대로 json으로 보관. learnings[]는 mz_skills.class_id +
        // learn_level로 표현(아래).
        Schema::create('mz_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->json('exp_curve');
            /** @var array<int, array{level:int,hp:int,mp:int,str:int,vit:int,mnd:int,dex:int,agi:int,luk:int,int:int}> */
            $table->json('param_curve');
            $table->unsignedSmallInteger('party_hud_icon')->nullable();
            $table->json('traits')->nullable();
        });

        // Skills.json: 더미 구분자("-----이름")는 스킵하고 안 들어온다. class_id/
        // learn_level은 Classes.json의 learnings[]에서 역산해서 채운다. scope/hit_type/
        // damage_type/stype_id는 시더가 MZ 표준 코드로 변환해서 저장(위 설명 참고).
        // occasion(MZ "전투/메뉴 중 언제 쓸 수 있는지")은 우리 게임에 그 개념이 없어
        // 항상 0(항상 가능)으로 고정.
        Schema::create('mz_skills', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedTinyInteger('class_id')->nullable();
            $table->foreign('class_id')->references('id')->on('mz_classes')->nullOnDelete();
            $table->unsignedTinyInteger('learn_level')->nullable();
            $table->string('name', 100);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('mp_cost')->default(0);
            $table->unsignedTinyInteger('scope');
            $table->unsignedTinyInteger('hit_type');
            $table->unsignedTinyInteger('stype_id')->default(0);
            $table->unsignedTinyInteger('occasion')->default(0);
            $table->unsignedTinyInteger('damage_type');
            $table->boolean('critical')->default(false);
            $table->string('damage_formula', 255);
            $table->unsignedTinyInteger('variance')->default(20);
            $table->integer('element_id')->default(0);
            $table->unsignedTinyInteger('repeats')->default(1);
            $table->unsignedTinyInteger('success_rate')->default(100);
            /** 우리 SkillEffect[] 원본(kind/percentValue/flatValue/stateId/chance) - 참고용 보관. */
            $table->json('effects');
            /** MzNoteTagParser::parseSkillTags() 결과 + effects[] 해석 결과(target_add_states 등). */
            $table->json('tags');
        });

        // States.json: id<100/>=100 같은 MZ 버프 관례(is_buff)는 우리 ID 범위(0~45)와
        // 안 맞아 폐지.
        Schema::create('mz_states', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedInteger('priority')->default(0);
            /** MZ 표준 motion 코드(0=없음/1=abnormal/2=sleep/3=dead) - 시더가 우리 문자열에서 변환. */
            $table->unsignedTinyInteger('motion')->default(0);
            $table->boolean('remove_at_battle_end')->default(true);
            $table->boolean('remove_by_restriction')->default(false);
            /** MZ 표준(0=턴 수로 안 사라짐/1=행동 종료 시/2=턴 종료 시) - 시더가 변환. */
            $table->unsignedTinyInteger('auto_removal_timing')->default(0);
            $table->unsignedTinyInteger('min_turns')->default(1);
            $table->unsignedTinyInteger('max_turns')->default(1);
            $table->boolean('remove_by_damage')->default(false);
            $table->unsignedTinyInteger('remove_by_damage_chance')->default(100);
            $table->string('message_when_added', 255)->nullable();
            $table->string('message_when_added_enemy', 255)->nullable();
            $table->string('message_while_active', 255)->nullable();
            $table->string('message_when_removed', 255)->nullable();
            $table->json('traits')->nullable();
            /** MzNoteTagParser::parseStateTags() 결과 - DotPercent/HotPercent/Shield/... */
            $table->json('tags');
        });

        // Items.json 중 kind=consumable만 - weapon/armor는 아래 mz_weapons/mz_armors로.
        Schema::create('mz_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 100);
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('item_type_id')->default(0); // 0=소모품/1=재료(kind=material)
            $table->boolean('consumable')->default(true);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedSmallInteger('animation_id')->default(0);
            $table->unsignedTinyInteger('scope')->nullable();
            $table->unsignedTinyInteger('occasion')->default(0);
            $table->unsignedTinyInteger('hit_type')->nullable();
            $table->unsignedTinyInteger('speed')->default(0);
            $table->unsignedTinyInteger('success_rate')->default(100);
            $table->unsignedTinyInteger('repeats')->default(1);
            $table->unsignedTinyInteger('damage_type')->nullable();
            $table->string('damage_formula', 255)->nullable();
            $table->unsignedTinyInteger('variance')->default(20);
            $table->integer('element_id')->default(0);
            $table->text('description')->nullable();
            /** 우리 SkillEffect[] 원본 - 참고용 보관. */
            $table->json('effects')->nullable();
            $table->unsignedInteger('crafting_cost')->default(0);
            $table->unsignedInteger('crafting_time')->default(0);
            /** MzNoteTagParser 결과(ApplySelfState/ExcludeSelf) + recover_hp/mp/add_states/remove_states/crafting. */
            $table->json('tags')->nullable();
        });

        Schema::create('mz_weapons', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 100);
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('wtype_id')->default(0);
            $table->unsignedTinyInteger('etype_id')->default(0); // 항상 0(무기 슬롯)
            $table->unsignedSmallInteger('animation_id')->default(0);
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            /** [MHP,MMP,ATK,DEF,MAT,MDF,AGI,LUK] 장비 스탯 보너스(MZ 표준 순서, MzWeapon::statBonuses()). */
            $table->json('params');
            $table->json('traits')->nullable();
            $table->unsignedInteger('crafting_cost')->default(0);
            $table->unsignedInteger('crafting_time')->default(0);
            /** {crafting: {seconds, materials:[{type,name,count}], gold_cost}} - CraftingController가 그대로 읽음. */
            $table->json('tags')->nullable();
        });

        Schema::create('mz_armors', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 100);
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('atype_id')->default(0);
            $table->unsignedTinyInteger('etype_id')->default(0); // 2=방패/3=몸/4=장신구(우리 EquipTypes 순서 기준)
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->json('params');
            $table->json('traits')->nullable();
            $table->unsignedInteger('crafting_cost')->default(0);
            $table->unsignedInteger('crafting_time')->default(0);
            $table->json('tags')->nullable();
        });

        // Actors.json: MZ 표준 characterName/characterIndex/faceIndex는 우리 스키마에
        // 없음(battlerName/faceName이 파일명 그대로) - 폐지.
        Schema::create('mz_actors', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
            $table->string('battler_name', 50)->nullable();
            $table->unsignedTinyInteger('class_id');
            $table->foreign('class_id')->references('id')->on('mz_classes');
            $table->string('face_name', 50)->nullable();
            $table->unsignedTinyInteger('initial_level')->default(1);
            $table->unsignedTinyInteger('max_level')->default(99);
            $table->text('profile')->nullable();
            /** [무기, 방패, 머리, 몸, 장신구] 순서의 id 5개(0=없음) - 무기는 mz_weapons.id, 나머지는 mz_armors.id. */
            $table->json('equips');
            $table->json('traits')->nullable();
        });

        // Enemies.json: MZ 8스탯(mhp/mmp/atk/def/mat/mdf/agi/luk) 대신 액터/클래스와
        // 동일한 STR/VIT/MND/DEX/AGI/LUK/INT(+HP/MP) 9스탯 - 전투 시점에 같은 파생
        // 스탯 공식(ATK=STR+무기 등)으로 계산한다.
        Schema::create('mz_enemies', function (Blueprint $table) {
            // units.mz_enemy_id가 이미 unsignedTinyInteger라 타입을 맞춰야 FK가 걸린다
            // (Enemies.json도 지금 1마리뿐이라 tinyint로 충분).
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
            $table->unsignedInteger('hp');
            $table->unsignedInteger('mp');
            $table->unsignedInteger('str');
            $table->unsignedInteger('vit');
            $table->unsignedInteger('mnd');
            $table->unsignedInteger('dex');
            $table->unsignedInteger('agi');
            $table->unsignedInteger('luk');
            $table->unsignedInteger('int');
            $table->unsignedInteger('reward_exp')->default(0);
            $table->unsignedInteger('reward_gold')->default(0);
            /** @var array<int, array{itemId:int,dropRate:int}> */
            $table->json('drops');
            /** MZ 표준 conditionType/skillId/rating 형태로 변환해서 저장 - BattleEngine::pickEnemySkill()이 그대로 읽음. */
            $table->json('actions');
            $table->string('image_type', 20); // dragonBones | animatedSv | staticSv
            $table->string('image', 100);
            $table->decimal('scale', 6, 2)->default(100);
            /** DragonBones 전용: [{motion, animationName}] SV 모션↔실제 클립 매핑. */
            $table->json('motion_map')->nullable();
            $table->json('traits')->nullable();
        });

        // Troops.json: MZ의 members[](x/y 좌표) 대신 우리 화면의 6슬롯 고정 레이아웃을
        // 그대로 컬럼화 - 별도 pivot 테이블(mz_troop_members) 불필요.
        Schema::create('mz_troops', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->string('battleback1', 50)->nullable();
            $table->string('battleback2', 50)->nullable();
            foreach (['front_top', 'front_middle', 'front_bottom', 'back_top', 'back_middle', 'back_bottom'] as $slot) {
                $table->unsignedTinyInteger("{$slot}_enemy_id")->nullable();
                $table->foreign("{$slot}_enemy_id")->references('id')->on('mz_enemies')->nullOnDelete();
            }
            /** @var array{name:string,pan:int,pitch:int,volume:int} */
            $table->json('battle_bgm');
            $table->json('victory_me');
            $table->json('defeat_me');
        });

        // Animations_mv.json(RPG Maker MV 원본 포맷) 그대로 임포트 - id 고정
        // 유지(Items.json weapon.animationId, Enemies.json <AttackAnimation: id>가 참조).
        // Effekseer(.efkefc) 대신 스프라이트시트 셀 애니메이션(animation1/2Name,
        // frames, timings)으로 재생한다 - FrontEnd의 mvAnimation.ts 참고.
        Schema::create('mz_animations', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50)->nullable();
            $table->string('animation1_name', 100)->nullable();
            $table->unsignedSmallInteger('animation1_hue')->default(0);
            $table->string('animation2_name', 100)->nullable();
            $table->unsignedSmallInteger('animation2_hue')->default(0);
            $table->unsignedTinyInteger('position')->default(1);
            $table->json('frames');
            $table->json('timings');
        });

        // Types.json: elements/skillTypes/weaponTypes/armorTypes/equipTypes 배열 - 배열
        // 위치가 곧 참조 ID(mz_system_audio와 같은 전역 설정 1행 패턴, id=1 고정).
        Schema::create('mz_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('elements');
            $table->json('skill_types');
            $table->json('weapon_types');
            $table->json('armor_types');
            $table->json('equip_types');
        });

        // ---- 4. 재구축 전 카탈로그를 참조하던 행 중, 새 카탈로그에 없는 id를 가리키는
        // 건 FK를 다시 걸기 전에 정리한다(id 구성이 완전히 바뀌었으므로) - 이 시점엔
        // 아직 정식 서비스 전이라 테스트 계정의 재고/장비 슬롯 정도만 있을 것으로
        // 보고, 참조가 깨진 행만 지운다(테이블 전체를 비우지 않음).

        // user_items.mz_item_id는 원래 unsignedTinyInteger(255개까지)였는데, 우리
        // Items.json은 690개(소모품+재료+무기+방어구 통합 id 공간)라 smallint로 넓혀야
        // FK가 걸린다(타입이 다르면 errno 150).
        DB::statement('ALTER TABLE user_items MODIFY mz_item_id SMALLINT UNSIGNED NOT NULL');

        DB::table('user_items')->whereNotIn('mz_item_id', DB::table('mz_items')->pluck('id'))->delete();
        DB::table('user_weapons')->whereNotIn('mz_weapon_id', DB::table('mz_weapons')->pluck('id'))->delete();
        DB::table('user_armors')->whereNotIn('mz_armor_id', DB::table('mz_armors')->pluck('id'))->delete();
        DB::table('user_mercenaries')->whereNotNull('weapon_id')->whereNotIn('weapon_id', DB::table('mz_weapons')->pluck('id'))->update(['weapon_id' => null]);
        foreach (['shield_id', 'body_armor_id', 'accessory_id'] as $column) {
            DB::table('user_mercenaries')->whereNotNull($column)->whereNotIn($column, DB::table('mz_armors')->pluck('id'))->update([$column => null]);
        }

        // ---- 5. FK를 새 테이블로 다시 건다 ----
        Schema::table('units', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('mz_classes')->nullOnDelete();
            $table->foreign('mz_enemy_id')->references('id')->on('mz_enemies')->nullOnDelete();
            $table->foreign('mz_actor_id')->references('id')->on('mz_actors')->nullOnDelete();
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('mz_classes')->nullOnDelete();
            $table->foreign('casting_skill_id')->references('id')->on('mz_skills')->nullOnDelete();
        });
        Schema::table('battle_unit_states', function (Blueprint $table) {
            $table->foreign('state_id')->references('id')->on('mz_states');
        });
        Schema::table('user_items', function (Blueprint $table) {
            $table->foreign('mz_item_id')->references('id')->on('mz_items')->cascadeOnDelete();
        });
        Schema::table('user_weapons', function (Blueprint $table) {
            $table->foreign('mz_weapon_id')->references('id')->on('mz_weapons')->cascadeOnDelete();
        });
        Schema::table('user_armors', function (Blueprint $table) {
            $table->foreign('mz_armor_id')->references('id')->on('mz_armors')->cascadeOnDelete();
        });
        Schema::table('user_mercenaries', function (Blueprint $table) {
            $table->foreign('weapon_id')->references('id')->on('mz_weapons')->nullOnDelete();
            $table->foreign('shield_id')->references('id')->on('mz_armors')->nullOnDelete();
            $table->foreign('body_armor_id')->references('id')->on('mz_armors')->nullOnDelete();
            $table->foreign('accessory_id')->references('id')->on('mz_armors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('units', 'class_id');
        $this->dropForeignIfExists('units', 'mz_enemy_id');
        $this->dropForeignIfExists('units', 'mz_actor_id');
        $this->dropForeignIfExists('battle_units', 'class_id');
        $this->dropForeignIfExists('battle_units', 'casting_skill_id');
        $this->dropForeignIfExists('battle_unit_states', 'state_id');
        $this->dropForeignIfExists('user_items', 'mz_item_id');
        $this->dropForeignIfExists('user_weapons', 'mz_weapon_id');
        $this->dropForeignIfExists('user_armors', 'mz_armor_id');
        $this->dropForeignIfExists('user_mercenaries', 'weapon_id');
        $this->dropForeignIfExists('user_mercenaries', 'shield_id');
        $this->dropForeignIfExists('user_mercenaries', 'body_armor_id');
        $this->dropForeignIfExists('user_mercenaries', 'accessory_id');

        Schema::dropIfExists('mz_types');
        Schema::dropIfExists('mz_animations');
        Schema::dropIfExists('mz_troops');
        Schema::dropIfExists('mz_enemies');
        Schema::dropIfExists('mz_actors');
        Schema::dropIfExists('mz_armors');
        Schema::dropIfExists('mz_weapons');
        Schema::dropIfExists('mz_items');
        Schema::dropIfExists('mz_skills');
        Schema::dropIfExists('mz_states');
        Schema::dropIfExists('mz_classes');

        // down()은 이번 재구축 이전 상태로 완전히 되돌리지 않는다(원본 마이그레이션들을
        // 재실행해야 함) - migrate:rollback 한 스텝 이후에는 그 마이그레이션들을 다시
        // 태워야 units/battle_units의 FK와 mz_troop_members가 복구된다.
    }
};
