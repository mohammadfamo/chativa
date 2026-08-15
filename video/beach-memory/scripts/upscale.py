#!/usr/bin/env python3
"""Prepare the source photograph for the tightest framings.

The closest shot crops to roughly a third of the photo's width, so the original
1024x1536 gets stretched well past 1:1 on the way to a 1080-wide frame. Doing
the enlargement here with Lanczos plus a restrained unsharp pass looks
considerably better than letting the browser scale it during the render.

    python3 scripts/upscale.py
"""
from pathlib import Path

from PIL import Image, ImageEnhance, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "public" / "img" / "beach.jpg"
DST = ROOT / "public" / "img" / "beach@2x.jpg"


def main() -> None:
    img = Image.open(SRC).convert("RGB")
    out = img.resize((img.width * 2, img.height * 2), Image.LANCZOS)
    # Enough to restore edge definition the interpolation softened, not enough
    # to raise halos on the horizon.
    out = out.filter(ImageFilter.UnsharpMask(radius=2.2, percent=78, threshold=3))
    out = ImageEnhance.Sharpness(out).enhance(1.12)
    out.save(DST, quality=95, subsampling=0, optimize=True)
    print(f"{img.size} -> {out.size}  {DST.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
