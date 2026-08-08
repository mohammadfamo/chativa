/**
 * ابزار تست سریع سرور Real-Time (اختیاری، برای توسعه/عیب‌یابی)
 *
 * قبل از دیپلوی روی cPanel، می‌توانید سرور را لوکال اجرا کنید و این
 * اسکریپت را برای اطمینان از درست کار کردن auth/join/broadcast بزنید:
 *
 *   WS_SECRET=mysecret PORT=3050 node server.js &
 *   WS_SECRET=mysecret PORT=3050 node smoke-test.js
 *
 * اگر همه‌چیز درست باشد، در انتها "SMOKE TEST PASSED" چاپ می‌شود.
 */

'use strict';

const crypto = require('crypto');
const http = require('http');
const WebSocket = require('ws');

const SECRET = process.env.WS_SECRET || 'testsecret123';
const PORT = process.env.PORT || 3050;

function base64url(buf) {
  return buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function mintToken(uid, username, ttlSeconds = 300) {
  const payload = JSON.stringify({ uid, un: username, exp: Math.floor(Date.now() / 1000) + ttlSeconds });
  const payloadB64 = base64url(Buffer.from(payload, 'utf8'));
  const sig = crypto.createHmac('sha256', SECRET).update(payloadB64).digest();
  return payloadB64 + '.' + base64url(sig);
}

const ws = new WebSocket(`ws://127.0.0.1:${PORT}/ws`);

ws.on('open', () => {
  console.log('[smoke-test] connected, authenticating...');
  ws.send(JSON.stringify({ type: 'auth', token: mintToken(42, 'ali') }));
});

ws.on('message', (raw) => {
  const msg = JSON.parse(raw.toString());
  console.log('[smoke-test] received:', msg);

  if (msg.type === 'auth_error') {
    console.error('[smoke-test] FAILED: auth rejected — WS_SECRET on client/server must match');
    process.exit(1);
  }

  if (msg.type === 'auth_ok') {
    ws.send(JSON.stringify({ type: 'join', room: 'public' }));
  }

  if (msg.type === 'joined') {
    const body = JSON.stringify({ room: 'public', event: 'message', payload: { id: 1, body: 'test' } });
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: PORT,
        path: '/broadcast',
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Internal-Secret': SECRET,
          'Content-Length': Buffer.byteLength(body),
        },
      },
      (res) => {
        if (res.statusCode !== 200) {
          console.error('[smoke-test] FAILED: /broadcast returned', res.statusCode);
          process.exit(1);
        }
      }
    );
    req.write(body);
    req.end();
  }

  if (msg.type === 'message') {
    console.log('[smoke-test] SMOKE TEST PASSED ✅');
    process.exit(0);
  }
});

ws.on('error', (err) => {
  console.error('[smoke-test] FAILED: connection error —', err.message);
  console.error('[smoke-test] آیا سرور با «node server.js» در حال اجراست؟');
  process.exit(1);
});

setTimeout(() => {
  console.error('[smoke-test] FAILED: timeout — پیام broadcast دریافت نشد');
  process.exit(1);
}, 5000);
