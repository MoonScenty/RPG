(function () {
  const player = document.getElementById("player");
  const statusDiv = document.getElementById("status");

  let audioCtx = null;
  let sourceNode = null;
  let pannerNode = null;
  let gainNode = null;

  function setStatus(text) {
    statusDiv.textContent = text;
    window.chrome?.webview?.postMessage({ type: "status", text });
  }

  function ensureGraph() {
    if (audioCtx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    sourceNode = audioCtx.createMediaElementSource(player);
    pannerNode = audioCtx.createStereoPanner();
    gainNode = audioCtx.createGain();
    sourceNode.connect(pannerNode).connect(gainNode).connect(audioCtx.destination);
  }

  function play(data) {
    try {
      ensureGraph();
    } catch (e) {
      // 이미 연결된 경우 등 무시하고 진행
    }

    player.pause();
    player.src = data.url;
    player.playbackRate = (data.pitch || 100) / 100;

    if (pannerNode) pannerNode.pan.value = Math.max(-1, Math.min(1, (data.pan || 0) / 100));
    if (gainNode) gainNode.gain.value = Math.min(1, (data.volume ?? 90) / 100);
    else player.volume = Math.min(1, (data.volume ?? 90) / 100);

    setStatus("재생 중: " + (data.name || ""));
    player.play().catch((err) => {
      if (err.name === "AbortError") return; // 뒤이은 재생 요청에 의해 대체됨 (정상)
      setStatus("재생 실패: " + err.message);
    });
  }

  function stop() {
    player.pause();
    player.currentTime = 0;
    setStatus("정지됨");
  }

  player.addEventListener("ended", () => setStatus("재생 완료"));

  window.chrome?.webview?.addEventListener("message", (event) => {
    const msg = event.data;
    if (!msg || !msg.type) return;
    if (msg.type === "play") play(msg);
    else if (msg.type === "stop") stop();
  });
})();
