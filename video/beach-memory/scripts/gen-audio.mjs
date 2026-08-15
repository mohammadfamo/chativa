/**
 * Synthesizes the soundtrack as a 16-bit stereo WAV. No asset downloads, no
 * dependencies, fully deterministic — re-running always produces the same file.
 *
 * Layers: ocean bed (filtered noise with swell envelopes), warm pad chords that
 * change on the visual cuts, a sparse piano motif, sub thumps on each cut, and
 * one shimmer riser into the payoff beat.
 */
import { writeFileSync, mkdirSync } from "node:fs";
import { dirname } from "node:path";

const SR = 44100;
const DUR = 23;
const N = Math.round(SR * DUR);
const OUT = new URL("../public/audio/score.wav", import.meta.url).pathname;

/** Visual cut points in seconds — the score changes with the picture. */
const CUTS = [3.0, 7.0, 11.5, 15.5, 19.5];
const PAYOFF = 15.5;

const L = new Float64Array(N);
const R = new Float64Array(N);

// Deterministic PRNG so renders are reproducible.
let seed = 0x2f6e2b1;
const rand = () => {
  seed ^= seed << 13;
  seed ^= seed >>> 17;
  seed ^= seed << 5;
  seed >>>= 0;
  return seed / 0xffffffff - 0.5;
};

const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);
/** Equal-power-ish smoothstep, used for every fade so nothing clicks. */
const smooth = (x) => {
  const t = clamp01(x);
  return t * t * (3 - 2 * t);
};

// ---------------------------------------------------------------- ocean bed
// Two decorrelated noise streams -> 2-pole lowpass -> slow swell envelope.
{
  const lp = [
    { a: 0, b: 0 },
    { a: 0, b: 0 },
  ];
  const hp = [{ z: 0 }, { z: 0 }];
  const chans = [L, R];
  for (let ch = 0; ch < 2; ch++) {
    for (let i = 0; i < N; i++) {
      const t = i / SR;
      // Cutoff drifts so the surf breathes instead of sitting as flat hiss.
      const cutoff = 620 + 260 * Math.sin(t / 3.1 + ch * 1.7);
      const k = 1 - Math.exp((-2 * Math.PI * cutoff) / SR);
      const s = rand();
      lp[ch].a += k * (s - lp[ch].a);
      lp[ch].b += k * (lp[ch].a - lp[ch].b);
      // Gentle highpass to strip mud below ~90Hz.
      const hpK = 1 - Math.exp((-2 * Math.PI * 90) / SR);
      hp[ch].z += hpK * (lp[ch].b - hp[ch].z);
      const band = lp[ch].b - hp[ch].z;

      // Wave swells: incommensurate periods so no loop is audible.
      const swell =
        0.55 +
        0.26 * Math.sin((t / 7.3) * 2 * Math.PI + ch * 0.6) +
        0.16 * Math.sin((t / 4.1) * 2 * Math.PI + 1.2) +
        0.11 * Math.sin((t / 11.7) * 2 * Math.PI + 2.4);

      chans[ch][i] += band * 14 * Math.max(0, swell) * 0.22;
    }
  }
}

// ------------------------------------------------------------------- chords
const NOTE = {
  F2: 87.31, G2: 98.0, A2: 110.0, C3: 130.81, E3: 164.81,
  F3: 174.61, G3: 196.0, A3: 220.0, B3: 246.94, C4: 261.63,
  D4: 293.66, E4: 329.63, F4: 349.23, G4: 392.0, A4: 440.0,
  C5: 523.25, D5: 587.33, E5: 659.25,
};

/** Chord changes land exactly on the visual cuts. */
const CHORDS = [
  { t: 0.0, end: 3.0, notes: [NOTE.A2, NOTE.A3, NOTE.C4, NOTE.E4] },
  { t: 3.0, end: 7.0, notes: [NOTE.F2, NOTE.F3, NOTE.C4, NOTE.F4] },
  { t: 7.0, end: 11.5, notes: [NOTE.C3, NOTE.C4, NOTE.E4, NOTE.G4] },
  { t: 11.5, end: 15.5, notes: [NOTE.A2, NOTE.A3, NOTE.C4, NOTE.E4] },
  { t: 15.5, end: 19.5, notes: [NOTE.F2, NOTE.F3, NOTE.C4, NOTE.A4] },
  { t: 19.5, end: 23.0, notes: [NOTE.C3, NOTE.C4, NOTE.E4, NOTE.G4] },
];

for (const chord of CHORDS) {
  const start = Math.round(chord.t * SR);
  // Overlap the tail into the next chord so changes cross-fade.
  const stop = Math.min(N, Math.round((chord.end + 1.6) * SR));
  const attack = 0.9;
  const release = 1.6;
  for (let vi = 0; vi < chord.notes.length; vi++) {
    const f = chord.notes[vi];
    // Detuned pair per voice gives the pad its slow chorus movement.
    const detunes = [1, 1.0016, 0.9986];
    const voiceGain = vi === 0 ? 0.55 : 0.32;
    for (let i = start; i < stop; i++) {
      const t = (i - start) / SR;
      const held = chord.end - chord.t;
      const env =
        smooth(t / attack) * (1 - smooth((t - held) / release));
      if (env <= 0) continue;
      let s = 0;
      for (const d of detunes) {
        const w = 2 * Math.PI * f * d;
        s += Math.sin(w * t) + 0.18 * Math.sin(2 * w * t) + 0.07 * Math.sin(3 * w * t);
      }
      s /= detunes.length;
      const pan = 0.5 + (vi % 2 === 0 ? -0.14 : 0.14);
      const v = s * env * voiceGain * 0.16;
      L[i] += v * (1 - pan);
      R[i] += v * pan;
    }
  }
}

// -------------------------------------------------------------- piano motif
/** [time, frequency] — sparse, descending, resolving on the last beat. */
const MOTIF = [
  [0.9, NOTE.E5], [1.7, NOTE.C5], [2.6, NOTE.A4],
  [3.4, NOTE.C5], [4.4, NOTE.F4], [5.3, NOTE.A4],
  [6.4, NOTE.C5], [7.3, NOTE.E5], [8.2, NOTE.G4],
  [9.2, NOTE.C5], [10.3, NOTE.A4],
  [11.6, NOTE.E5], [12.5, NOTE.C5], [13.5, NOTE.A4], [14.6, NOTE.G4],
  [15.7, NOTE.A4], [16.6, NOTE.C5], [17.6, NOTE.E5], [18.6, NOTE.D5],
  [19.7, NOTE.C5], [20.6, NOTE.A4], [21.6, NOTE.E4],
];

for (const [t0, f] of MOTIF) {
  const start = Math.round(t0 * SR);
  const len = Math.round(2.6 * SR);
  // Higher notes decay faster, as a real string does.
  const tau = 0.95 - 0.00035 * f;
  const attack = 0.006;
  for (let i = start; i < Math.min(N, start + len); i++) {
    const t = (i - start) / SR;
    const env = smooth(t / attack) * Math.exp(-t / tau);
    if (env < 1e-5) break;
    const w = 2 * Math.PI * f;
    const s =
      Math.sin(w * t) +
      0.45 * Math.sin(2 * w * t) +
      0.2 * Math.sin(3 * w * t) +
      0.09 * Math.sin(4 * w * t) +
      0.22 * Math.sin(0.5 * w * t); // sub-octave body
    const v = s * env * 0.085;
    L[i] += v * 0.52;
    R[i] += v * 0.48;
  }
}

// ------------------------------------------------------- sub thumps on cuts
for (const cut of CUTS) {
  const start = Math.round((cut - 0.04) * SR);
  const len = Math.round(1.2 * SR);
  for (let i = Math.max(0, start); i < Math.min(N, start + len); i++) {
    const t = (i - start) / SR;
    const env = Math.exp(-t / 0.22) * smooth(t / 0.004);
    // Pitch drop gives the thump its weight.
    const f = 58 * Math.exp(-t * 2.2) + 32;
    const v = Math.sin(2 * Math.PI * f * t) * env * 0.18;
    L[i] += v;
    R[i] += v;
  }
}

// ------------------------------------------------------ shimmer into payoff
{
  const start = Math.round((PAYOFF - 1.8) * SR);
  const len = Math.round(2.2 * SR);
  let z = 0;
  for (let i = Math.max(0, start); i < Math.min(N, start + len); i++) {
    const t = (i - start) / SR;
    const p = clamp01(t / 1.8);
    const env = smooth(p) * (1 - smooth((t - 1.8) / 0.4));
    // Noise band sweeping upward.
    const cutoff = 900 + 5200 * p;
    const k = 1 - Math.exp((-2 * Math.PI * cutoff) / SR);
    z += k * (rand() - z);
    const air =
      0.5 * Math.sin(2 * Math.PI * NOTE.E5 * 2 * t) +
      0.35 * Math.sin(2 * Math.PI * NOTE.C5 * 2 * t + 0.7);
    const v = (z * 3.2 + air * 0.25) * env * 0.06;
    L[i] += v * 0.6;
    R[i] += v * 0.4;
  }
}

// ------------------------------------------------------------ master shaping
const fadeIn = Math.round(0.8 * SR);
const fadeOut = Math.round(2.2 * SR);
let peak = 0;
for (let i = 0; i < N; i++) {
  const fi = smooth(i / fadeIn);
  const fo = smooth((N - i) / fadeOut);
  L[i] *= fi * fo;
  R[i] *= fi * fo;
  // Soft saturation keeps transients musical instead of clipping hard.
  L[i] = Math.tanh(L[i] * 1.15);
  R[i] = Math.tanh(R[i] * 1.15);
  peak = Math.max(peak, Math.abs(L[i]), Math.abs(R[i]));
}
const norm = peak > 0 ? 0.84 / peak : 1;

// ----------------------------------------------------------------- WAV file
const bytesPerSample = 2;
const dataBytes = N * 2 * bytesPerSample;
const buf = Buffer.alloc(44 + dataBytes);
buf.write("RIFF", 0);
buf.writeUInt32LE(36 + dataBytes, 4);
buf.write("WAVE", 8);
buf.write("fmt ", 12);
buf.writeUInt32LE(16, 16);
buf.writeUInt16LE(1, 20); // PCM
buf.writeUInt16LE(2, 22); // stereo
buf.writeUInt32LE(SR, 24);
buf.writeUInt32LE(SR * 2 * bytesPerSample, 28);
buf.writeUInt16LE(2 * bytesPerSample, 32);
buf.writeUInt16LE(16, 34);
buf.write("data", 36);
buf.writeUInt32LE(dataBytes, 40);

let off = 44;
for (let i = 0; i < N; i++) {
  buf.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(L[i] * norm * 32767))), off);
  buf.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(R[i] * norm * 32767))), off + 2);
  off += 4;
}

mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, buf);
console.log(`wrote ${OUT} — ${DUR}s stereo, peak ${(peak * norm).toFixed(3)}`);
