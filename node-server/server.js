/**
 * Chativa Real-Time Server (مرحله ۵)
 * -------------------------------------------------
 * یک سرور سبک WebSocket + HTTP که برای اجرا روی cPanel با قابلیت
 * "Setup Node.js App" طراحی شده. هیچ اتصال مستقیمی به دیتابیس MySQL
 * ندارد - تمام داده‌ها را از اسکریپت‌های PHP می‌گیرد/می‌فرستد تا:
 *   ۱) نصب روی هاست‌های اشتراکی ساده‌تر شود (بدون نیاز به mysql2 و
 *      اطلاعات اتصال جداگانه)
 *   ۲) دیتابیس همیشه منبع حقیقت (source of truth) باقی بماند.
 *
 * مسیرها:
 *   GET  /health     - health check ساده
 *   POST /broadcast   - دریافت پیام از PHP و ارسال آن به کلاینت‌های یک room
 *   WS   /ws          - اتصال بلادرنگ مرورگر
 *
 * متغیرهای محیطی مورد نیاز (از تنظیمات Node.js App در cPanel):
 *   WS_SECRET     - رشته‌ی مخفی مشترک با PHP (از پنل ادمین > تنظیمات کپی کنید)
 *   PHP_BASE_URL  - آدرس کامل سایت PHP، مثل https://chat.example.com (بدون / انتهایی)
 *   PORT          - توسط خود cPanel به‌صورت خودکار تنظیم می‌شود
 */

'use strict';

const http = require('http');
const https = require('https');
const crypto = require('crypto');
const { URL } = require('url');
const { WebSocketServer } = require('ws');

const PORT = process.env.PORT || 3000;
const WS_SECRET = process.env.WS_SECRET || '';
const PHP_BASE_URL = (process.env.PHP_BASE_URL || '').replace(/\/+$/, '');
const MEMBERSHIP_CACHE_TTL_MS = 30 * 1000;

if (!WS_SECRET) {
  console.error('[chativa] هشدار: WS_SECRET تنظیم نشده. اتصال کلاینت‌ها رد خواهد شد.');
}
if (!PHP_BASE_URL) {
  console.error('[chativa] هشدار: PHP_BASE_URL تنظیم نشده. بررسی عضویت در گفتگوهای خصوصی کار نخواهد کرد.');
}

// ---------------------------------------------------------
// رجیستری room ها: roomName -> Set<ws>
// ---------------------------------------------------------
const rooms = new Map();

function joinRoom(ws, room) {
  if (!rooms.has(room)) rooms.set(room, new Set());
  rooms.get(room).add(ws);
  ws.rooms.add(room);
}

function leaveAllRooms(ws) {
  for (const room of ws.rooms) {
    const set = rooms.get(room);
    if (set) {
      set.delete(ws);
      if (set.size === 0) rooms.delete(room);
    }
  }
  ws.rooms.clear();
}

function sendJson(ws, data) {
  if (ws.readyState === ws.OPEN) {
    ws.send(JSON.stringify(data));
  }
}

function broadcastToRoom(room, data, exceptWs) {
  const set = rooms.get(room);
  if (!set) return 0;
  let count = 0;
  const json = JSON.stringify(data);
  for (const client of set) {
    if (client === exceptWs) continue;
    if (client.readyState === client.OPEN) {
      client.send(json);
      count++;
    }
  }
  return count;
}

// ---------------------------------------------------------
// توکن HMAC (باید دقیقاً با includes/realtime.php هم‌خوان باشد)
// ---------------------------------------------------------
function base64urlDecode(str) {
  return Buffer.from(str.replace(/-/g, '+').replace(/_/g, '/'), 'base64');
}

function verifyToken(token) {
  if (!WS_SECRET || typeof token !== 'string' || !token.includes('.')) return null;

  const dotIndex = token.lastIndexOf('.');
  const payloadB64 = token.slice(0, dotIndex);
  const sigB64 = token.slice(dotIndex + 1);
  if (!payloadB64 || !sigB64) return null;

  const expectedSig = crypto.createHmac('sha256', WS_SECRET).update(payloadB64).digest();
  let gotSig;
  try {
    gotSig = base64urlDecode(sigB64);
  } catch (e) {
    return null;
  }
  if (expectedSig.length !== gotSig.length || !crypto.timingSafeEqual(expectedSig, gotSig)) {
    return null;
  }

  let payload;
  try {
    payload = JSON.parse(base64urlDecode(payloadB64).toString('utf8'));
  } catch (e) {
    return null;
  }

  if (!payload || typeof payload.uid !== 'number' || !payload.exp || payload.exp < Math.floor(Date.now() / 1000)) {
    return null;
  }

  return payload; // { uid, un, exp }
}

// ---------------------------------------------------------
// تایید عضویت در گفتگوی خصوصی (سمت PHP) با کش کوتاه‌مدت
// ---------------------------------------------------------
const membershipCache = new Map(); // key -> { ok, expiresAt }

function verifyMembership(userId, conversationId) {
  return new Promise((resolve) => {
    if (!PHP_BASE_URL) {
      resolve(false);
      return;
    }

    const cacheKey = `${userId}:${conversationId}`;
    const cached = membershipCache.get(cacheKey);
    if (cached && cached.expiresAt > Date.now()) {
      resolve(cached.ok);
      return;
    }

    let target;
    try {
      target = new URL(PHP_BASE_URL + '/api/internal/verify_membership.php');
    } catch (e) {
      resolve(false);
      return;
    }
    target.searchParams.set('user_id', String(userId));
    target.searchParams.set('conversation_id', String(conversationId));

    const lib = target.protocol === 'https:' ? https : http;
    const req = lib.request(
      target,
      {
        method: 'GET',
        headers: { 'X-Internal-Secret': WS_SECRET },
        timeout: 4000,
      },
      (res) => {
        let body = '';
        res.on('data', (chunk) => (body += chunk));
        res.on('end', () => {
          let ok = false;
          try {
            ok = JSON.parse(body).ok === true;
          } catch (e) {
            ok = false;
          }
          membershipCache.set(cacheKey, { ok, expiresAt: Date.now() + MEMBERSHIP_CACHE_TTL_MS });
          resolve(ok);
        });
      }
    );
    req.on('error', () => resolve(false));
    req.on('timeout', () => {
      req.destroy();
      resolve(false);
    });
    req.end();
  });
}

function isValidRoomName(room) {
  return room === 'public' || /^conversation:\d+$/.test(room);
}

// ---------------------------------------------------------
// سرور HTTP (health check + دریافت broadcast از PHP)
// ---------------------------------------------------------
const httpServer = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    // CORS باز فقط برای این مسیر بی‌خطر تا دکمه‌ی «تست اتصال» در پنل ادمین کار کند
    res.writeHead(200, {
      'Content-Type': 'application/json; charset=utf-8',
      'Access-Control-Allow-Origin': '*',
    });
    res.end(JSON.stringify({ ok: true, rooms: rooms.size }));
    return;
  }

  if (req.method === 'POST' && req.url === '/broadcast') {
    if (req.headers['x-internal-secret'] !== WS_SECRET || !WS_SECRET) {
      res.writeHead(403, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ ok: false, error: 'forbidden' }));
      return;
    }

    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
      if (body.length > 5 * 1024 * 1024) req.destroy(); // محافظت ساده در برابر بدنه‌ی خیلی بزرگ
    });
    req.on('end', () => {
      let data;
      try {
        data = JSON.parse(body);
      } catch (e) {
        res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ ok: false, error: 'invalid_json' }));
        return;
      }

      const { room, event, payload } = data || {};
      if (!isValidRoomName(room) || typeof event !== 'string') {
        res.writeHead(422, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ ok: false, error: 'invalid_payload' }));
        return;
      }

      const delivered = broadcastToRoom(room, { type: event, payload: payload || {} });
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ ok: true, delivered }));
    });
    return;
  }

  res.writeHead(404, { 'Content-Type': 'application/json; charset=utf-8' });
  res.end(JSON.stringify({ ok: false, error: 'not_found' }));
});

// ---------------------------------------------------------
// WebSocket
// ---------------------------------------------------------
const wss = new WebSocketServer({ server: httpServer, path: '/ws' });

wss.on('connection', (ws) => {
  ws.isAlive = true;
  ws.rooms = new Set();
  ws.userId = null;
  ws.username = null;

  ws.on('pong', () => {
    ws.isAlive = true;
  });

  ws.on('message', async (raw) => {
    let msg;
    try {
      msg = JSON.parse(raw.toString());
    } catch (e) {
      return;
    }
    if (!msg || typeof msg.type !== 'string') return;

    // ---- احراز هویت ----
    if (msg.type === 'auth') {
      const payload = verifyToken(msg.token);
      if (!payload) {
        sendJson(ws, { type: 'auth_error' });
        ws.close();
        return;
      }
      ws.userId = payload.uid;
      ws.username = payload.un;
      sendJson(ws, { type: 'auth_ok' });
      return;
    }

    if (ws.userId === null) {
      return; // قبل از auth هیچ پیام دیگری پذیرفته نمی‌شود
    }

    // ---- عضویت در room ----
    if (msg.type === 'join') {
      const room = String(msg.room || '');
      if (!isValidRoomName(room)) return;

      if (room.startsWith('conversation:')) {
        const conversationId = parseInt(room.split(':')[1], 10);
        const allowed = await verifyMembership(ws.userId, conversationId);
        if (!allowed) return;
      }

      joinRoom(ws, room);
      sendJson(ws, { type: 'joined', room });
      return;
    }

    // ---- تایپینگ (فقط relay، بدون تماس با دیتابیس) ----
    if (msg.type === 'typing') {
      const room = String(msg.room || '');
      if (!ws.rooms.has(room)) return;
      broadcastToRoom(room, { type: 'typing', payload: { username: ws.username } }, ws);
      return;
    }

    // ---- Seen (فقط relay؛ ثبت در دیتابیس از سمت PHP انجام می‌شود) ----
    if (msg.type === 'seen') {
      const room = String(msg.room || '');
      if (!ws.rooms.has(room)) return;
      broadcastToRoom(room, { type: 'seen', payload: {} }, ws);
      return;
    }

    // ---- سیگنالینگ تماس صوتی WebRTC (فقط relay بین دو نفر همان گفتگو) ----
    if (['call:offer', 'call:answer', 'call:ice', 'call:end', 'call:busy'].includes(msg.type)) {
      const room = String(msg.room || '');
      if (!ws.rooms.has(room) || !room.startsWith('conversation:')) return;
      broadcastToRoom(room, { type: msg.type, payload: Object.assign({}, msg.payload, { from: ws.username }) }, ws);
      return;
    }
  });

  ws.on('close', () => {
    leaveAllRooms(ws);
  });

  ws.on('error', () => {
    leaveAllRooms(ws);
  });
});

// پینگ دوره‌ای برای تشخیص اتصال‌های مرده
const heartbeat = setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) {
      leaveAllRooms(ws);
      return ws.terminate();
    }
    ws.isAlive = false;
    ws.ping();
  });
}, 30000);

wss.on('close', () => clearInterval(heartbeat));

httpServer.listen(PORT, () => {
  console.log(`[chativa] real-time server در حال اجرا روی پورت ${PORT}`);
});
