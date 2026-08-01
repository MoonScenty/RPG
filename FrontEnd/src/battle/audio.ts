// mz_project System.json에서 임포트한 배틀 BGM/승리·패배 ME 트랙, 그리고
// mz_animations.sound_timings에 걸린 이펙트 효과음(SE)을 재생한다. PixiJS 사운드
// 라이브러리 없이 HTML5 Audio만으로 충분한 단순 재생이라 별도 의존성을 추가하지
// 않았다. 실제 파일은 mz_project/audio/에서 통째로 복사한
// FrontEnd/public/assets/audio/{bgm,me,se}/*.ogg(Vite 번들 대상 아님, 정적 서빙).
const BGM_VOLUME = 0.5
const ME_VOLUME = 0.7
// SE는 MZ 자체 볼륨(0~100)이 이미 있어서, 여기 배율은 그걸 BGM/ME와 균형 맞추는
// 전체 배율일 뿐이다.
const SE_VOLUME_SCALE = 0.6

function trackUrl(kind: 'bgm' | 'me' | 'se', name: string): string {
  return `/assets/audio/${kind}/${name}.ogg`
}

let bgm: HTMLAudioElement | null = null

/** 전투 시작 시 루프 재생. 브라우저 자동재생 정책으로 막힐 수 있어 실패는 조용히 무시한다. */
export function playBattleBgm(name: string | null): void {
  stopBattleBgm()
  if (!name) return

  bgm = new Audio(trackUrl('bgm', name))
  bgm.loop = true
  bgm.volume = BGM_VOLUME
  void bgm.play().catch(() => {})
}

export function stopBattleBgm(): void {
  if (!bgm) return
  bgm.pause()
  bgm.currentTime = 0
  bgm = null
}

/** 승리/패배 확정 시 한 번만 재생(BGM은 이미 stopBattleBgm()으로 멈춘 뒤 호출). */
export function playResultMe(name: string | null): void {
  if (!name) return
  const me = new Audio(trackUrl('me', name))
  me.volume = ME_VOLUME
  void me.play().catch(() => {})
}

/**
 * 이펙트 효과음 1회 재생(mz_animations.sound_timings). MZ의 pan(좌우 스테레오)은
 * HTMLAudioElement 단독으론 못 주고 Web Audio API 그래프가 따로 필요해서, 이번
 * 스코프에선 뺐다(대부분 히트 이펙트는 pan=0이라 실효과는 작음) - volume/pitch만 반영.
 */
export function playSe(se: { name: string; pitch: number; volume: number }): void {
  if (!se.name) return
  const audio = new Audio(trackUrl('se', se.name))
  audio.volume = Math.min(1, Math.max(0, (se.volume / 100) * SE_VOLUME_SCALE))
  // HTMLMediaElement.playbackRate 유효 범위 밖으로 나가면 재생이 아예 실패할 수 있어 clamp.
  audio.playbackRate = Math.min(4, Math.max(0.25, se.pitch / 100))
  void audio.play().catch(() => {})
}
