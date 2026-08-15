import React from "react";
import { AbsoluteFill, Audio, staticFile } from "remotion";
import { theme } from "../theme";
import { SHOTS } from "../shots";
import { PhotoCamera } from "../components/Photo";
import { Caption, Line } from "../components/Caption";
import {
  BgMesh,
  Bokeh,
  FilmFade,
  Grade,
  Grain,
  LightLeak,
  SeaShimmer,
  Vignette,
} from "../components/Layers";
import { useFonts } from "../fonts";

/**
 * The narration. Each line lives inside one shot, enters after the framing has
 * settled and clears before the cut, leaving stretches of picture with no type
 * on it at all — the holds are what make the moving parts feel expensive.
 */
const LINES: Line[] = [
  { text: "یک عصرِ ساده، کنارِ دریا", highlight: "دریا", from: 34, to: 100 },
  { text: "گفتم: اون‌جا رو ببین…", highlight: "اون‌جا", from: 116, to: 212 },
  { text: "و او تمامِ دنیا را دید", highlight: "دنیا", from: 234, to: 352 },
  { text: "دستِ کوچکش در دستِ من", highlight: "من", from: 368, to: 470 },
  {
    text: "این لحظه، برای همیشه می‌ماند",
    highlight: "همیشه",
    from: 488,
    to: 592,
  },
  // Lands well before the fade starts at 660, so the card gets a clear hold.
  { text: "برای دخترم", highlight: "دخترم", from: 598, to: 690, hero: true },
];

export const BeachMemory: React.FC = () => {
  useFonts();

  return (
    <AbsoluteFill style={{ backgroundColor: theme.colors.bg }}>
      <Audio src={staticFile("audio/score.wav")} volume={0.92} />

      {/* 1 — base */}
      <BgMesh />

      {/* 2 — the photograph, under a moving camera */}
      <PhotoCamera shots={SHOTS} />

      {/* 3 — atmosphere and type */}
      <SeaShimmer />
      <LightLeak />
      <Bokeh />
      {LINES.map((line) => (
        <Caption key={line.from} line={line} />
      ))}

      {/* 4 — grade */}
      <Grade />

      {/* 5 — texture, then the fade */}
      <Grain />
      <Vignette />
      <FilmFade />
    </AbsoluteFill>
  );
};
