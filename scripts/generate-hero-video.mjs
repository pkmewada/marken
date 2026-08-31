import ffmpegPath from "ffmpeg-static";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import path from "node:path";

const run = promisify(execFile);
const productsDir = path.resolve(import.meta.dirname, "..", "images", "products");
const outDir = path.resolve(import.meta.dirname, "..", "videos");
const posterOut = path.resolve(import.meta.dirname, "..", "images", "hero-poster.jpg");

// Studio product photography (clean white background, no baked-in marketing
// text) composited onto a MarkEn-navy backdrop — the marketing banners have
// promotional copy burned into the pixels, which fights with the HTML
// headline overlaid on top, so they're unsuitable as hero footage.
const clips = [
  "ult-deep-freezer-86.webp",
  "icu-7-function-bed.webp",
  "ventilator.webp",
].map((f) => path.join(productsDir, f));

const SEG = 4; // seconds per image
const XFADE = 1; // crossfade duration
const FPS = 24;
const W = 1920;
const H = 1080;

// A low input framerate keeps the demuxer from repeating the still image
// thousands of times; zoompan then holds each of those few raw frames for
// exactly FPS output frames (one second), giving a predictable frame count.
const inputs = clips.flatMap((f) => [
  "-loop", "1", "-framerate", "1", "-t", String(SEG + XFADE), "-i", f,
]);

// Scale each product to a fixed height, pad it onto a navy card centered in
// the frame, vignette the corners so the pad seam melts into the backdrop,
// then apply a slow zoom.
const zoompan = (i) =>
  `[${i}:v]scale=-1:840:flags=lanczos,pad=${W}:${H}:(ow-iw)/2:(oh-ih)/2:color=0x0a2472,vignette=PI/4.2,zoompan=z='min(zoom+0.0008,1.06)':d=${FPS}:s=${W}x${H}:fps=${FPS},format=yuv420p[v${i}]`;

const zoompans = clips.map((_, i) => zoompan(i)).join(";");

let chain = "[v0][v1]xfade=transition=fade:duration=" + XFADE + ":offset=" + SEG + "[x1];";
let prevOffset = SEG;
let prevLabel = "x1";
for (let i = 2; i < clips.length; i++) {
  const offset = prevOffset + SEG;
  const label = `x${i}`;
  chain += `[${prevLabel}][v${i}]xfade=transition=fade:duration=${XFADE}:offset=${offset}[${label}];`;
  prevOffset = offset;
  prevLabel = label;
}
chain += `[${prevLabel}]format=yuv420p[vout]`;

const filterComplex = zoompans + ";" + chain;

const outFile = path.join(outDir, "hero.mp4");

const totalDuration = SEG * clips.length + XFADE;

const args = [
  "-y",
  "-loglevel",
  "error",
  ...inputs,
  "-filter_complex",
  filterComplex,
  "-map",
  "[vout]",
  "-r",
  String(FPS),
  "-c:v",
  "libx264",
  "-preset",
  "faster",
  "-crf",
  "30",
  "-maxrate",
  "2200k",
  "-bufsize",
  "4000k",
  "-pix_fmt",
  "yuv420p",
  "-movflags",
  "+faststart",
  "-an",
  "-t",
  String(totalDuration),
  outFile,
];

console.log("Generating hero video...");
await run(ffmpegPath, args, { maxBuffer: 1024 * 1024 * 50 });
console.log("hero.mp4 written to", outFile);

console.log("Generating poster frame...");
await run(ffmpegPath, ["-y", "-i", outFile, "-vframes", "1", "-q:v", "3", posterOut]);
console.log("poster written to", posterOut);
