import { apiRequest } from './api'

export type Side = 'ally' | 'enemy'
export type BattleStatus = 'in_progress' | 'finished'
export type ActionType = 'attack' | 'skill' | 'item' | 'cancel' | 'casting'
/** 일반 공격 시 재생할 SV 모션 - 장착 무기의 wtypeId를 System.json attackMotions로 조회한 결과. */
export type AttackMotion = 'thrust' | 'swing' | 'missile'

export interface BattleUnit {
  id: number
  unit_id: number
  name: string
  sprite: string
  /** units.hud_sprite("Actor1:0" 형식) - 파티 HUD 좌측 얼굴 그래픽 전용(sprite와 다른 에셋일 수 있음), 없으면 null(적 등). */
  hud_sprite: string | null
  /** units.enemy_face(img/faces 파일명, 시트 아님 - 적 한 마리당 독립 이미지 한 장) - 아군이거나 아직 얼굴을 안 채운 적이면 null. */
  enemy_face: string | null
  side: Side
  slot: number
  spd: number
  /** 진짜 ATB 게이지(0~100+, 100 도달 시 행동) - BattleEngine::pickReadyActor()가 매 advanceTurn()마다 갱신. TurnOrderStrip이 다음 턴 순서를 예측할 때 이 값+spd로 틱 시뮬레이션을 이어서 돌린다. */
  atb_gauge: number
  attack_motion: AttackMotion
  /** 일반 공격 시 재생할 mz_animations.id(RPG Maker MV 포맷) - 없으면 애니메이션 없음. */
  weapon_animation_id: number | null
  /** <DragonBonesData: 이름> 노트태그 값(파일명) - 있으면 sv 시트 대신 DragonBones 스켈레톤으로 렌더링. */
  dragonbones_skeleton: string | null
  /** <DragonBonesTextureAtlasData: 이름> 노트태그 값(파일명). */
  dragonbones_atlas: string | null
  /** <DragonBonesMotion: 모션이름, 클립이름> 반복 태그로 만든 맵 - 우리 내부 모션 이름(idle/swing/damage/...) -> 스켈레톤 클립 이름. */
  dragonbones_motions: Record<string, string> | null
  /** <DragonBonesScale: n> 노트태그 값(퍼센트) - bounding box 자동 정규화 크기 위에 추가로 곱해지는 배율. 없으면 100% 취급. */
  dragonbones_scale: number | null
  max_hp: number
  max_mp: number
  current_hp: number
  current_mp: number
  /** BattleEngine::stateMotionFor() - 걸린 상태 중 States.json priority가 가장 높은 것의 motion(1='abnormal'/2='sleep')만 반영, 없으면 null. */
  state_motion: 'abnormal' | 'sleep' | null
  /** 소속 직업(mz_classes)의 <PartyHudIcon: n> 노트태그 값(IconSet.png 아이콘 인덱스) - 적이거나 태그가 없으면 null. */
  party_hud_icon: number | null
}

/** BattleEngine::rollHit()의 3가지 결과 - 물리는 명중률+회피율, 마법은 명중률+마법회피율, 필중(hitType=0)은 항상 'hit'. */
export type HitOutcome = 'hit' | 'missed' | 'evaded'

export interface BattleLogTarget {
  battle_unit_id: number
  damage: number | null
  hp_after: number
  /** 이 대상에게 치명타가 터졌는지(damage_type=1 피해가 아니면 null). */
  critical: boolean | null
  /** 이 대상 기준 명중 판정 - 광역기는 대상마다 각각 굴리므로 같은 스킬이라도 대상별로 다를 수 있음. */
  hit_outcome: HitOutcome
}

export interface BattleLogEntry {
  turn_number: number
  actor_battle_unit_id: number
  target_battle_unit_id: number | null
  action_type: ActionType
  damage: number | null
  /** 단일 대상 공격/스킬의 치명타 발동 여부(피해가 아닌 행동이면 null). 광역기는 targets[].critical을 대신 본다. */
  is_critical: boolean | null
  /** 단일 대상 공격/스킬의 명중 판정('casting'/'cancel' 등 판정 자체가 없는 행은 null). 광역기는 targets[].hit_outcome을 대신 본다. */
  hit_outcome: HitOutcome | null
  target_hp_after: number | null
  /** 광역기(scope 2/8/10) 전용 - 대상이 여럿이라 위 target_battle_unit_id/damage/target_hp_after(단일 대상용)는 null이고 대신 이 배열에 대상별 결과가 담긴다. 단일 대상 행은 null. */
  targets: BattleLogTarget[] | null
  /** <SkillMotion: 모션이름> 스킬 노트태그 - action_type이 'skill'일 때 재생할 SV 모션 이름(swing/thrust/missile/spell/skill/chant 등, sv 배틀러의 18개 MZ 표준 모션 중 하나). 태그가 없으면 null이고, 이때 프론트는 기존처럼 swing으로 폴백한다. action_type이 'item'일 때는 항상 'item' 모션 고정. */
  skill_motion: string | null
  /** <스킬의 Invocation.AnimationId(RPGEditor "애니메이션" 필드) - action_type이 'skill'일 때 재생할 mz_animations.id(playWeaponEffect용, 캐릭터 모션과 별개의 이펙트 스프라이트). 0(없음)이거나 미설정이면 null이라 이펙트 없이 모션만 재생. */
  skill_animation_id: number | null
  /** <CircleImage: 배율, 이름> 스킬 노트태그(캐스팅 스킬 전용) - action_type이 'casting'일 때만 값이 있음. 이름은 /assets/circle/{이름}.png 파일명. */
  circle_image_name: string | null
  /** 위 태그의 배율 - 1이 원본 크기(Pixi scale.set()에 그대로 씀, 퍼센트 아님). */
  circle_image_scale: number | null
  /** action_type이 'item'일 때만 값이 있음 - 사용한 소모 아이템 이름(예: "해독의 물약"). 스킬은 이름을 로그에 안 남기는 것과 달리, 아이템은 문구에 직접 필요해서 시딩값이 아니라 로그 시점에 박아둔다. */
  item_name: string | null
}

export interface BattleState {
  id: number
  status: BattleStatus
  winner_side: Side | null
  turn_number: number
  /** Troops.json의 트룹별 배경 이름(예: "Cyberspace") - null이면 assets.ts가 Grassland로 대체. */
  battleback1: string | null
  battleback2: string | null
  units: BattleUnit[]
  logs: BattleLogEntry[]
  finished?: boolean
}

export function isUnitAlive(unit: BattleUnit): boolean {
  return unit.current_hp > 0
}

/** 진행 중인 시험전투가 있으면 이어서, 없으면 현재 진형으로 새로 시작. */
export function createOrResumeBattle(): Promise<BattleState> {
  return apiRequest('POST', '/api/battles')
}

/** 생성/재개 없이 진행 중인 전투가 있는지만 확인 - 메인 화면의 "이어서 전투" 버튼 노출용. */
export function getActiveBattleId(): Promise<{ id: number | null }> {
  return apiRequest('GET', '/api/battles/active')
}

export function getBattle(id: number): Promise<BattleState> {
  return apiRequest('GET', `/api/battles/${id}`)
}

export function advanceTurn(id: number): Promise<BattleState> {
  return apiRequest('POST', `/api/battles/${id}/turn`)
}

export interface BattleAudio {
  battle_bgm: string | null
  victory_me: string | null
  defeat_me: string | null
  /** GambitCatalog::CASTING_SKILL_NAME("캐스팅") 더미 스킬의 message1(MZ 표준 %1/%2 치환 문법) - %1은 시전자, %2는 대상으로 치환해서 캐스팅 턴 로그에 쓴다. 비어있으면 기본 문구로 대체. */
  casting_message: string | null
}

/** mz_project System.json에서 임포트한 전투 배경음/승리·패배 음악 트랙명(확장자 없음) + 캐스팅 턴 연출용 이펙트. */
export function getBattleAudio(): Promise<BattleAudio> {
  return apiRequest('GET', '/api/battle-audio')
}

export interface MzSoundEffect {
  name: string
  pan: number
  pitch: number
  volume: number
}

/** RPG Maker MV 애니메이션 프레임 하나의 셀 항목 - [pattern, x, y, scale, rotation, mirror, opacity, blendType]. */
export type MvAnimationCell = [number, number, number, number, number, number, number, number]

export interface MvAnimationTiming {
  frame: number
  se: MzSoundEffect | null
  flashColor: [number, number, number, number]
  flashDuration: number
  /** 1=대상 플래시, 2=화면 플래시, 3=대상 숨김(지속시간 flashDuration*rate). 0이면 아무 효과 없음(se만). */
  flashScope: number
}

export interface MvAnimation {
  id: number
  animation1_name: string | null
  animation1_hue: number
  animation2_name: string | null
  animation2_hue: number
  /** 0=머리, 1=중앙(기본), 2=발밑, 3=화면 고정. */
  position: number
  frames: MvAnimationCell[][]
  timings: MvAnimationTiming[]
}

/** mz_animations 전체(스프라이트시트/프레임/타이밍) - 애니메이션 종류별 고정값이라 유닛 응답과 분리. */
export function getBattleAnimations(): Promise<MvAnimation[]> {
  return apiRequest('GET', '/api/battle-animations')
}
