(function () {
  const FPS = 60;
  // mz_project/js/rmmz_sprites.js Sprite_AnimationMV 기준 고정값 - 애니메이션
  // 프레임 하나 = 게임 틱 4개(60fps 기준 15fps), 셀은 192x192, 시트는 5열.
  const RATE = 4;
  const CELL_SIZE = 192;
  const CELLS_PER_ROW = 5;
  const MAX_CELL_SPRITES = 16;
  const BLEND_MODE_BY_CODE = { 0: "source-over", 1: "lighter", 2: "multiply", 3: "screen" };

  const canvas = document.getElementById("canvas");
  const flashDiv = document.getElementById("flash");
  const statusDiv = document.getElementById("status");
  const ctx = canvas.getContext("2d");

  let sheet1 = null;
  let sheet2 = null;
  let currentAnimation = null;
  let playing = false;
  let frameIndex = 0;
  let frameAccumulator = 0;
  let lastTime = 0;
  let firedTimingFrames = new Set();
  let flashColor = [0, 0, 0, 0];
  let flashDuration = 0;

  function setStatus(text) {
    statusDiv.textContent = text;
  }

  function resizeCanvas() {
    canvas.width = Math.max(1, canvas.clientWidth);
    canvas.height = Math.max(1, canvas.clientHeight);
  }

  function loadImage(url) {
    return new Promise((resolve, reject) => {
      if (!url) {
        resolve(null);
        return;
      }
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error("이미지 로드 실패: " + url));
      img.src = url;
    });
  }

  function playSe(se) {
    if (!se || !se.name) return;
    const audio = new Audio("/audio/se/" + encodeURIComponent(se.name) + ".ogg");
    audio.volume = Math.min(1, Math.max(0, (se.volume ?? 90) / 100));
    // HTMLMediaElement.playbackRate 유효 범위 밖으로 나가면 재생이 아예 실패할 수 있어 clamp.
    audio.playbackRate = Math.min(4, Math.max(0.25, (se.pitch ?? 100) / 100));
    audio.play().catch(() => {});
  }

  /** frameIdx에 걸린 타이밍을 전부 처리 - 한 프레임에 여러 개(SE+플래시 등) 있을 수 있다. */
  function processTimings(frameIdx) {
    if (!currentAnimation) return;
    for (const timing of currentAnimation.timings || []) {
      if (timing.frame !== frameIdx) continue;
      if (timing.se) playSe(timing.se);
      // flashScope: 1=대상 플래시, 2=화면 플래시 - 미리보기엔 "대상"이 없어서
      // 둘 다 화면 전체 플래시로 보여준다(3=대상 숨김은 미리보기에서 의미 없어 무시).
      if (timing.flashScope === 1 || timing.flashScope === 2) {
        flashColor = timing.flashColor.slice();
        flashDuration = timing.flashDuration * RATE;
      }
    }
  }

  /** mz_project Sprite_Animation.updateFlash와 동일하게 매 틱 곱셈 감쇠. */
  function updateFlash() {
    if (flashDuration > 0) {
      const d = flashDuration--;
      flashColor[3] *= (d - 1) / d;
      const [r, g, b, a] = flashColor;
      flashDiv.style.background = `rgba(${r},${g},${b},${(a / 255).toFixed(3)})`;
    } else {
      flashDiv.style.background = "transparent";
    }
  }

  async function playAnimation(data) {
    playing = false;
    currentAnimation = data;
    frameIndex = 0;
    frameAccumulator = 0;
    firedTimingFrames = new Set();
    flashColor = [0, 0, 0, 0];
    flashDuration = 0;
    flashDiv.style.background = "transparent";
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    setStatus("스프라이트시트 로딩 중...");
    try {
      [sheet1, sheet2] = await Promise.all([loadImage(data.animation1Url), loadImage(data.animation2Url)]);
    } catch (err) {
      setStatus(String(err.message || err));
      return;
    }
    if (currentAnimation !== data) return; // 로딩 중 다른 애니메이션이 선택됨

    // frame 0 타이밍(SE/플래시)은 재생 시작과 동시에 바로 발동해야 자연스럽다 -
    // 렌더 루프의 첫 틱 누적을 기다리면 최대 1틱(약 16ms)만큼 밀린다.
    firedTimingFrames.add(0);
    processTimings(0);

    playing = true;
    lastTime = performance.now();
    setStatus("재생 중: " + (data.name || ""));
  }

  function stopAnimation() {
    playing = false;
    currentAnimation = null;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    flashDiv.style.background = "transparent";
    setStatus("정지됨");
  }

  /** Sprite_AnimationMV.prototype.updateCellSprite를 Canvas2D drawImage로 이식. */
  function drawFrame(cells) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!cells) return;

    const originX = canvas.width / 2;
    const originY = canvas.height / 2;

    for (let i = 0; i < Math.min(cells.length, MAX_CELL_SPRITES); i++) {
      const cell = cells[i];
      if (!cell || cell[0] < 0) continue;

      const [pattern, x, y, scale, rotation, mirror, opacity, blendType] = cell;
      const sheet = pattern < 100 ? sheet1 : sheet2;
      if (!sheet) continue;

      const p = pattern % 100;
      const sx = (p % CELLS_PER_ROW) * CELL_SIZE;
      const sy = Math.floor(p / CELLS_PER_ROW) * CELL_SIZE;

      ctx.save();
      ctx.globalAlpha = Math.max(0, Math.min(1, opacity / 255));
      ctx.globalCompositeOperation = BLEND_MODE_BY_CODE[blendType] || "source-over";
      ctx.translate(originX + x, originY + y);
      ctx.rotate((rotation * Math.PI) / 180);
      ctx.scale((scale / 100) * (mirror ? -1 : 1), scale / 100);
      ctx.drawImage(sheet, sx, sy, CELL_SIZE, CELL_SIZE, -CELL_SIZE / 2, -CELL_SIZE / 2, CELL_SIZE, CELL_SIZE);
      ctx.restore();
    }
  }

  function checkEnd() {
    if (currentAnimation && frameIndex >= currentAnimation.frames.length) {
      playing = false;
      setStatus("재생 완료");
    }
  }

  function render(time) {
    requestAnimationFrame(render);
    if (!playing || !currentAnimation) return;

    const delta = time - lastTime;
    lastTime = time;
    frameAccumulator += (delta * FPS) / 1000;

    while (frameAccumulator >= RATE && playing) {
      frameAccumulator -= RATE;
      if (frameIndex < currentAnimation.frames.length) {
        if (!firedTimingFrames.has(frameIndex)) {
          firedTimingFrames.add(frameIndex);
          processTimings(frameIndex);
        }
        frameIndex++;
      }
      checkEnd();
    }
    updateFlash();

    if (playing && currentAnimation) {
      const idx = Math.min(frameIndex, currentAnimation.frames.length - 1);
      drawFrame(currentAnimation.frames[idx]);
    }
  }

  window.addEventListener("resize", resizeCanvas);

  window.chrome?.webview?.addEventListener("message", (event) => {
    const msg = event.data;
    if (!msg || !msg.type) return;
    if (msg.type === "play") {
      void playAnimation(msg);
    } else if (msg.type === "stop") {
      stopAnimation();
    }
  });

  window.addEventListener("DOMContentLoaded", () => {
    resizeCanvas();
    setStatus("준비됨");
    requestAnimationFrame(render);
  });
})();
