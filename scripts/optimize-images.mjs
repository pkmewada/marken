import sharp from "sharp";
import { readdirSync, statSync } from "node:fs";
import path from "node:path";

const root = path.resolve(import.meta.dirname, "..", "images");

async function optimizeDir(dir, { width, quality }) {
  const files = readdirSync(dir).filter((f) => /\.(png|jpe?g)$/i.test(f));
  for (const file of files) {
    const full = path.join(dir, file);
    const out = full.replace(/\.(png|jpe?g)$/i, ".webp");
    const img = sharp(full);
    const meta = await img.metadata();
    const targetWidth = Math.min(width, meta.width ?? width);
    await sharp(full)
      .resize({ width: targetWidth, withoutEnlargement: true })
      .webp({ quality })
      .toFile(out);
    const before = statSync(full).size;
    const after = statSync(out).size;
    console.log(
      `${path.relative(root, full)} ${(before / 1024).toFixed(0)}KB -> ${(after / 1024).toFixed(0)}KB`
    );
  }
}

await optimizeDir(path.join(root, "products"), { width: 1200, quality: 82 });
await optimizeDir(path.join(root, "banners"), { width: 2400, quality: 78 });
console.log("done");
