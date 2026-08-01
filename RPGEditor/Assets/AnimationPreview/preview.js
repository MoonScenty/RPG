(function () {
  const FPS = 60;
  const VIEWPORT_SIZE = 4096;

  const canvas = document.getElementById("canvas");
  const flashDiv = document.getElementById("flash");
  const statusDiv = document.getElementById("status");

  let gl = null;
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
    statusDiv.textContent = text;
  }

  function resizeCanvas() {
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(canvas.clientWidth * dpr));
    canvas.height = Math.max(1, Math.floor(canvas.clientHeight * dpr));
  }

  function init() {
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    gl = canvas.getContext("webgl", {
      alpha: true,
      antialias: true,
      premultipliedAlpha: false,
      depth: true,
      stencil: false,
    });

    if (!gl) {
      setStatus("WebGL을 사용할 수 없습니다.");
      return;
    }

    effekseer.initRuntime(
      "effekseer.wasm",
      () => {
        context = effekseer.createContext();
        context.init(gl);
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
    const url = "https://audio.local/se/" + encodeURIComponent(se.name) + ".ogg";
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
