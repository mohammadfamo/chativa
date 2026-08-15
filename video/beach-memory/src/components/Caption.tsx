import React from "react";
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { theme } from "../theme";

export type Line = {
  text: string;
  /** The one word carrying the hero colour. At most one per frame, by rule. */
  highlight?: string;
  from: number;
  to: number;
  /** Closing card: centred in the frame and set large. */
  hero?: boolean;
};

/** Persian punctuation is part of the token but not part of the match. */
const bare = (w: string) => w.replace(/[،.:…!؟?]/g, "");

export const Caption: React.FC<{ line: Line }> = ({ line }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const local = frame - line.from;
  const span = line.to - line.from;
  if (local < -1 || frame > line.to) return null;

  const words = line.text.split(" ");
  const size = line.hero ? 88 : 58;
  const perWord = 3;

  // Exit is deliberately faster than the entrance.
  const EXIT = 12;
  const exitT = interpolate(local, [span - EXIT, span], [0, 1], {
    easing: theme.ease.in,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const exitY = exitT * -34;
  const exitO = 1 - exitT;

  // The scrim rises with the block so type never sits on bright wet sand.
  const scrimP = spring({ frame: local, fps, config: theme.spring.soft });
  const scrim = scrimP * exitO;

  const rule = spring({ frame: local - 2, fps, config: theme.spring.smooth });

  return (
    <AbsoluteFill style={{ pointerEvents: "none" }}>
      <AbsoluteFill
        style={{
          opacity: scrim * (line.hero ? 0.94 : 0.82),
          // The closing card darkens the whole frame evenly. A radial scrim
          // leaves a bright ring in the flat sky and reads as a lens artefact.
          background: line.hero
            ? "linear-gradient(180deg, rgba(6,4,2,0.8), rgba(6,4,2,0.84))"
            : "linear-gradient(0deg, rgba(6,4,2,0.8) 4%, rgba(6,4,2,0.44) 32%, transparent 60%)",
        }}
      />

      <AbsoluteFill
        style={{
          justifyContent: line.hero ? "center" : "flex-end",
          alignItems: "center",
          // Pixel padding, never em — em would resolve against the parent size.
          // Extra bottom padding lifts the closing card off the horizon line.
          padding: line.hero ? "0 120px 150px" : "0 110px 400px",
        }}
      >
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 30,
            transform: `translateY(${exitY}px)`,
            opacity: exitO,
          }}
        >
          {/* Hairline rule above the closing card only — one accent, not two. */}
          {line.hero ? (
            <div
              style={{
                width: 132,
                height: 2,
                borderRadius: 2,
                background: theme.colors.textDim,
                transform: `scaleX(${rule})`,
                opacity: rule * 0.85,
              }}
            />
          ) : null}

          <div
            style={{
              direction: "rtl",
              display: "flex",
              flexWrap: "wrap",
              justifyContent: "center",
              // Pixel gaps between large type, by rule.
              columnGap: 18,
              rowGap: 14,
              fontFamily: theme.fonts.display,
              fontWeight: line.hero ? 900 : 700,
              fontSize: size,
              lineHeight: 1.42,
              color: theme.colors.text,
              textAlign: "center",
              maxWidth: line.hero ? 840 : 830,
            }}
          >
            {words.map((word, i) => {
              // Every word gets three properties, staggered. No lone fades.
              const p = spring({
                frame: local - i * perWord,
                fps,
                config: theme.spring.soft,
              });
              const isHero = line.highlight != null && bare(word) === bare(line.highlight);
              // Clamped even though the spring drives them: an overshooting
              // config would otherwise push the word past its resting place.
              const clamp = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;

              return (
                <span
                  key={i}
                  style={{
                    display: "inline-block",
                    opacity: p,
                    transform: `translateY(${interpolate(p, [0, 1], [26, 0], clamp)}px) scale(${interpolate(
                      p,
                      [0, 1],
                      [0.965, 1],
                      clamp,
                    )})`,
                    color: isHero ? theme.colors.primary : theme.colors.text,
                    textShadow: isHero
                      ? `0 0 42px ${theme.colors.glow}, 0 0 86px rgba(232,163,61,0.22), 0 4px 22px rgba(0,0,0,0.72)`
                      : "0 4px 22px rgba(0,0,0,0.74), 0 1px 3px rgba(0,0,0,0.55)",
                  }}
                >
                  {word}
                </span>
              );
            })}
          </div>
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
