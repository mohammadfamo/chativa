/**
 * تماس صوتی زنده با WebRTC (مرحله ۵)
 * -------------------------------------------------
 * فقط در چت خصوصی (messages.php) فعال می‌شود. سیگنالینگ WebRTC از همان
 * اتصال WebSocket چت (window.ChativaRealtime) استفاده می‌کند - نیازی به
 * سرور جدا یا اتصال دوم نیست. اگر Real-Time فعال نباشد یا وصل نشود،
 * تماس صوتی هم در دسترس نخواهد بود (تماس زنده بدون WebSocket ممکن نیست).
 */
(function () {
  'use strict';

  const cfg = window.CHATIVA;
  if (!cfg || cfg.mode !== 'private') return;

  const realtime = window.ChativaRealtime;
  if (!realtime) return;

  function dialogAlert(message) {
    if (window.ChativaDialog && window.ChativaDialog.alert) {
      return window.ChativaDialog.alert(message);
    }
    alert(message);
  }

  const btnCall = document.getElementById('btn-call');
  const callBar = document.getElementById('call-bar');
  const callBarTitle = document.getElementById('call-bar-title');
  const callBarTimer = document.getElementById('call-bar-timer');
  const btnMute = document.getElementById('btn-call-mute');
  const btnDecline = document.getElementById('btn-call-decline');
  const btnAccept = document.getElementById('btn-call-accept');
  const btnHangup = document.getElementById('btn-call-hangup');
  const remoteAudioEl = document.getElementById('call-remote-audio');

  if (!btnCall || !callBar) return;

  const ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302' }];
  if (cfg.turn && cfg.turn.url) {
    ICE_SERVERS.push({
      urls: cfg.turn.url,
      username: cfg.turn.username || undefined,
      credential: cfg.turn.credential || undefined,
    });
  }

  let pc = null;
  let localStream = null;
  let state = 'idle'; // idle | calling | ringing | in-call
  let pendingOfferSdp = null;
  let iceQueue = [];
  let timerInterval = null;
  let callStartedAt = 0;
  let muted = false;

  function fmtTime(totalSeconds) {
    const m = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
    const s = Math.floor(totalSeconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  }

  function startTimer() {
    callStartedAt = Date.now();
    callBarTimer.classList.remove('hidden');
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
      callBarTimer.textContent = fmtTime((Date.now() - callStartedAt) / 1000);
    }, 1000);
  }

  function stopTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    callBarTimer.classList.add('hidden');
    callBarTimer.textContent = '00:00';
  }

  function showBar() {
    callBar.classList.remove('hidden');
  }

  function hideBar() {
    callBar.classList.add('hidden');
  }

  function setButtons({ mute, decline, accept, hangup }) {
    btnMute.classList.toggle('hidden', !mute);
    btnDecline.classList.toggle('hidden', !decline);
    btnAccept.classList.toggle('hidden', !accept);
    btnHangup.classList.toggle('hidden', !hangup);
  }

  function cleanupMedia() {
    if (pc) {
      try { pc.close(); } catch (e) { /* بی‌اهمیت */ }
      pc = null;
    }
    if (localStream) {
      localStream.getTracks().forEach((t) => t.stop());
      localStream = null;
    }
    if (remoteAudioEl) remoteAudioEl.srcObject = null;
    stopTimer();
  }

  function resetState() {
    state = 'idle';
    pendingOfferSdp = null;
    iceQueue = [];
    muted = false;
    cleanupMedia();
    hideBar();
    setButtons({});
    if (btnMute) btnMute.innerHTML = (window.ChativaIcons && window.ChativaIcons.mic) || '';
  }

  function createPeerConnection() {
    const conn = new RTCPeerConnection({ iceServers: ICE_SERVERS });

    conn.onicecandidate = (event) => {
      if (event.candidate) {
        realtime.send({
          type: 'call:ice',
          room: realtime.room(),
          payload: { candidate: event.candidate },
        });
      }
    };

    conn.ontrack = (event) => {
      if (remoteAudioEl) {
        remoteAudioEl.srcObject = event.streams[0];
      }
    };

    conn.onconnectionstatechange = () => {
      if (conn.connectionState === 'connected' && state !== 'in-call') {
        state = 'in-call';
        callBarTitle.textContent = cfg.partnerName ? `تماس با ${cfg.partnerName}` : 'در حال مکالمه';
        setButtons({ mute: true, hangup: true });
        startTimer();
      }
      if (['failed', 'disconnected', 'closed'].includes(conn.connectionState) && state === 'in-call') {
        endCall(false);
      }
    };

    return conn;
  }

  async function flushIceQueue() {
    if (!pc || !pc.remoteDescription) return;
    const queued = iceQueue;
    iceQueue = [];
    for (const candidate of queued) {
      try {
        await pc.addIceCandidate(candidate);
      } catch (e) { /* بی‌اهمیت */ }
    }
  }

  async function startCall() {
    if (state !== 'idle') return;
    if (!realtime.isConnected()) {
      dialogAlert('برای تماس صوتی زنده، Real-Time باید فعال و متصل باشد.');
      return;
    }

    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      dialogAlert('دسترسی به میکروفون امکان‌پذیر نیست.');
      return;
    }

    state = 'calling';
    pc = createPeerConnection();
    localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));

    callBarTitle.textContent = 'در حال تماس...';
    btnDecline.textContent = 'لغو';
    setButtons({ decline: true });
    showBar();

    try {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      realtime.send({
        type: 'call:offer',
        room: realtime.room(),
        payload: { sdp: offer.sdp },
      });
    } catch (e) {
      endCall(false);
    }
  }

  function showIncomingCall(fromName) {
    state = 'ringing';
    callBarTitle.textContent = `تماس صوتی از ${fromName || cfg.partnerName}`;
    btnDecline.textContent = 'رد تماس';
    setButtons({ decline: true, accept: true });
    showBar();
  }

  async function acceptCall() {
    if (state !== 'ringing' || !pendingOfferSdp) return;

    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      dialogAlert('دسترسی به میکروفون امکان‌پذیر نیست.');
      declineCall();
      return;
    }

    pc = createPeerConnection();
    localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));

    try {
      await pc.setRemoteDescription({ type: 'offer', sdp: pendingOfferSdp });
      await flushIceQueue();
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      realtime.send({
        type: 'call:answer',
        room: realtime.room(),
        payload: { sdp: answer.sdp },
      });
      callBarTitle.textContent = 'در حال اتصال...';
      setButtons({});
    } catch (e) {
      endCall(false);
    }
  }

  function declineCall() {
    realtime.send({ type: 'call:busy', room: realtime.room(), payload: {} });
    resetState();
  }

  function endCall(notify) {
    if (notify !== false && state !== 'idle') {
      realtime.send({ type: 'call:end', room: realtime.room(), payload: {} });
    }
    resetState();
  }

  function toggleMute() {
    if (!localStream) return;
    muted = !muted;
    localStream.getAudioTracks().forEach((t) => {
      t.enabled = !muted;
    });
    const icons = window.ChativaIcons || {};
    btnMute.innerHTML = muted ? (icons['mic-off'] || '') : (icons.mic || '');
  }

  // ---- دکمه‌ها ----
  btnCall.addEventListener('click', startCall);
  btnAccept.addEventListener('click', acceptCall);
  btnDecline.addEventListener('click', () => {
    if (state === 'ringing') {
      declineCall();
    } else {
      endCall();
    }
  });
  btnHangup.addEventListener('click', () => endCall());
  btnMute.addEventListener('click', toggleMute);

  // ---- سیگنال‌های ورودی از WebSocket ----
  realtime.onCallSignal(async (type, payload) => {
    if (type === 'call:offer') {
      if (state !== 'idle') {
        // مشغولیم - رد خودکار برای طرف مقابل
        realtime.send({ type: 'call:busy', room: realtime.room(), payload: {} });
        return;
      }
      pendingOfferSdp = payload.sdp;
      showIncomingCall(payload.from);
      return;
    }

    if (type === 'call:answer') {
      if (state !== 'calling' || !pc) return;
      try {
        await pc.setRemoteDescription({ type: 'answer', sdp: payload.sdp });
        await flushIceQueue();
      } catch (e) {
        endCall(false);
      }
      return;
    }

    if (type === 'call:ice') {
      if (!payload.candidate) return;
      let candidate;
      try {
        candidate = new RTCIceCandidate(payload.candidate);
      } catch (e) {
        return;
      }
      if (pc && pc.remoteDescription) {
        try {
          await pc.addIceCandidate(candidate);
        } catch (e) { /* بی‌اهمیت */ }
      } else {
        iceQueue.push(candidate);
      }
      return;
    }

    if (type === 'call:end') {
      if (state !== 'idle') {
        cleanupMedia();
        state = 'idle';
        callBarTitle.textContent = 'تماس پایان یافت';
        setButtons({});
        setTimeout(() => {
          hideBar();
          pendingOfferSdp = null;
          iceQueue = [];
        }, 1500);
      }
      return;
    }

    if (type === 'call:busy') {
      if (state === 'calling') {
        cleanupMedia();
        state = 'idle';
        callBarTitle.textContent = 'کاربر مشغول است';
        setButtons({});
        setTimeout(() => {
          hideBar();
        }, 1500);
      }
      return;
    }
  });

  // ---- پایان تماس هنگام بستن/ترک صفحه (رفرش کامل یا بستن تب) ----
  window.addEventListener('beforeunload', () => {
    if (state !== 'idle') {
      try {
        realtime.send({ type: 'call:end', room: realtime.room(), payload: {} });
      } catch (e) { /* بی‌اهمیت */ }
    }
  });

  // ---- پایان تماس هنگام ناوبری PJAX به صفحه‌ی دیگر ----
  // باید قبل از teardown خود main.js اجرا شود (که اتصال WebSocket را می‌بندد)
  // تا پیام call:end واقعاً ارسال شود؛ به همین دلیل با unshift به ابتدای صف اضافه می‌شود.
  window.ChativaTeardowns = window.ChativaTeardowns || [];
  window.ChativaTeardowns.unshift(function () {
    if (state !== 'idle') {
      try {
        realtime.send({ type: 'call:end', room: realtime.room(), payload: {} });
      } catch (e) { /* بی‌اهمیت */ }
    }
    resetState();
  });
})();
