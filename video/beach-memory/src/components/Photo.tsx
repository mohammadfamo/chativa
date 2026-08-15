import React from "react";
import { AbsoluteFill, Img, interpolate, staticFile, useCurrentFrame } from "remotion";
import { theme } from "../theme";
import { framing, Shot, XFADE } from "../shots";

/**
 * Shallow depth of field. A blurred copy of the same framing, revealed only at
 * the edges by a radial mask, so the centre of interest stays sharp and the
 * corners fall away the way a fast lens would render them.
 *
 * Multi-stop and with no transparent plateau: a two-stop mask leaves a visible
 * oval seam wherever the picture is flat, and this photograph has a big flat
 * sky for it to show up in.
 */
const DOF_MASK = [
  "radial-gradient(ellipse 72% 54% at 50% 48%,",
  "rgba(0,0,0,0) 6%,",
  "rgba(0,0,0,0.18) 38%,",
  "rgba(0,0,0,0.55) 66%,",
  "rgba(0,0,0,0.85) 84%,",
  "rgba(0,0,0,1) 100%)",
].join(" ");

/**
 * Peak defocus during a shot change, in pixels.
 *
 * Every shot is a crop of the SAME photograph, so a plain cross-dissolve
 * superimposes the man on himself at two different scales and reads as a
 * glitch. Racking both sides out of focus through the handover turns that
 * overlap into a soft bloom and gives the change a lens motivation.
 */
const RACK = 24;

/**
 * Defocus over a handover, as a function of transition progress.
 *
 * It has to PEAK at the midpoint, where the two framings are blended 50/50 and
 * the ghosting is worst. Ramping it (ease-out on the incoming, ease-in on the
 * outgoing) does the opposite: both sides land near zero in the middle and the
 * double image comes through perfectly sharp.
 */
const rackBell = (p: number) => Math.sin(Math.PI * Math.max(0, Math.min(1, p)));

/** One framing of the photograph, handed over by a defocus rack. */
export const PhotoShot: React.FC<{
  shot: Shot;
  isFirst: boolean;
  nextFrom: number | null;
}> = ({ shot, isFirst, nextFrom }) => {
  const frame = useCurrentFrame();
  const local = frame - shot.from;

  // The camera move. Eased end to end — nothing here travels linearly.
  const t = interpolate(local, [0, shot.duration], [0, 1], {
    easing: theme.ease.drift,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  const zoom = shot.zoom + (shot.zoomTo - shot.zoom) * t;
  const focal = [
    shot.focal[0] + (shot.focalTo[0] - shot.focal[0]) * t,
    shot.focal[1] + (shot.focalTo[1] - shot.focal[1]) * t,
  ] as const;

  const { w, h, left, top } = framing(focal, zoom);

  // Cross-dissolve in. The first shot is revealed by the opening fade instead.
  const opacity = isFirst
    ? 1
    : interpolate(local, [0, XFADE], [0, 1], {
        easing: theme.ease.inOut,
        extrapolateLeft: "clamp",
        extrapolateRight: "clamp",
      });

  // Both sides of a handover carry the same bell, so the blur is symmetric and
  // the pair washes out together at the crossing point.
  const inRack = isFirst || local > XFADE ? 0 : rackBell(local / XFADE);
  const outRack =
    nextFrom == null || frame < nextFrom || frame > nextFrom + XFADE
      ? 0
      : rackBell((frame - nextFrom) / XFADE);
  const rack = Math.max(inRack, outRack);
  const blur = RACK * rack;

  // A dissolve alone reads as a slideshow; pairing it with a small scale
  // differential makes the handover feel like a lens move. The extra scale
  // while defocused keeps the blur from sampling past the photo's own edge.
  const dissolveScale = interpolate(local, [0, XFADE], [1.035, 1], {
    easing: theme.ease.out,
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const edgeGuard = 1 + rack * 0.05;

  const box: React.CSSProperties = {
    position: "absolute",
    left,
    top,
    width: w,
    height: h,
    objectFit: "cover",
  };

  const src = staticFile(theme.photo.src);

  return (
    <AbsoluteFill
      style={{
        opacity,
        transform: `scale(${(isFirst ? 1 : dissolveScale) * edgeGuard})`,
        // Turns an overcast afternoon into remembered warmth. The sepia stays
        // low — past ~0.12 it drains the sea and the whole frame goes muddy.
        // The brightness lift rides the rack: a small bloom through the
        // handover, which hides what is left of the overlap.
        filter: [
          "saturate(1.16)",
          "contrast(1.11)",
          `brightness(${(0.99 + rack * 0.06).toFixed(3)})`,
          "sepia(0.1)",
          "hue-rotate(-6deg)",
        ].join(" "),
      }}
    >
      <Img src={src} style={{ ...box, filter: blur > 0.2 ? `blur(${blur.toFixed(2)}px)` : undefined }} />
      {/* Pointless under a rack, and it doubles the cost of the priciest frames. */}
      {blur > 3 ? null : (
        <Img
          src={src}
          style={{
            ...box,
            filter: "blur(7px)",
            maskImage: DOF_MASK,
            WebkitMaskImage: DOF_MASK,
          }}
        />
      )}
    </AbsoluteFill>
  );
};

/** The full stack of shots; only the ones on screen are mounted. */
export const PhotoCamera: React.FC<{ shots: Shot[] }> = ({ shots }) => {
  const frame = useCurrentFrame();
  return (
    <AbsoluteFill>
      {shots.map((shot, i) => {
        const active = frame >= shot.from && frame < shot.from + shot.duration + XFADE;
        if (!active) return null;
        return (
          <PhotoShot
            key={shot.id}
            shot={shot}
            isFirst={i === 0}
            nextFrom={shots[i + 1]?.from ?? null}
          />
        );
      })}
    </AbsoluteFill>
  );
};
