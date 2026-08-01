import { Assets, Rectangle, Texture } from 'pixi.js'

export type CharacterKey =
  | 'Actor1_1'
  | 'Actor1_2'
  | 'Actor1_3'
  | 'Actor1_4'
  | 'Actor1_5'
  | 'Actor1_6'
  | 'Actor1_7'
  | 'Actor1_8'
  | 'Actor2_1'
  | 'Actor2_2'
  | 'Actor2_3'
  | 'Actor2_4'
  | 'Actor2_5'
  | 'Actor2_6'
  | 'Actor2_7'
  | 'Actor2_8'
  | 'Goblin_Normal'

export type AnimName = string

// Unit.sprite (백엔드) -> mz_project 원본 배틀러 시트명. 용병 16명은
// mz_project/img/sv_actors/Actor{1,2}_{1-8}.png, 시험전투 고정 적은
// img/sv_enemies/Goblin_Normal.png(둘 다 같은 9x6 SV 배틀러 그리드 포맷).
const SPRITE_TO_CHARACTER: Record<string, CharacterKey> = {
  'Actor1:0': 'Actor1_1',
  'Actor1:1': 'Actor1_2',
  'Actor1:2': 'Actor1_3',
  'Actor1:3': 'Actor1_4',
  'Actor1:4': 'Actor1_5',
  'Actor1:5': 'Actor1_6',
  'Actor1:6': 'Actor1_7',
  'Actor1:7': 'Actor1_8',
  'Actor2:0': 'Actor2_1',
  'Actor2:1': 'Actor2_2',
  'Actor2:2': 'Actor2_3',
  'Actor2:3': 'Actor2_4',
  'Actor2:4': 'Actor2_5',
  'Actor2:5': 'Actor2_6',
  'Actor2:6': 'Actor2_7',
  'Actor2:7': 'Actor2_8',
  'Enemy:Goblin_Normal': 'Goblin_Normal',
}

export function characterKeyForSprite(sprite: string): CharacterKey | undefined {
  return SPRITE_TO_CHARACTER[sprite]
}

// 모든 캐릭터가 같은 mz 표준 포맷이라 이름이 캐릭터마다 갈라질 일이 없지만,
// BattlerSprite가 기대하는 후보-목록 인터페이스는 그대로 유지(단일 원소).
// 기본 공격 모션은 thrust(System.json attackMotions 전부 type 0으로 통일).
export const ATTACK_ANIM_CANDIDATES = ['thrust']
export const FRONTSTEP_ANIM_CANDIDATES = ['frontstep']
export const DEATH_ANIM_CANDIDATES = ['dead']

// mz_project 원본 SV 배틀러 시트 - 캐릭터당 파일 1장에 9열x6행 그리드로 18개
// 모션 x 3프레임이 들어있는 RPG Maker 표준 포맷(rmmz_sprites.js의
// Sprite_Actor.MOTIONS와 동일한 모션 순서). sv_actors/sv_enemies 둘 다 동일.
const SV_MOTION_INDEX: Record<string, number> = {
  walk: 0,
  wait: 1,
  chant: 2,
  guard: 3,
  damage: 4,
  evade: 5,
  thrust: 6,
  swing: 7,
  missile: 8,
  skill: 9,
  spell: 10,
  item: 11,
  escape: 12,
  victory: 13,
  dying: 14,
  abnormal: 15,
  sleep: 16,
  dead: 17,
}
const SV_FRAMES_PER_MOTION = 3

const urlModules = import.meta.glob('../assets/battlers/sv_actors/*.png', {
  eager: true,
  import: 'default',
}) as Record<string, string>

const urlByCharacter: Partial<Record<CharacterKey, string>> = {}

for (const [path, url] of Object.entries(urlModules)) {
  const match = /sv_actors\/([^/]+)\.png$/.exec(path)
  const key = match?.[1]
  if (key) urlByCharacter[key as CharacterKey] = url
}

// DragonBones 유닛용 정지 이미지(사용자가 직접 그려서 추가) - sv 시트처럼 9x6
// 모션 그리드가 아니라 파일 하나가 그대로 완성된 한 장. 턴 오더 아이콘에서
// <DragonBonesData> 노트태그 값(스켈레톤 이름)으로 조회한다 - getStaticBattlerTexture 참고.
const staticBattlerModules = import.meta.glob('../assets/battlers/static/*.png', {
  eager: true,
  import: 'default',
}) as Record<string, string>

const staticBattlerUrlByName: Record<string, string> = {}

for (const [path, url] of Object.entries(staticBattlerModules)) {
  const match = /static\/([^/]+)\.png$/.exec(path)
  const key = match?.[1]
  if (key) staticBattlerUrlByName[key] = url
}

const staticBattlerTextureByName: Record<string, Texture> = {}

/** DragonBones 스켈레톤 이름(노트태그 원문 값)으로 정적 배틀러 이미지를 찾는다 - 아직 안 추가됐으면 undefined. */
export function getStaticBattlerTexture(skeletonName: string): Texture | undefined {
  return staticBattlerTextureByName[skeletonName]
}

type FrameMap = Partial<Record<AnimName, Texture[]>>

const frameCache: Partial<Record<CharacterKey, FrameMap>> = {}

export function getCharacterFrames(key: string): FrameMap | undefined {
  return frameCache[key as CharacterKey]
}

export async function preloadCharacterAnimations(): Promise<void> {
  await Assets.load([...Object.values(urlByCharacter), ...Object.values(staticBattlerUrlByName)])

  for (const [key, url] of Object.entries(urlByCharacter) as Array<[CharacterKey, string]>) {
    const sheet = Assets.get<Texture>(url)
    const cellWidth = sheet.source.width / 9
    const cellHeight = sheet.source.height / 6

    const frames: FrameMap = {}
    for (const [name, motionIndex] of Object.entries(SV_MOTION_INDEX)) {
      const bigCol = Math.floor(motionIndex / 6)
      const row = motionIndex % 6
      frames[name] = Array.from({ length: SV_FRAMES_PER_MOTION }, (_, pattern) => {
        const x = (bigCol * SV_FRAMES_PER_MOTION + pattern) * cellWidth
        const y = row * cellHeight

        return new Texture({ source: sheet.source, frame: new Rectangle(x, y, cellWidth, cellHeight) })
      })
    }

    // BattlerSprite/BattleScene가 쓰는 이름(idle/frontstep)에 맞춘 별칭.
    frames.idle = frames.wait
    frames.frontstep = frames.walk

    frameCache[key] = frames
  }

  for (const [name, url] of Object.entries(staticBattlerUrlByName)) {
    staticBattlerTextureByName[name] = Assets.get<Texture>(url)
  }
}
