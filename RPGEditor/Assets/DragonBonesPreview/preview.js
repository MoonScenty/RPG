(function () {
  const canvas = document.getElementById("canvas");
  const statusDiv = document.getElementById("status");

  window.PIXI = PIXI;

  let app = null;
  let factory = null;
  let armatureDisplay = null;
  let loadToken = 0;

  function setStatus(text) {
    statusDiv.textContent = text;
  }

  function resizeCanvas() {
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(canvas.clientWidth * dpr));
    canvas.height = Math.max(1, Math.floor(canvas.clientHeight * dpr));
    if (app) app.renderer.resize(canvas.width, canvas.height);
  }

  function init() {
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    app = new PIXI.Application({
      view: canvas,
      width: canvas.width,
      height: canvas.height,
      backgroundAlpha: 0,
      antialias: true,
    });

    factory = dragonBones.PixiFactory.factory;
    setStatus("준비됨");
  }

  function clearArmature() {
    if (armatureDisplay) {
      app.stage.removeChild(armatureDisplay);
      armatureDisplay.dispose();
      armatureDisplay = null;
    }
  }

  function loadArmature(data) {
    clearArmature();
    const token = ++loadToken;
    setStatus("로딩 중: " + data.armatureName);

    Promise.all([
      fetch(data.skeUrl).then((r) => r.json()),
      fetch(data.texJsonUrl).then((r) => r.json()),
    ])
      .then(([skeJson, texJson]) => {
        if (token !== loadToken) return;

        const baseTexture = PIXI.BaseTexture.from(data.texPngUrl);
        const onLoaded = () => {
          if (token !== loadToken) return;
          try {
            factory.parseDragonBonesData(skeJson);
            factory.parseTextureAtlasData(texJson, baseTexture);
            armatureDisplay = factory.buildArmatureDisplay(data.armatureName);
            if (!armatureDisplay) {
              setStatus("아마추어를 찾을 수 없습니다: " + data.armatureName);
              return;
            }

            armatureDisplay.x = canvas.width / 2;
            armatureDisplay.y = canvas.height * 0.75;
            const rawHeight = armatureDisplay.height || 0;
            const scale = rawHeight > 0 ? Math.min(2, (canvas.height * 0.6) / rawHeight) : 1;
            armatureDisplay.scale.set(isFinite(scale) && scale > 0 ? scale : 1);
            app.stage.addChild(armatureDisplay);

            const names = armatureDisplay.animation.animationNames.slice();
            window.chrome?.webview?.postMessage({ type: "animations", names });

            const first = names[0];
            if (first) armatureDisplay.animation.play(first);
            setStatus("재생 중: " + (first || ""));
          } catch (err) {
            setStatus("로드 오류: " + err.message);
          }
        };

        if (baseTexture.valid) onLoaded();
        else baseTexture.on("loaded", onLoaded);
      })
      .catch((err) => setStatus("로드 실패: " + err.message));
  }

  function playAnimation(name) {
    if (armatureDisplay && name) {
      armatureDisplay.animation.play(name);
      setStatus("재생 중: " + name);
    }
  }

  window.chrome?.webview?.addEventListener("message", (event) => {
    const msg = event.data;
    if (!msg || !msg.type) return;
    if (msg.type === "load") loadArmature(msg);
    else if (msg.type === "play") playAnimation(msg.name);
    else if (msg.type === "stop") {
      clearArmature();
      setStatus("정지됨");
    }
  });

  window.addEventListener("DOMContentLoaded", init);
})();
