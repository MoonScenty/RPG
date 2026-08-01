import { Assets } from 'pixi.js'
import { PixiFactory, EventObject, type PixiArmatureDisplay } from 'pixi-dragonbones-runtime'

// DragonBones 스켈레톤/텍스처 아틀라스 런타임 export 파일 위치 - mz_project/img/dragonbones/*를
// 그대로 복사해둔 것(effekseer/effects와 동일한 public 정적 에셋 컨벤션). 노트태그 값
// (<DragonBonesData: 이름>)은 이 폴더 안 파일명일 뿐, DB row를 가리키는 게 아니다.
const DRAGONBONES_BASE_URL = '/assets/dragonbones/'

const factory = PixiFactory.factory

interface LoadedRig {
  dragonBonesName: string
  armatureName: string
}

// 같은 적이 여러 마리 나와도 스켈레톤/아틀라스 파싱은 한 번만 - factory.parseXxxData는
// 내부적으로 이름 기준 캐시를 갖고 있어 반복 호출 자체는 안전하지만, fetch를 매번
// 새로 하지 않도록 우리 쪽에서도 Promise를 캐싱한다.
const loadedRigs = new Map<string, Promise<LoadedRig>>()

async function fetchJson(url: string): Promise<Record<string, unknown>> {
  const res = await fetch(url)
  if (!res.ok) {
    throw new Error(`DragonBones 데이터를 불러오지 못했습니다(${res.status}): ${url}`)
  }
  return res.json()
}

function ensureRigLoaded(skeletonName: string, atlasName: string): Promise<LoadedRig> {
  const cacheKey = `${skeletonName}|${atlasName}`
  let pending = loadedRigs.get(cacheKey)
  if (pending) return pending

  pending = (async () => {
    const [skeletonJson, atlasJson] = await Promise.all([
      fetchJson(`${DRAGONBONES_BASE_URL}${skeletonName}.json`),
      fetchJson(`${DRAGONBONES_BASE_URL}${atlasName}.json`),
    ])
    const imagePath = atlasJson.imagePath as string
    const texture = await Assets.load(`${DRAGONBONES_BASE_URL}${imagePath}`)

    const dbData = factory.parseDragonBonesData(skeletonJson)
    factory.parseTextureAtlasData(atlasJson, texture)

    const armatureName = dbData?.armatureNames?.[0]
    if (!dbData || !armatureName) {
      throw new Error(`DragonBones 스켈레톤에 armature가 없습니다: ${skeletonName}`)
    }

    return { dragonBonesName: dbData.name, armatureName }
  })()
  loadedRigs.set(cacheKey, pending)

  return pending
}

/** skeletonName/atlasName(둘 다 노트태그 원문 값)으로 armatureDisplay 인스턴스를 새로 하나 만든다. */
export async function buildArmature(skeletonName: string, atlasName: string): Promise<PixiArmatureDisplay> {
  const rig = await ensureRigLoaded(skeletonName, atlasName)
  const display = factory.buildArmatureDisplay(rig.armatureName, rig.dragonBonesName)
  if (!display) {
    throw new Error(`DragonBones armatureDisplay 생성 실패: ${rig.armatureName}`)
  }

  return display
}

export { EventObject }
export type { PixiArmatureDisplay }
