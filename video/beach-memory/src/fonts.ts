// Vazirmatn is bundled in public/fonts and registered through the FontFace API.
// Render is held until the faces are ready so the first frames never fall back
// to a system font.
import { useEffect, useState } from "react";
import { continueRender, delayRender, staticFile } from "remotion";

const FACES: { file: string; weight: string }[] = [
  { file: "Vazirmatn-Regular.woff2", weight: "400" },
  { file: "Vazirmatn-Medium.woff2", weight: "500" },
  { file: "Vazirmatn-SemiBold.woff2", weight: "600" },
  { file: "Vazirmatn-Bold.woff2", weight: "700" },
  { file: "Vazirmatn-Black.woff2", weight: "900" },
];

const register = async () => {
  await Promise.all(
    FACES.map(async ({ file, weight }) => {
      const face = new FontFace(
        "Vazirmatn",
        `url(${staticFile(`fonts/${file}`)}) format("woff2")`,
        { weight, style: "normal", display: "block" },
      );
      await face.load();
      document.fonts.add(face);
    }),
  );
};

/**
 * Holds the render until Vazirmatn is available.
 *
 * delayRender has to be reached from inside a render — calling it at module
 * scope throws while webpack is still evaluating the bundle and takes the whole
 * composition down with it. The useState initializer runs during the component's
 * first render, which is a valid place for it.
 */
export const useFonts = () => {
  const [handle] = useState(() => delayRender("Loading Vazirmatn"));

  useEffect(() => {
    register()
      .catch((err) => {
        // Never hang the render on a font failure — fall back and keep going.
        console.error("font load failed", err);
      })
      .finally(() => continueRender(handle));
  }, [handle]);
};
