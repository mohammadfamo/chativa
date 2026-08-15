import { Config } from "@remotion/cli/config";

Config.setVideoImageFormat("jpeg");
Config.setOverwriteOutput(true);
// The frame is a stack of blurs, blend modes and masks; one thread per frame
// keeps Chromium from thrashing on a small container.
Config.setConcurrency(2);
