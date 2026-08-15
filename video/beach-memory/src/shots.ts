// The "camera". One photograph, six framings — each shot is a focal point in
// normalized photo coordinates plus a zoom, moving over the shot's length.
// zoom 1 = the photo exactly covering the 9:16 frame.
import { theme } from "./theme";

export type Shot = {
  id: string;
  from: number;
  duration: number;
  focal: readonly [number, number];
  focalTo: readonly [number, number];
  zoom: number;
  zoomTo: number;
};

/**
 * Length of a shot handover, in frames. Short on purpose: the picture goes
 * soft through it, and a long rack repeated five times reads as a focus fault
 * rather than a transition.
 */
export const XFADE = 12;

// Landmarks in the photograph, normalized: his pointing hand at (0.19, 0.30),
// her face at (0.74, 0.55), their joined hands at (0.61, 0.63).
//
// The hand and the girl span 0.19–0.84 of the photo's width, and a 9:16 crop
// only shows 0.84/zoom of it — so any framing meant to hold the whole gesture
// has to sit at zoom ~1.0 and centre near x 0.515. Every wide here obeys that;
// anything tighter deliberately excludes the arm rather than clipping it.
export const SHOTS: Shot[] = [
  // 1 — steps. Opens intimate: joined hands, legs, surf. No arm to clip.
  { id: "steps", from: 0, duration: 106, focal: [0.63, 0.72], focalTo: [0.625, 0.685], zoom: 1.95, zoomTo: 1.78 },
  // 2 — the point. His whole gesture, hand held off the left edge with margin.
  { id: "point", from: 90, duration: 136, focal: [0.382, 0.41], focalTo: [0.347, 0.36], zoom: 1.3, zoomTo: 1.44 },
  // 3 — her face. Closest we get; a slow pull so it settles rather than lunges.
  { id: "her", from: 210, duration: 150, focal: [0.742, 0.548], focalTo: [0.737, 0.538], zoom: 2.15, zoomTo: 2.0 },
  // 4 — the hands. The emotional centre, tight, still pushing.
  { id: "hands", from: 344, duration: 136, focal: [0.612, 0.634], focalTo: [0.616, 0.626], zoom: 2.3, zoomTo: 2.45 },
  // 5 — payoff. Pulls all the way out to the whole photograph, gesture included.
  { id: "wide", from: 464, duration: 138, focal: [0.58, 0.52], focalTo: [0.515, 0.5], zoom: 1.34, zoomTo: 1.04 },
  // 6 — resolution. Comes to rest on the full frame under the closing card.
  { id: "rest", from: 586, duration: 104, focal: [0.515, 0.5], focalTo: [0.515, 0.5], zoom: 1.04, zoomTo: 1.0 },
];

/**
 * Maps a focal point + zoom to the absolute box of the photo inside the frame,
 * clamped so an edge of the photograph can never enter the picture.
 */
export const framing = (focal: readonly [number, number], zoom: number) => {
  const { width: OW, height: OH } = theme.video;
  const cover = Math.max(OW / theme.photo.width, OH / theme.photo.height);
  const w = theme.photo.width * cover * zoom;
  const h = theme.photo.height * cover * zoom;
  const left = Math.min(0, Math.max(OW - w, OW / 2 - focal[0] * w));
  const top = Math.min(0, Math.max(OH - h, OH / 2 - focal[1] * h));
  return { w, h, left, top };
};
