import React from "react";
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from "remotion";
import { theme } from "../theme";

/** Deterministic hash-noise so particles are identical on every render pass. */
const rnd = (i: number, salt: number) => {
  const x = Math.sin(i * 127.1 + salt * 311.7) * 43758.5453;
  return x - Math.floor(x);
};

/**
 * Layer 1 — the warm base the photograph sits on. Visible through the dissolves
 * and behind the opening fade, so it is never a flat black rectangle.
 */
export const BgMesh: React.FC = () => {
  const frame = useCurrentFrame();
  const d1 = Math.sin(frame / 62) * 44;
  const d2 = Math.cos(frame / 81) * 36;
  return (
    <AbsoluteFill style={{ background: theme.colors.bg }}>
      <div
        style={{
          position: "absolute",
          width: 1500,
          height: 1500,
          borderRadius: "50%",
          top: -520,
          left: -340 + d1,
          filter: "blur(60px)",
          background: `radial-gradient(circle, ${theme.colors.primary}30, transparent 62%)`,
        }}
      />
      <div
        style={{
          position: "absolute",
          width: 1200,
          height: 1200,
          borderRadius: "50%",
          bottom: -460,
          right: -300 - d2,
          filter: "blur(80px)",
          background: `radial-gradient(circle, ${theme.colors.accent}22, transparent 65%)`,
        }}
      />
    </AbsoluteFill>
  );
};

/**
 * Layer 3a — a warm bloom drifting across the blown-out sky. This is what sells
 * the golden-hour lie on a photograph shot under flat cloud.
 */
export const LightLeak: React.FC = () => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();

  const x = interpolate(frame, [0, durationInFrames], [-160, 220], {
    easing: theme.ease.drift,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const y = interpolate(frame, [0, durationInFrames], [-60, 180], {
    easing: theme.ease.drift,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  // The leak breathes rather than sitting at a fixed strength. Kept weak on
  // purpose: the sky in this photograph is already near-white, and a screen
  // blend over it blows the top half of the frame out to flat haze.
  const pulse = 0.78 + Math.sin(frame / 47) * 0.16;

  return (
    <AbsoluteFill style={{ pointerEvents: "none", mixBlendMode: "screen", opacity: 0.2 * pulse }}>
      <div
        style={{
          position: "absolute",
          width: 1400,
          height: 1100,
          left: -400 + x,
          top: 180 + y,
          filter: "blur(80px)",
          background: `radial-gradient(ellipse at center, ${theme.colors.primary}44, ${theme.colors.primary}14 42%, transparent 66%)`,
        }}
      />
      <div
        style={{
          position: "absolute",
          width: 820,
          height: 640,
          right: -260 - x * 0.5,
          top: 820 + y * 0.4,
          filter: "blur(95px)",
          background: `radial-gradient(ellipse at center, #FFD9A028, transparent 66%)`,
        }}
      />
    </AbsoluteFill>
  );
};

/**
 * Layer 3b — broad, slow bands of light across the water. Kept diffuse on
 * purpose: the framing moves shot to shot, so anything tied to a fixed spot on
 * the waves would slide off them.
 */
export const SeaShimmer: React.FC = () => {
  const frame = useCurrentFrame();
  const bands = 5;
  return (
    <AbsoluteFill style={{ pointerEvents: "none", mixBlendMode: "screen", opacity: 0.12 }}>
      {Array.from({ length: bands }).map((_, i) => {
        const speed = 0.42 + i * 0.13;
        const drift = Math.sin(frame / (58 + i * 17) + i * 1.4) * (90 + i * 26);
        const y = 380 + i * 190 + Math.sin(frame / (71 + i * 9)) * 16;
        const fade = 0.35 + Math.sin(frame / 39 + i * 0.9) * 0.28;
        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: -300 + drift * speed,
              top: y,
              width: 1800,
              height: 90 + i * 14,
              filter: "blur(34px)",
              opacity: Math.max(0, fade),
              background:
                "linear-gradient(90deg, transparent, rgba(255,236,204,0.5) 38%, rgba(255,255,255,0.28) 56%, transparent)",
            }}
          />
        );
      })}
    </AbsoluteFill>
  );
};

/**
 * Layer 3c — airborne motes. The cheapest, strongest cue that a still frame is
 * a living memory rather than a photograph on a slide.
 */
export const Bokeh: React.FC<{ count?: number }> = ({ count = 18 }) => {
  const frame = useCurrentFrame();
  const { width, height } = useVideoConfig();

  return (
    <AbsoluteFill style={{ pointerEvents: "none", mixBlendMode: "screen" }}>
      {Array.from({ length: count }).map((_, i) => {
        const size = 7 + rnd(i, 1) * 26;
        const speed = 0.16 + rnd(i, 2) * 0.34;
        const swayAmp = 24 + rnd(i, 3) * 62;
        const swayRate = 44 + rnd(i, 4) * 58;
        const phase = rnd(i, 5) * Math.PI * 2;

        const startY = height + 120 + rnd(i, 6) * height;
        // Wrap so the field never empties out over 22 seconds.
        const travelled = frame * speed * 3.1;
        const y = ((startY - travelled) % (height + 320) + height + 320) % (height + 320) - 160;
        const x = rnd(i, 7) * width + Math.sin(frame / swayRate + phase) * swayAmp;

        const twinkle = 0.3 + Math.sin(frame / (30 + rnd(i, 8) * 40) + phase) * 0.3;
        // Motes fade out near the top and bottom edges instead of popping.
        const edge = Math.min(1, Math.min(y + 160, height + 160 - y) / 260);

        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: x,
              top: y,
              width: size,
              height: size,
              borderRadius: "50%",
              filter: `blur(${size > 20 ? 7 : 3.5}px)`,
              opacity: Math.max(0, twinkle) * Math.max(0, edge) * 0.55,
              background: `radial-gradient(circle, #FFE7C0 0%, ${theme.colors.primary}70 45%, transparent 72%)`,
            }}
          />
        );
      })}
    </AbsoluteFill>
  );
};

/** Layer 4 — the grade. Unifies every shot into one look. */
export const Grade: React.FC = () => (
  <AbsoluteFill style={{ pointerEvents: "none" }}>
    <AbsoluteFill
      style={{ backgroundColor: theme.colors.primary, mixBlendMode: "soft-light", opacity: 0.24 }}
    />
    {/* Cool shadows against the warm highlights — the classic split tone. */}
    <AbsoluteFill
      style={{ backgroundColor: "#123241", mixBlendMode: "soft-light", opacity: 0.16 }}
    />
    <AbsoluteFill
      style={{
        background:
          "linear-gradient(180deg, rgba(9,6,4,0.36), transparent 24%, transparent 56%, rgba(9,6,4,0.6))",
      }}
    />
  </AbsoluteFill>
);

/** Layer 5a — procedural film grain, no asset file. */
export const Grain: React.FC = () => {
  const frame = useCurrentFrame();
  const noise = `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='220' height='220' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E")`;
  return (
    <AbsoluteFill
      style={{
        pointerEvents: "none",
        backgroundImage: noise,
        backgroundSize: "220px",
        backgroundPosition: `${(frame * 7) % 220}px ${(frame * 13) % 220}px`,
        opacity: 0.07,
        mixBlendMode: "overlay",
      }}
    />
  );
};

/** Layer 5b — vignette, breathing very slightly so the frame stays alive. */
export const Vignette: React.FC = () => {
  const frame = useCurrentFrame();
  const strength = 0.34 + Math.sin(frame / 54) * 0.03;
  return (
    <AbsoluteFill
      style={{
        pointerEvents: "none",
        background: `radial-gradient(ellipse at center, transparent 48%, rgba(6,4,2,${strength.toFixed(3)}) 100%)`,
      }}
    />
  );
};

/** Opens out of black and closes back into it. Topmost layer of all. */
export const FilmFade: React.FC = () => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();

  // Even and unhurried. easeOutExpo here would clear the black in ~12 frames
  // and throw away the reveal.
  const open = interpolate(frame, [0, 48], [1, 0], {
    easing: theme.ease.inOut,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  // Late and slow: the closing card needs a full second clear of the fade
  // before the picture starts going.
  const close = interpolate(frame, [durationInFrames - 30, durationInFrames - 2], [0, 1], {
    easing: theme.ease.inOut,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  return (
    <AbsoluteFill
      style={{
        pointerEvents: "none",
        backgroundColor: "#000",
        opacity: Math.max(open, close),
      }}
    />
  );
};
