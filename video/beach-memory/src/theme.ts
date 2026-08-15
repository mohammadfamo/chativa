// Single source of truth. No component inlines a color, easing or spring.
//
// Palette: "warm premium". The source photograph is a cool, overcast afternoon;
// the grade pushes it toward remembered golden hour, so the base is a warm
// near-black and the single hero color is a soft gold.
import { Easing } from "remotion";

export const theme = {
  colors: {
    bg: "#0B0805",
    bgAlt: "#171009",
    primary: "#E8A33D", // THE hero color — at most one element per frame
    accent: "#7FB0C4", // cool sea tone, atmosphere only, never on type
    text: "#F7F1E8",
    textDim: "rgba(247, 241, 232, 0.62)",
    glow: "rgba(232, 163, 61, 0.42)",
    scrim: "rgba(11, 8, 5, 0.55)",
  },
  fonts: {
    display: "Vazirmatn",
    body: "Vazirmatn",
  },
  // Linear is forbidden. Every interpolate picks one of these.
  ease: {
    out: Easing.bezier(0.16, 1, 0.3, 1), // easeOutExpo — entrances
    inOut: Easing.bezier(0.83, 0, 0.17, 1), // easeInOutQuint — camera moves
    in: Easing.bezier(0.7, 0, 0.84, 0), // exits only
    drift: Easing.bezier(0.37, 0, 0.63, 1), // long, near-constant camera drift
  },
  spring: {
    snappy: { damping: 14, stiffness: 160, mass: 0.6 },
    smooth: { damping: 20, stiffness: 90, mass: 1 },
    soft: { damping: 26, stiffness: 62, mass: 1.1 }, // slow, weighted type
  },
  video: {
    width: 1080,
    height: 1920,
    fps: 30,
    durationInFrames: 690, // 23s
  },
  // Source photograph, in its own pixels — framing math derives from these.
  photo: {
    src: "img/beach@2x.jpg",
    width: 2048,
    height: 3072,
  },
} as const;
