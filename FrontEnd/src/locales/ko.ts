// 사이트에서 쓰는 사용자 노출 문자열을 한곳에 모아둔 파일 - 다국어(language)
// 기능을 만들 때 이 구조를 그대로 vue-i18n 등의 로케일 메시지로 옮기면 된다.
// 지금은 한국어 전용이라 스위칭 로직 없이 이 객체를 직접 참조만 한다.
// 값에 인자가 들어가는 문자열(가격/개수 등 삽입)은 함수로 뒀다.

const ko = {
  common: {
    loading: '불러오는 중...',
    requestFailed: '요청에 실패했습니다.',
  },

  auth: {
    login: {
      emailLabel: '이메일',
      passwordLabel: '비밀번호',
      submit: '로그인',
      submitting: '로그인 중...',
      switchToRegister: '용병단 창설',
      loginFailed: '로그인에 실패했습니다.',
    },
    register: {
      title: '용병단 창설',
      nameLabel: '용병단 이름',
      emailLabel: '이메일',
      passwordLabel: '비밀번호',
      passwordConfirmLabel: '비밀번호 재입력',
      submit: '용병단 창설',
      submitting: '창설 중...',
      switchToLogin: '이미 계정이 있으신가요? 로그인',
      passwordMismatch: '비밀번호가 일치하지 않습니다.',
      registerFailed: '용병단 창설에 실패했습니다.',
    },
  },

  home: {
    logout: '로그아웃',
    currencies: {
      honor: '명예점수',
      gold: '골드',
      fatigue: '피로도',
    },
    topIcons: {
      notice: '공지사항',
      message: '메시지',
      settings: '설정',
    },
    toasts: {
      itemPreparing: (label: string) => `${label} 준비 중입니다`,
      noticePreparing: '공지사항 준비 중입니다',
      messagePreparing: '메시지 기능 준비 중입니다',
      formationRequired: '용병을 진형에 배치해야 모험을 시작할 수 있습니다',
    },
    menu: {
      auctionHouse: '경매장',
      friends: '친구',
      inventory: '인벤토리',
      bakery: '제빵소',
      lab: '연구소',
      blacksmith: '대장간',
      mercenary: '용병소',
    },
    quest: {
      title: '일일 임무',
      empty: '진행 가능한 퀘스트가 없습니다',
    },
    adventureButton: '모험 시작',
    continueBattle: {
      message: '진행 중인 전투가 있습니다.',
      button: '이어서 전투',
    },
    adventure: {
      trialBattle: '시험전투',
      dungeon: '던전',
      duel: '결투',
      subjugation: '토벌전',
    },
  },

  mercenary: {
    tabs: {
      buy: '구매',
      formation: '진형',
      gambit: '행동',
      equipment: '장비',
    },

    purchase: {
      loadFailed: '용병 목록을 불러오지 못했습니다.',
      buyFailed: '구매에 실패했습니다.',
      loading: '불러오는 중...',
      buying: '구매 중...',
      free: '무료로 영입',
      buyFor: (price: string) => `${price} 골드로 영입`,
      owned: '보유중',
    },

    formation: {
      loadFailed: '진형 정보를 불러오지 못했습니다.',
      confirmLeave: '저장하지 않은 변경사항이 있습니다. 이동하면 사라집니다. 계속할까요?',
      maxPlaced: (max: number) => `진형에는 최대 ${max}명까지만 배치할 수 있습니다.`,
      saveFailed: '저장에 실패했습니다.',
      applyFailed: '적용에 실패했습니다.',
      preset: (n: number) => `프리셋 ${n}`,
      usingPreset: '사용중인 프리셋',
      usePreset: '이 프리셋 사용하기',
      saving: '저장 중...',
      save: '저장',
      placedCount: (count: number, max: number) => `${count}/${max}명 배치됨`,
      front: '전방',
      back: '후방',
      emptySlot: '빈 슬롯',
      unplacedLabel: '미배치 용병',
      noUnplaced: '미배치 용병이 없습니다.',
      loading: '불러오는 중...',
    },

    gambit: {
      confirmLeave: '저장하지 않은 변경사항이 있습니다. 이동하면 사라집니다. 계속할까요?',
      loadFailed: '행동 규칙을 불러오지 못했습니다.',
      noRoster: '보유한 용병이 없습니다. 먼저 구매 탭에서 용병을 영입해주세요.',
      preset: (n: number) => `프리셋 ${n}`,
      usingPreset: '사용중인 프리셋',
      usePreset: '이 프리셋 사용하기',
      addRule: '+ 규칙 추가',
      saving: '저장 중...',
      save: '저장',
      saveFailed: '저장에 실패했습니다.',
      applyFailed: '적용에 실패했습니다.',
      moveUp: '▲',
      moveDown: '▼',
      delete: '삭제',
      pinnedRule: '항상 → 공격한다 (자동 추가, 수정/삭제 불가)',
      skillTarget: (side: string) => `대상: ${side}`,
      ally: '아군',
      enemy: '적군',
      self: '자신',

      conditions: {
        always: '항상',
        firstAction: '처음 행동시',
        selfPositionFront: '자신이 전방에 있을때',
        selfPositionBack: '자신이 후방에 있을때',
        selfMpPctGte: '자신의 MP가 n% 이상일때',
        selfMpPctLte: '자신의 MP가 n% 이하일때',
        selfMpFlatGte: '자신의 MP가 n 이상일때',
        selfMpFlatLte: '자신의 MP가 n 이하일때',
        selfHpPctGte: '자신의 HP가 n% 이상일때',
        selfHpPctLte: '자신의 HP가 n% 이하일때',
        selfHpFlatGte: '자신의 HP가 n 이상일때',
        selfHpFlatLte: '자신의 HP가 n 이하일때',
        allyMpPctGte: '아군의 MP가 n% 이상일때',
        allyMpPctLte: '아군의 MP가 n% 이하일때',
        allyMpFlatGte: '아군의 MP가 n 이상일때',
        allyMpFlatLte: '아군의 MP가 n 이하일때',
        allyHpPctGte: '아군의 HP가 n% 이상일때',
        allyHpPctLte: '아군의 HP가 n% 이하일때',
        allyHpFlatGte: '아군의 HP가 n 이상일때',
        allyHpFlatLte: '아군의 HP가 n 이하일때',
        selfDebuff: '자신에게 <디버프>가 걸렸을때',
        allyDebuff: '아군에게 <디버프>가 걸렸을때',
        enemyDebuff: '적에게 <디버프>가 걸렸을때',
        allyDead: '아군이 죽어 있을때',
      },

      actions: {
        attack: '공격한다 (대상: 적군)',
        skill: '스킬을 사용한다',
        item: '아이템을 사용한다',
        cancel: '캐스팅을 취소한다',
      },
    },

    equipment: {
      loadFailed: '장비 정보를 불러오지 못했습니다.',
      updateFailed: '장비 변경에 실패했습니다.',
      noRoster: '보유한 용병이 없습니다. 먼저 구매 탭에서 용병을 영입해주세요.',
      loading: '불러오는 중...',
      slots: {
        weapon: '무기',
        shield: '방패',
        body_armor: '몸',
        accessory: '장신구',
      },
      empty: '장착 안 함',
      unequip: '해제',
      noOptions: '장착할 수 있는 장비가 없습니다.',
      owned: (n: number) => `보유 ${n}개`,
    },
  },

  crafting: {
    loadFailed: '정보를 불러오지 못했습니다.',
    startFailed: '제작을 시작하지 못했습니다.',
    collectFailed: '수령에 실패했습니다.',
    loading: '불러오는 중...',
    starting: '시작 중...',
    collecting: '수령 중...',
    slotsTitle: (used: number, cap: number) => `제작 슬롯 (${used}/${cap})`,
    slotsFull: '제작 슬롯이 가득 찼습니다.',
    emptySlots: '진행 중인 제작이 없습니다.',
    recipesTitle: '제작 가능 목록',
    noRecipes: '아직 등록된 조합식이 없습니다.',
    materialType: {
      item: '아이템',
      weapon: '무기',
      armor: '방어구',
    },
    goldCost: (gold: string) => `${gold} 골드`,
    seconds: (s: number) => `${s}초`,
    remaining: (duration: string) => `${duration} 남음`,
    start: '제작 시작',
    collect: '수령하기',
    ready: '완료!',

    inventory: {
      loadFailed: '인벤토리를 불러오지 못했습니다.',
      loading: '불러오는 중...',
      empty: '보유한 항목이 없습니다.',
      quantity: (n: number) => `x${n}`,
    },
  },
} as const

export type KoStrings = typeof ko
export default ko
