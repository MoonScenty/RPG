// /battle로 실제 들어가려는 의도가 있었는지 표시하는 순수 인메모리 플래그.
// 라우터 쿼리나 localStorage처럼 새로고침에서 살아남는 곳에 두면 안 된다 -
// 그러면 새로고침해도 계속 자동으로 전투에 진입해버려서(진행 중이던 전투를
// BGM 자동재생 정책 위반 상태로 재개하려는 시도까지 포함) 원래 문제가 그대로
// 재현된다. 홈 화면의 "이어서 전투"/"시험전투" 버튼을 실제로 눌렀을 때만
// true로 세팅하고, 라우터 가드가 한 번 소비하면서 바로 리셋한다.
let intentional = false

export function markBattleEntryIntentional(): void {
  intentional = true
}

/** 확인과 동시에 리셋 - 같은 URL을 다시 새로고침하면 또 막혀야 하므로. */
export function consumeBattleEntryIntent(): boolean {
  const was = intentional
  intentional = false
  return was
}
