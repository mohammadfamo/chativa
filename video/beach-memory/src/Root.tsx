import React from "react";
import { Composition } from "remotion";
import { theme } from "./theme";
import { BeachMemory } from "./scenes/BeachMemory";

export const RemotionRoot: React.FC = () => (
  <Composition
    id="BeachMemory"
    component={BeachMemory}
    durationInFrames={theme.video.durationInFrames}
    fps={theme.video.fps}
    width={theme.video.width}
    height={theme.video.height}
  />
);
