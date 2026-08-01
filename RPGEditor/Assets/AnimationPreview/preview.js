(function () {
  const FPS = 60;
  const VIEWPORT_SIZE = 4096;

  const canvas = document.getElementById("canvas");
  const flashDiv = document.getElementById("flash");
  const statusDiv = document.getElementById("status");

  let gl = null;
  let glVersion = "";
  let context = null;
  let handle = null;
  let audioCtx = null;

  let currentAnimation = null;
  let frameIndex = 0;
  let frameAccumulator = 0;
  let lastTime = 0;
  let flashColor = [0, 0, 0, 0];
  let flashDuration = 0;
  let maxTimingFrame = 0;

  function setStatus(text) {
    // 진단용 - 사용자 화면에서 webgl2/webgl 중 뭐가 잡혔는지 바로 보이게(개발자
    // 도구를 열 수 없는 배포 빌드에서도 확인 가능하도록).
    statusDiv.textContent = glVersion ? `${text} [${glVersion}]` : text;
  }

  // FrontEnd/src/battle/effekseer.ts(EffekseerOverlay)와 좌표계를 맞추기 위해
  // 디바이스 픽셀 배율은 일부러 적용하지 않는다(CSS 픽셀 그대로) - HiDPI 화면에서
  // VIEWPORT_SIZE(고정 4096px) 기준 투영행렬과 실제 캔버스 해상도가 어긋나면
  // 이펙트가 실제 게임보다 작게/이상하게 보인다(사용자 확인).
  function resizeCanvas() {
    canvas.width = Math.max(1, canvas.clientWidth);
    canvas.height = Math.max(1, canvas.clientHeight);
  }

  function init() {
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    // premultipliedAlpha:false가 빠지면 파티클 텍스처의 투명 영역이 검은 박스로
    // 나온다(straight-alpha 텍스처를 premultiplied 블렌딩으로 합성할 때 생기는
    // 전형적인 증상) - Effekseer가 straight alpha를 전제하므로 반드시 꺼야 한다.
    //
    // stencil:true는 필수다 - mz_project/js/libs/pixi.js의 Renderer가 실제로 만드는
    // WebGL 컨텍스트는 항상 stencil:true로 생성되고, effekseer.min.js 안에는
    // _emscripten_glStencilFunc 등 스텐실 버퍼를 실제로 쓰는 WASM 코드가 들어있다.
    // 이 옵션이 빠지면 스텐실 마스킹/클리핑을 쓰는 일부 파티클만 골라 깨진다.
    const contextOptions = { alpha: true, premultipliedAlpha: false, stencil: true };
    gl = canvas.getContext("webgl2", contextOptions);
    glVersion = gl ? "webgl2" : "webgl";
    gl ??= canvas.getContext("webgl", contextOptions);

    if (!gl) {
      setStatus("WebGL을 사용할 수 없습니다.");
      return;
    }

    // Effekseer 런타임은 매 파티클마다 blendFunc/blendEquation만 바꿔가며 그리고,
    // gl.BLEND 자체를 켜는 건 호스트 책임으로 가정한다 - 이게 꺼져 있으면 블렌드
    // 모드(더하기 등)와 무관하게 텍스처가 통째로 불투명하게(검은 배경 포함) 그려진다.
    gl.enable(gl.BLEND);

    effekseer.initRuntime(
      "effekseer.wasm",
      () => {
        context = effekseer.createContext();
        context.init(gl);
        // setRestorationOfStatesFlag(false)로 끄면 beginDraw/endDraw가 GL 상태를
        // save/restore하지 않는다(effekseer.min.js 내부 확인: flag=false일 때
        // endDraw()가 contextStates.restore()를 아예 건너뜀) - 이펙트 draw가 남긴
        // blend/texture 상태가 다음 draw로 새어나가 검은 얼룩으로 보이는 원인이었다.
        // 기본값(true)을 그대로 둔다.
        setStatus("준비됨");
        requestAnimationFrame(render);
      },
      () => setStatus("Effekseer 런타임 로드 실패")
    );
  }

  function stopEffect() {
    if (handle) {
      handle.stop();
      handle = null;
    }
    flashColor = [0, 0, 0, 0];
    flashDuration = 0;
    flashDiv.style.background = "transparent";
  }

  function playAnimation(data) {
    stopEffect();
    if (context && context._lastEffect) {
      context.releaseEffect(context._lastEffect);
      context._lastEffect = null;
    }

    currentAnimation = data;
    frameIndex = 0;
    frameAccumulator = 0;
    maxTimingFrame = 0;
    for (const t of (data.soundTimings || []).concat(data.flashTimings || [])) {
      if (t.frame > maxTimingFrame) maxTimingFrame = t.frame;
    }

    if (!data.effectUrl) return;

    setStatus("이펙트 로딩 중...");
    const effect = context.loadEffect(
      data.effectUrl,
      1.0,
      () => {
        context._lastEffect = effect;
        handle = context.play(effect, 0, 0, 0);
        const r = Math.PI / 180;
        const rot = data.rotation || { x: 0, y: 0, z: 0 };
        handle.setRotation(rot.x * r, rot.y * r, rot.z * r);
        const scale = (data.scale || 100) / 100;
        handle.setScale(scale, scale, scale);
        handle.setSpeed((data.speed || 100) / 100);
        setStatus("재생 중: " + (data.name || ""));
      },
      () => setStatus("이펙트 로드 실패: " + data.effectUrl)
    );
  }

  function processTimings() {
    if (!currentAnimation) return;
    for (const t of currentAnimation.soundTimings || []) {
      if (t.frame === frameIndex && t.se && t.se.name) {
        playSe(t.se);
      }
    }
    for (const t of currentAnimation.flashTimings || []) {
      if (t.frame === frameIndex) {
        flashColor = t.color.slice();
        flashDuration = t.duration;
      }
    }
  }

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

  function playSe(se) {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const url = "/audio/se/" + encodeURIComponent(se.name) + ".ogg";
    const audio = new Audio(url);
    audio.playbackRate = (se.pitch || 100) / 100;
    try {
      const source = audioCtx.createMediaElementSource(audio);
      const panner = audioCtx.createStereoPanner();
      panner.pan.value = Math.max(-1, Math.min(1, (se.pan || 0) / 100));
      const gainNode = audioCtx.createGain();
      gainNode.gain.value = (se.volume || 90) / 100;
      source.connect(panner).connect(gainNode).connect(audioCtx.destination);
    } catch (e) {
      audio.volume = Math.min(1, (se.volume || 90) / 100);
    }
    audio.play().catch(() => {});
  }

  function checkEnd() {
    if (
      currentAnimation &&
      frameIndex > maxTimingFrame &&
      flashDuration === 0 &&
      !(handle && handle.exists)
    ) {
      currentAnimation = null;
      setStatus("재생 완료");
    }
  }

  function setMatrices() {
    const p = -(VIEWPORT_SIZE / canvas.height);
    context.setProjectionMatrix([1, 0, 0, 0, 0, -1, 0, 0, 0, 0, 1, p, 0, 0, 0, 1]);
    context.setCameraMatrix([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, -10, 1]);
  }

  function render(time) {
    requestAnimationFrame(render);
    if (!gl || !context) return;

    const delta = lastTime ? time - lastTime : 1000 / FPS;
    lastTime = time;
    frameAccumulator += (delta * FPS) / 1000;
    while (frameAccumulator >= 1) {
      frameAccumulator -= 1;
      if (currentAnimation) {
        processTimings();
        frameIndex++;
        checkEnd();
      }
      updateFlash();
      context.update();
    }

    gl.viewport(0, 0, canvas.width, canvas.height);
    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

    if (handle && handle.exists) {
      const offsetX = (currentAnimation && currentAnimation.offsetX) || 0;
      const offsetY = (currentAnimation && currentAnimation.offsetY) || 0;
      const vw = VIEWPORT_SIZE;
      const vh = VIEWPORT_SIZE;
      const vx = canvas.width / 2 - vw / 2 + offsetX;
      const vy = canvas.height / 2 - vh / 2 - offsetY;
      gl.viewport(vx, vy, vw, vh);
      setMatrices();
      context.beginDraw();
      context.drawHandle(handle);
      context.endDraw();
      gl.viewport(0, 0, canvas.width, canvas.height);
    }
  }

  window.chrome?.webview?.addEventListener("message", (event) => {
    const msg = event.data;
    if (!msg || !msg.type) return;
    if (msg.type === "play") {
      playAnimation(msg);
    } else if (msg.type === "stop") {
      currentAnimation = null;
      stopEffect();
      setStatus("정지됨");
    }
  });

  window.addEventListener("DOMContentLoaded", init);
})();
