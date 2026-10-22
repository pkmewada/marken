# MarkEn Website — Project Reference

This file is a cached technical reference for the MarkEn marketing/catalogue website. It was produced by a full read-through of every HTML, CSS and JS file in the repo (September 2026). Read this file before making changes instead of re-scanning the whole repo.

## 1. Project Overview

MarkEn is a B2B medical-equipment manufacturer (cold chain, blood bank, hospital furniture, medical equipment). This repo is its **marketing/catalogue website**: plain HTML/CSS/JS pages (no framework, no bundler, no build step) plus a small **PHP + MySQL layer** for the product catalogue and the admin panel.

- Runs on Apache + PHP 8.1+ + MySQL (WAMP locally). `mod_rewrite` and `.htaccess` must be enabled.
- **The database creates itself**: the first request that calls `db()` (`app/db.php`) and finds `marken_admin` missing runs `installDatabase()` from `app/schema.php`, which creates the DB and tables and seeds the original 4 categories + 24 products. No install step is needed.
- `package.json` only holds **dev-only** tooling (`sharp`, `ffmpeg-static`) for one-off asset generation (`npm run optimize-images`, `npm run generate-hero-video`) — irrelevant to how the site runs in a browser.
- **Products/categories live in MySQL** and are served to the front-end by `api/catalog.php`. **Blog posts/blog categories also live in MySQL** (`blogs`, `blogCategories`) and are served as JSON by `api/blog.php` — see §15. `js/blog-data.js` is the legacy static data, no longer loaded by any page.
- "Forms" (contact, newsletter, quote/enquiry/document requests) have no server: they build `mailto:` links or a `wa.me` (WhatsApp) link client-side. There is no cart, checkout, or payment flow anywhere on the site — it's lead-generation, not e-commerce.

## 2. Repository Structure

```
index.html                 Homepage
products.html               Product catalogue/listing (filter + search)
product-details.html        Single product page (see §7)
blog.html                   Blog listing (filter + search)
blog-details.html           Single blog article page
css/
  base.css                  Design tokens, resets, .container, .btn*, .reveal — loaded on every page
  header.css                 Site header, nav, mega menu, mobile menu — loaded on every page
  footer.css                 Site footer — loaded on every page
  hero.css                   Pinned/fixed full-bleed hero — loaded ONLY by index.html and product-details.html
  home.css                   index.html-only sections
  products.css                products.html-only (+ product card component)
  blog.css                    Shared blog card component + blog.html listing styles (also loaded by blog-details.html and index.html)
  blog-details.css            blog-details.html-only
  product-details.css         product-details.html-only (also overrides the shared hero for this page, see §8)
app/                         Shared PHP (denied over HTTP by app/.htaccess)
  config.php                 DB credentials, admin user + bcrypt hash, upload settings — gitignored, per-server;
                             config.example.php is the committed template (add new settings to both)
  db.php                     db() PDO singleton (auto-installs on missing DB), dbOne/dbAll/dbRun, toJson/fromJson
  schema.php                 installDatabase() + migrateSchema() (idempotent). db() runs migrateSchema() once whenever
                             app/.schema-version (gitignored) differs from SCHEMA_VERSION in db.php — bump it when adding tables/columns
  mailer.php                 sendMail($to, $subject, $html, $attachments) — MIME + minimal SMTP client (falls back to mail())
  public-api.php             Shared guards for public form endpoints: readPublicForm(), emailError(), rateLimit(), jsonOut()
  html-sanitizer.php         sanitizeHtml() allowlist sanitizer for blog rich text (DOMDocument) + isSafeUrl()
  product-sections.php       Product page section keys + order rules (normalize / strict parse / DB value) — see §16
  blog-seed.php / .json      importBlogSeed(): idempotent import of the original static posts (blog-seed.json = exact export of js/blog-data.js)
api/
  catalog.php                Emits `const CATEGORIES = …; const PRODUCTS = …;` as JavaScript (published products only)
  blog.php                   Blog JSON: list (published cards + active categories), ?slug= single post + related, admin ?preview=1
  categories.php             JSON category list for the header mega menu / mobile menu
  document-request.php       Product page doc cards → emails the document (attached if uploaded) + logs to documentRequests
  enquiry.php                Homepage contact form → enquiries
  newsletter.php             Footer newsletter → newsletterSubscribers
admin/                       PHP admin panel — see §13
uploads/                     Files uploaded from the admin (script execution denied by uploads/.htaccess)
js/
  main.js                    Shared: header scroll toggle (.is-solid + .is-compact), mega menu, mobile menu, .reveal animation, homepage collage carousel, newsletter form — loaded on every page
  data.js                    Helper functions (getProduct/getCategory/etc.) + shared productCardHTML() over the globals from api/catalog.php — loaded by index, products, product-details, always AFTER api/catalog.php
  blog-data.js                LEGACY static blog data — not loaded anywhere since the blog moved to MySQL; kept until the client confirms, safe to delete
  blog-render.js              Shared blog helpers: fetchBlog(), blogCardHTML(), formatBlogDate(), escBlog() — loaded by index, blog, blog-details
  products-page.js            products.html-only: renders/filters the product grid
  blog-listing.js             blog.html-only: renders/filters the blog grid
  blog-details.js             blog-details.html-only: renders one article from ?slug=, builds JSON-LD
  product-details.js          product-details.html-only: renders one product from ?slug=
images/
  logo.svg, hero-poster.jpg
  products/*.webp             Product photography (studio shots, light background)
  banners/*.webp              Category/marketing banner photography (has promotional copy baked into some pixels)
videos/hero.mp4               Homepage hero video (generated from product stills by scripts/generate-hero-video.mjs)
scripts/                      Dev-only Node tooling (not shipped to the browser)
  generate-hero-video.mjs     Builds videos/hero.mp4 + images/hero-poster.jpg from product stills via ffmpeg
  optimize-images.mjs         Batch-converts png/jpg in images/ to resized .webp via sharp
  import-blog.php             CLI only (`php scripts/import-blog.php`): reruns importBlogSeed() and prints a report
favicon.svg
```

No `node_modules` output ships to the site; the two npm scripts are run manually by a developer when swapping source imagery.

## 3. Page Architecture

This is a classic static MPA: **every page inlines its own copy of the header, mobile-menu, and footer markup** — there is no include/templating system (no PHP includes, no SSI, no client-side templating for these). All 5 HTML files contain byte-for-byte identical `<header class="site-header">…</header>` and `<div class="mobile-menu">…</div>` blocks, and near-identical `<footer class="site-footer">…</footer>` blocks (product-details.html and blog.html/blog-details.html/products.html footers add `id="contact"` to `<footer>`; the footer inside product-details.html is nested one level deeper inside `.page-wrap`).

Two distinct page layout patterns exist:

- **Pinned-hero pages** (index.html, product-details.html): `<header>` → `<div class="mobile-menu">` → `<section class="hero">` (fixed/pinned, see §8) → `<div class="hero-spacer">` → `<div class="page-wrap"><main>…</main><footer>…</footer></div>`.
- **Plain pages** (products.html, blog.html, blog-details.html): `<header>` → `<div class="mobile-menu">` → `<main>` (starts with a normal in-flow "hero" section that just has extra `padding-top` to clear the fixed header) → `<footer>`. No `.page-wrap`/`.hero-spacer` wrapper.

Every page loads `css/base.css`, `css/header.css`, `css/footer.css`, plus its own page CSS, each with a `?v=2` cache-busting query string. Scripts load at the end of `<body>` as plain `<script src="...">` tags (no `type="module"`, no bundler) — order matters because later files assume globals from earlier ones (e.g. `product-details.js` uses `getProduct()` defined in `data.js`).

## 4. CSS Architecture

- **`base.css`** — the only place design tokens live: `:root` custom properties (`--navy`, `--blue`, `--ink`, `--body`, `--bg`, `--bg-soft`, `--border`, `--gradient`, `--font-heading`, `--font-body`, `--util-h`, `--nav-h`, `--header-h: calc(var(--util-h) + var(--nav-h))`, `--container: min(1440px, 92vw)`, `--ease`). Also resets, `.container`, `.btn`/`.btn-dark`/`.btn-light`/`.btn-fill`, and the `.reveal` scroll-in-animation utility class.
- **`header.css`** — the entire site header, mega menu, mobile menu overlay, and the `1024px` breakpoint that swaps nav for the burger button. Shared by all 5 pages.
- **`footer.css`** — the entire site footer. Shared by all 5 pages.
- **`hero.css`** — the pinned/fixed full-bleed hero component (`.hero`, `.hero-spacer`, `.page-wrap`). Loaded only where that effect is used: index.html and product-details.html. See §8 for full mechanics.
- **Page-specific CSS** (`home.css`, `products.css`, `blog.css`, `blog-details.css`, `product-details.css`) — own everything below the shared header/hero, including that page's grid layouts, breakpoints, and any component only that page uses (e.g. `.product-card` lives in `products.css`, `.blog-card` in `blog.css`).
- **Exception to the "external CSS only" pattern**: `index.html` has an inline `<style>` block in `<head>` (~300 lines) for the homepage-only "vertical slider" section (`.vertical-slider-section`, `.vs-*`). This is the one place page styling isn't in an external stylesheet — be aware of it if asked to touch that section.
- No CSS framework (no Bootstrap/Tailwind), no preprocessor (plain CSS, custom properties for theming). BEM-ish naming (`.block__element`, `.block__element--modifier`) with page-scoped prefixes: `pd-` (product-details), `bd-` (blog-details), `vs-` (homepage vertical slider).
- **Where to make changes**: token/global change → `base.css`. Header/nav/mega-menu/mobile-menu → `header.css` (affects all pages). Footer → `footer.css` (affects all pages). Pinned hero mechanics → `hero.css` (affects index.html AND product-details.html — see §11). Anything visually scoped to one page → that page's own CSS file.

## 5. JavaScript Architecture

No modules, no bundler — plain global-scope scripts loaded in `<script>` tags at the bottom of `<body>`, relying on load order for shared globals.

- **`main.js`** (every page): header scroll toggle (adds `.is-solid` to `.site-header` once `window.scrollY > 10`, or immediately if the page has no `.hero`; adds `.is-compact` once `window.scrollY > 10` on every page — on desktop (>1024px) `header.css` then collapses `.header-main` and floats `.header-nav` centered into the utility bar, and shows the `a.nav-home` "Home" link that every page's header-nav carries; below 1024px the full header with the burger stays), mega-menu hover-to-open, mobile menu open/close (the overlay scrolls on its own; "Products" stays a link and the chevron `.mobile-menu__toggle` expands/collapses `.mobile-menu__panel`, collapsed by default; overlay ends with a "Make an Enquiry" button + SVG social icons), **header categories** (fetches `api/categories.php` and rebuilds `.mega-menu__grid` and `.mobile-menu__categories` from the DB; inserts an `hr.mega-menu__divider` before every new row of 4; the hardcoded category links in each HTML file are only a fallback if the request fails), a generic `IntersectionObserver` that adds `.is-visible` to `.reveal` elements present at initial load, the homepage "our story" collage carousel (guarded — no-ops if `.collage__nav` isn't present), and the newsletter form handler (guarded similarly).
- **`api/catalog.php` → `data.js`** (products, product-details): `api/catalog.php` is loaded as a `<script>` and defines the `CATEGORIES` and `PRODUCTS` globals from MySQL (product keys: `slug`, `name`, `category`, `image`, `featured`, `description`, `fullDescription`, `gallery`, `features`, `applications`, `specifications`, `certifications`, `documents`, `metaTitle`, `metaDescription` — empty fields are omitted). `data.js` then defines `getCategory()`, `getProduct()`, `getProductsByCategory()`, `getRelatedProducts()`. Draft products are never emitted.
- **`blog-render.js`** (index, blog, blog-details): `fetchBlog(params)` (GET `api/blog.php`, throws `Error` with `.status` and a user-facing message), the shared `blogCardHTML(post)` card template (escapes every field via `escBlog()`), and `formatBlogDate()`. The homepage grid (inline script in index.html) calls `fetchBlog({ featured: 1, limit: 3 })`.
- **`products-page.js`** (products.html only): renders the category filter buttons from `CATEGORIES` (only "All Products" is static in the HTML), reads the category from the clean URL `/products/{slug}` (legacy `?category=` still read), renders/filters `.products-grid` client-side, and writes `products` / `products/{slug}` back via `history.replaceState`, resolved against `document.baseURI`. Root `.htaccess` rewrites `/products/{slug}` → `products.html?category=` and 301s old `products(.html)?category=x` links to the clean URL. Like product-details.html, products.html writes a `<base href>` at the site root as the first thing in `<head>` (the clean URL adds a path segment) and the script re-points `#anchor` links. All category links site-wide use `products/{slug}` (relative to the root).
- **`blog-listing.js`** (blog.html only): fetches all published cards + active categories once from `api/blog.php`, renders the category buttons (only "All Articles" is static in the HTML), then filters/searches client-side; `?category=` is read and written via `history.replaceState`. Loading/empty/error messages reuse the `.blog-empty` paragraph.
- **`blog-details.js`** (blog-details.html only): reads the slug from the clean URL `/blog-details/{slug}` (legacy `?slug=` still read) plus `?preview=1`, fetches that one post from `api/blog.php?slug=`, populates breadcrumb/title/meta (metaTitle → title fallback, metaDescription → excerpt)/author/cover/body (server-sanitized HTML)/CTA (related product, or "Make an Enquiry")/related articles, and injects a `BlogPosting` JSON-LD block. A 404 renders an in-page "Article not found" state with `noindex` and the 3 latest posts (no redirect). Re-runs a fresh `IntersectionObserver` over freshly-injected `.reveal` elements (main.js's own observer already ran before this content existed).
- **`product-details.js`** (product-details.html only): reads `?slug=`, redirects to `products.html` if not found, otherwise first reorders the `<main>` sections by `product.sectionOrder` (§16), then populates hero/breadcrumb/media-slider/applications/certifications/documentation-links/FAQ. Also re-runs its own `IntersectionObserver` for the same "content injected after main.js ran" reason.
- **Exception to the "external JS only" pattern**: `index.html` has four inline `<script>` blocks — the featured product grid + stats count-up (rAF, starts when `.stats` enters the viewport), the vertical slider (scroll-driven, nothing is intercepted: `#vsScroll` reserves `(slides-1) × 70vh` of extra scroll height via `--vs-extra`, the `.vertical-slider-section` inside it is `position: sticky` below the header (`--util-h` desktop, `--header-h` ≤1024px), and the scroll position inside it picks the target slide; the track steps one slide per 600ms towards the target, so a fast scroll still plays every slide in order; dots scroll the page to that slide), the cert-card accordion + contact form handler, and the homepage blog-grid render call. These exist only in index.html's markup, not in `js/`.

**Initialization pattern**: every page-specific script (`product-details.js`, `blog-details.js`, `products-page.js`, `blog-listing.js`) executes top-level immediately on load (no `DOMContentLoaded` wrapper) — this works because the `<script>` tags sit at the end of `<body>`, after all the DOM they touch already exists.

## 6. Shared Components

| Component | Markup location | Styles | Behavior |
|---|---|---|---|
| Header (`.site-header`) | Duplicated inline in all 5 HTML files | `header.css` | `main.js` (solid + compact on scroll, mega menu, burger) |
| Mobile menu (`.mobile-menu`) | Duplicated inline in all 5 HTML files | `header.css` | `main.js` |
| Footer (`.site-footer`) | Duplicated inline in all 5 HTML files (near-identical; `id="contact"` varies) | `footer.css` | none (newsletter form handler in `main.js`) |
| Buttons (`.btn`, `.btn-dark`, `.btn-light`, `.btn-fill`) | n/a (utility classes) | `base.css` | none |
| Product card (`.product-card`) | Rendered via `productCardHTML()` in `products-page.js` | `products.css` | click → `product-details.html?slug=` |
| Homepage featured grid (`#home-featured-grid`) | `.products-grid` in index.html, filled by an inline script: admin-featured products first, topped up at random to 8 | `products.css` (loaded by index.html) + `.featured__products` in `home.css` | links to `product-details/{slug}` |
| Blog card (`.blog-card`) | Rendered via shared `blogCardHTML()` in `blog-render.js` from `api/blog.php` data | `blog.css` | click → `blog-details/{slug}` |
| Scroll-reveal (`.reveal`) | Applied as a class on many elements across all pages | `base.css` | `IntersectionObserver` in `main.js`, re-run per-page for late-injected content (see §5) |

## 7. Product Details Page (`product-details.html`)

**Loads**: `base.css`, `header.css`, `hero.css`, `product-details.css`, `footer.css` → `data.js`, `main.js`, `product-details.js`.

**Structure (top to bottom)**:
1. `<header class="site-header">` + `<div class="mobile-menu">` — identical to every other page.
2. `<section class="hero">` — the shared pinned hero (see §8), but here it holds an `<img id="pd-hero-image">` (a still image) rather than the `<video>` element index.html uses. Populated by JS with the product's photo, category eyebrow, and product-name title. `.pd-hero-actions` (Request a Quote / Download Brochure / Enquire Now / WhatsApp) is absolutely positioned over the bottom-right of the hero.
3. `<div class="hero-spacer">` then `<div class="page-wrap"><main>…</main><footer>…</footer></div>`.
4. Inside `<main>` (default order — after the breadcrumb the sections are reordered per product, see §16): breadcrumb → media slider (`.pd-slider`, JS-driven, up to 2 images: product photo + category banner) → applications section (banner image + tag list, removed from the DOM if the product has no `applications`) → certifications badge grid (company-wide, static list, same 5 certs on every product) → "Why Choose MarkEn" 4-card grid (fully static, not product-specific) → documentation request cards (4 static cards, each a `mailto:` link pre-filled per product) → FAQ accordion (5 static Q&As, not product-specific).

**URL**: canonical URL is `/product-details/{slug}` (root `.htaccess` rewrites it to `product-details.html?slug=`; legacy `?slug=` still works). Because the clean URL adds a path segment, `product-details.html` writes a `<base href>` pointing at the site root as the first thing in `<head>`, and `product-details.js` re-points in-page `#anchor` links at the current URL (otherwise they would resolve to the homepage). Root `.htaccess` must keep `-MultiViews` or this URL 404s.

**Hero media**: if the product has `heroMedia` (admin "Hero Media" card), `product-details.js` adds `.pd-hero--cover` to `.hero` and shows it full-bleed (`object-fit: cover`, no padding); `heroMediaType: "video"` swaps `#pd-hero-image` for a `<video class="hero__video">` (autoplay, muted, loop, playsinline, poster = product image; no autoplay under prefers-reduced-motion). Without hero media the product `image` is shown contained with padding (designed for studio shots on navy).

**Data source**: everything product-specific comes from `PRODUCTS` (served by `api/catalog.php` from MySQL), looked up by slug in `product-details.js`. Fields used: `name`, `category`, `image`, `gallery` (extra slider images; falls back to the category banner when empty), `description` (used only for the `<meta name="description">`), `applications` (optional — drives the applications section). Fields stored but **not yet rendered** on this page: `features`, `fullDescription`, `specifications`, `certifications`, `documents`, `metaTitle`/`metaDescription`.

**No pricing, no variants/sizes, no add-to-cart** — this is not an e-commerce product page. The CTAs are all lead-gen: "Request a Quote" and "Enquire Now" open pre-filled `mailto:` links, "WhatsApp" opens a pre-filled `wa.me` link, "Download Brochure" is an in-page anchor jump to the Documentation section (also `mailto:`-based, since no PDF assets exist to serve).

**Notable gap**: `features` (title+description pairs, present on the 4 "featured" products) and the other admin-managed fields listed above are in the DB but not rendered by `product-details.js` yet.

**Media slider**: `.pd-slider` shows at most 2 images (product photo, then category banner if different) with prev/next arrows and dot pagination; if only 1 image is available, the arrows/dots are hidden via inline `style.display`. CSS defines a `.pd-slider__video` class but no video slide is ever added by JS — unused/dead CSS, written for a feature that doesn't exist yet.

**Mobile behavior**: `.pd-hero-actions` buttons wrap to justified-center, 2-per-row under 760px. `.pd-why__grid` / `.pd-documentation__grid` go 4→2 columns under 1024px, →1 column under 560px. `.pd-slider__arrow` shrinks under 560px.

## 8. Header + Hero Relationship

**Status: implemented differently per page as of the latest change.** `index.html` still uses the original pinned/overlay hero described below. `product-details.html` has been changed so its header no longer overlays its hero — see "Product Details (current, separated)" beneath the original-mechanism writeup, which is kept because it still describes `index.html` (and the shared `header.css`/`hero.css` defaults every page starts from) exactly.

**This is the exact mechanism behind the header overlaying the hero on index.html, verified by reading the source (not inferred from names):**

- `.site-header` — `header.css:1-6`:
  ```css
  .site-header { position: fixed; inset: 0 0 auto 0; z-index: 100; }
  ```
  Pinned to the top of the viewport, above everything else on the page.

- `.hero` — `hero.css:4-12` (used only by index.html and product-details.html):
  ```css
  .hero { position: fixed; inset: 0; height: 92vh; width: 100%; overflow: hidden; z-index: 1; background: var(--navy); }
  ```
  **Also** `position: fixed`, **also** pinned at `top: 0`, filling the top 92% of the viewport height.

- Because both elements are `position: fixed` at `top: 0` and thus occupy overlapping screen space regardless of scroll position, the browser stacks them purely by `z-index`: header at `z-index: 100` paints **on top of** hero at `z-index: 1`. That is the entire mechanism — no negative margins, no transforms, no absolute positioning trick, just two fixed elements with different z-indices occupying the same screen region.

- This is **intentional**, not an accident — `hero.css:1-3` carries an explicit comment: *"The hero is pinned to the viewport while the page content scrolls up and over it — the spacer below reserves the scroll distance the fixed hero occupies, and .page-wrap (opaque, higher z-index) slides over it."* The header is deliberately kept transparent (see `.site-header` default state, no background) so the full hero photo/video shows through edge-to-edge behind the floating nav, then `main.js` adds `.is-solid` (opaque white background, defined in `header.css:112-137`) once `window.scrollY > 10`, so the "floating over hero" look only applies at the very top of the page.

- `.hero-spacer` (`hero.css:14-16`, `height: 92vh`) is a normal-flow block immediately after `.hero`. Since `.hero` itself is `position: fixed` (removed from document flow), this spacer exists purely to push everything after it down by 92vh, so `.page-wrap` doesn't start underneath the pinned hero.

- `.page-wrap` (`hero.css:87-92`) is `position: relative; z-index: 5;` — above the hero (5 > 1) but below the header (5 < 100) — with a box-shadow along its top edge to sell a "sliding panel" effect as the user scrolls it up over the fixed hero.

- **No JS repositions the header or hero.** `main.js`'s only effect on `.site-header` is toggling the `.is-solid` class (background color / box-shadow only, via CSS in `header.css:112-137`) — it never touches `position`, `top`, `z-index`, or layout.

- **Pages that do NOT have this overlay**: `products.html`, `blog.html`, `blog-details.html` never load `hero.css` and never use the `.hero` class. Their top sections (`.products-hero` in `products.css`, `.blog-hero` in `blog.css`, `.blog-detail` in `blog-details.css`) are ordinary in-flow blocks that simply add `padding-top: calc(var(--header-h) + 4rem)` (or `padding-top: var(--header-h)` for `.blog-detail`) to clear the fixed header — there's no pinned/fixed media section on those pages for the header to overlay.

### Product Details (current, separated)

`product-details.html` no longer overlays its header over its hero. This was implemented entirely as page-scoped overrides — `header.css` and `hero.css` (and therefore `index.html`, which shares those exact classes) were **not modified**.

- `<body>` on `product-details.html` now carries a `pd-page` class (the only HTML hook this change needed): `<body class="pd-page">`.
- `css/product-details.css` adds a block titled "Header / hero separation (Product Details only)" with rules scoped under `body.pd-page`:
  - `body.pd-page .site-header { position: sticky; ... }` — overrides the shared `.site-header`'s `position: fixed` to `sticky`. Because `header.css` already sets `top: 0` (via `inset: 0 0 auto 0`), no extra offset was needed. Sticky (not `static`) was chosen so the header still reserves its own box in normal document flow (satisfying "header occupies real layout space," no spacer/padding hack required) while remaining reachable/visible while scrolling, consistent with how the header behaves on every other page.
  - The same rule block also hardcodes the header's readable "solid" appearance (background, text colors, logo filter, burger color) by duplicating the exact values from `.site-header.is-solid` in `header.css`, scoped to `body.pd-page` instead of gated by the `.is-solid` class. This was necessary because the header's *default* (non-solid) state is white text with no background — legible only over a dark hero image behind it, which is no longer the case once the header sits in its own space. `main.js`'s scroll listener still toggles the `.is-solid` class on this page (untouched, see below), but it's now redundant: the `body.pd-page` rules have higher CSS specificity than both the default and `.is-solid` states, so the header looks correct regardless of what class JS adds or removes.
  - `body.pd-page .hero { position: relative; }` — overrides the shared `.hero`'s `position: fixed` so it participates in normal document flow immediately after the (now in-flow) header, instead of being pinned to the viewport. It keeps its explicit `height: 92vh` (inherited, unchanged) and stays a positioning context for its `position: absolute` children (`.hero__video`, `.hero__scrim`, `.hero__content`, `.pd-hero-actions`), which needed no changes themselves.
- `product-details.html` no longer includes `<div class="hero-spacer"></div>`. That element existed solely to reserve scroll space for the old pinned/`fixed` hero (which was removed from flow); once `.hero` is `position: relative` on this page, it already occupies its own space, so the spacer would only add an unwanted blank 92vh gap and was deleted rather than zeroed out via CSS.
- `.page-wrap` (its `position: relative; z-index: 5` and top box-shadow) was **not modified** — it still works as a normal block directly below the hero; the box-shadow now just reads as a soft separator rather than simulating the old "sliding over a pinned background" illusion, which is a harmless side effect, not a functional change.
- **No JavaScript was modified.** `main.js`'s header scroll-listener still runs on this page and still finds `.hero` in the DOM (so `hasHero` is still `true`, same branch as `index.html`), but its only effect — toggling the `.is-solid` class — is now visually absorbed by the higher-specificity `body.pd-page` rules described above, so it's inert rather than conflicting.
- **Why `index.html` is unaffected**: every new/changed rule is scoped under `body.pd-page`, a class that exists only on `product-details.html`. `header.css`, `hero.css`, and the bare `.site-header`/`.hero`/`.hero-spacer`/`.page-wrap` selectors inside them are byte-for-byte unchanged, so `index.html` (and `products.html`/`blog.html`/`blog-details.html`, which don't use `.hero` at all) render exactly as before.

**Key constraint for any future work here**: `.site-header`, `.hero`, `.hero-spacer`, and `.page-wrap` are still bare, unscoped class selectors shared verbatim by `index.html` — the `body.pd-page` scoping pattern above (mirroring `product-details.css`'s pre-existing `#pd-hero-image` override) is the template to reuse for any further product-details-only header/hero tweaks; don't edit the shared `header.css`/`hero.css` rules directly unless the intent is to change `index.html` too.

## 9. Responsive Behavior

- No CSS framework/breakpoint system — every breakpoint is a literal `@media (max-width: …)` value repeated per file; there's no shared variables/mixins for breakpoints (only `--container: min(1440px, 92vw)` is a shared responsive-ish token, in `base.css`).
- **1024px** — the primary breakpoint: `header.css` hides `.header-nav`/`.header-utility__left` and shows the burger button; most 4-column grids (home.css, product-details.css, products.css, blog.css) collapse to 2 columns.
- **900px** — footer 5-column grid collapses to 2 columns (`footer.css`).
- **760px** — hero height 92vh → 88vh and scrim gradient changes for readability (`hero.css`); `.pd-hero-actions` buttons wrap to 2-per-row (`product-details.css`); `.bd-cta` stacks (`blog-details.css`).
- **640px / 560px** — remaining grids collapse to 1 column; search/toolbar inputs go full-width; `.pd-slider__arrow` shrinks (`product-details.css`); `.products-hero`/`.blog-hero` are not separately breakpointed beyond their parent grid rules.

## 10. Development Conventions

- Plain HTML/CSS/JS, no build step — edit files directly, refresh the browser.
- Cache-busting via `?v=NNNN` query strings (currently `?v=2169`, one shared value; the admin's own `assets/admin.css`/`admin.js` have separate `?v=` numbers in `layout-top.php`/`layout-bottom.php`) on every `<link>`/`<script>` tag referencing `css/`/`js/` files — bump it repo-wide on **every deploy that changes CSS/JS**: the live host (Hostinger, hyther.in/marken) serves assets with `Cache-Control: max-age=604800` (7 days), so an unbumped file keeps returning visitors on the old version (local WAMP doesn't cache, which hides this).
- BEM-ish naming with page-scoped prefixes: `pd-` (product-details), `bd-` (blog-details), `vs-` (homepage vertical slider), unprefixed for genuinely shared components (`.site-header`, `.site-footer`, `.btn`, `.product-card`, `.blog-card`).
- Header/mobile-menu/footer markup is intentionally copy-pasted into every HTML file (no templating system exists) — any edit to shared chrome must be manually replicated across all 5 HTML files or pages will visually diverge.
- Product/category data and blog posts/blog categories live in MySQL and are edited through the admin panel. To change the *initial* seed catalogue, edit `app/schema.php`; the initial blog seed is `app/blog-seed.json`.
- Contact form, footer newsletter and product document requests POST to `api/*.php` (see §14). The hero CTAs (Request a Quote / Enquire Now / WhatsApp) are still `mailto:`/`wa.me` links.
- Source comments in this codebase explain **why**, not what (e.g. the hero.css pinned-hero comment, the product-details.css `object-fit` rationale, the `optimize-images.mjs`/`generate-hero-video.mjs` asset-choice comments) — match that style for any new non-obvious code.

## 11. Important Warnings

- **`.site-header` rules in `header.css` affect all 5 pages.** Any change to header position, sizing, or stacking affects the whole site, not just one page.
- **`.hero` / `.hero-spacer` / `.page-wrap` rules in `hero.css` affect both index.html and product-details.html identically** — these are the only two pages that load `hero.css`, and they share those exact class names with no scoping. A change meant only for product-details.html (like the upcoming header/hero separation) must not edit these shared selectors directly — see the scoping approach described in §8.
- **`main.js`'s `hasHero` check** (`!!document.querySelector(".hero")`) determines whether the header starts transparent or always-solid on load. If a future change renames `.hero` or removes it from a page without updating this check's assumptions, the header's transparent-over-hero behavior could silently break.
- **`api/catalog.php` + `data.js` and `blog-render.js` are multi-page dependencies.** The catalog pair is consumed by index.html (featured grid), products.html and product-details.html, in that script order (blog-details.html no longer loads it — `api/blog.php` resolves the related product server-side); `blog-render.js` is consumed by index.html, blog.html, and blog-details.html. Renaming or removing any exported helper (`getProduct`, `getCategory`, `getProductsByCategory`, `getRelatedProducts`, `productCardHTML`, `fetchBlog`, `blogCardHTML`, `formatBlogDate`, `escBlog`) requires checking every consumer.
- **Table-name case**: local WAMP MySQL runs with `lower_case_table_names=1` (Windows), so `blogCategories` is stored as `blogcategories` and wrong-case SQL still works locally. Linux hosting is case-sensitive — always write the exact camelCase table names.
- **Footer markup differs slightly per page** (which element carries `id="contact"`, and product-details.html nests its footer one level deeper inside `.page-wrap`) — don't assume a straight copy-paste of one page's footer onto another without checking anchor links (`#contact`) still resolve correctly.
- **index.html has inline `<style>`/`<script>` blocks** not mirrored in `css/`/`js/` (the vertical-slider CSS, and three inline scripts) — changes to homepage-only behavior may need to happen inside index.html itself, not in the shared files.

## 13. Admin Panel (`admin/`)

PHP + MySQL, Bootstrap 5.3.2 + Bootstrap Icons from `cdn.jsdelivr.net` (no SRI hashes — wrong hashes previously blocked the stylesheet). Login: `admin` / `marken@2024` (hash in `app/config.php`). Entry: `/admin/` → `login` or `products`.

```
admin/
  .htaccess          -MultiViews; /admin/page → page.php; GET /admin/page.php → 301 /admin/page;
                     products/new, products/edit/{slug} → product-form.php; categories/edit/{slug} → categories.php;
                     blogs/new, blogs/edit/{slug} → blog-form.php; blog-categories/edit/{slug} → blog-categories.php
  index.php          Redirects to products or login
  login.php / logout.php
  products.php       List (search, category filter, sort, pagination) + delete
  product-form.php   Create (/admin/products/new) and edit (/admin/products/edit/{slug}) — looked up by slug, no ids in URLs;
                     includes the drag-and-drop "Section Order" card (§16)
  product-sections.php  JSON endpoint (POST, X-CSRF-Token header) that saves only a product's sectionOrder (§16)
  categories.php     List + inline create/edit (/admin/categories/edit/{slug}); delete is blocked while products use the category; renaming a slug re-points its products
  blogs.php          "Blog Posts" list (search, category + status filters incl. Scheduled, sort, pagination), preview/view, delete — see §15
  blog-form.php      Create (/admin/blogs/new) and edit (/admin/blogs/edit/{slug}) with the built-in rich-text editor
  blog-categories.php  List + inline create/edit (/admin/blog-categories/edit/{slug}): description, active flag, display order, post count; delete blocked while posts use it
  enquiries.php      "Contact Enquiries": list, status change, delete (filled by the homepage contact form)
  document-requests.php  Product document leads (email, product, document, mail status) + CSV export
  newsletter.php     Footer newsletter subscribers + CSV export
  upload.php         JSON upload endpoint used by the forms → uploads/ (images/docs 10 MB, MP4/WebM video 50 MB).
                     PHP's 2 MB default is raised for admin/ only: admin/.user.ini (FastCGI — WAMP runs PHP via
                     mod_fcgid, which ignores php_value) and an <IfModule mod_php.c> block in admin/.htaccess
  includes/          auth.php (session, CSRF, validation, helpers), layout-top.php, layout-bottom.php — denied over HTTP
  assets/            admin.css, admin.js (table engine, drop-zone uploads, slug autofill, delete confirm).
                     Table filters: `<select data-table-filter>` matches `<tr data-filter>`; `data-table-filter="status"` matches `<tr data-status>` (any number of filters)
```

- **DB tables** (camelCase columns): `categories` (id, slug, name, tagline, image, sortOrder, createdAt, updatedAt), `products` (id, slug, name, categorySlug, status `published|draft`, featured, image, heroMediaType `image|video`, heroMedia, gallery/features/applications/specifications/certifications/documents/sectionOrder as JSON, description, fullDescription, metaTitle, metaDescription, sortOrder, createdAt, updatedAt), `enquiries` (id, name, email, phone, subject, message, status `new|replied|closed`, createdAt), plus `blogCategories` / `blogs` (see §15).
- **Page pattern**: `require includes/auth.php` → `requireLogin()` → handle POST (`verifyCsrf()` first, validate, PRG redirect with `setFlash()`) → set `$pageTitle`/`$pageActive`/`$topbarActions` → include `layout-top.php` → markup → optional `$pageScripts` → include `layout-bottom.php`.
- **Security**: every POST form includes `csrfField()`; `upload.php` takes the token from the `X-CSRF-Token` header (read from the `csrf-token` meta tag). Every output goes through `h()`. Uploads are typed by `finfo` and the extension comes from the MIME map in `config.php`, never from the client filename. Product and blog links/images must pass `isSafeLink()` (in `includes/auth.php`: http(s), mailto, or `uploads/` / `images/` paths) so `javascript:` URLs can't reach the public site.
- Links inside admin are extensionless and id-free (`products`, `products/edit/ventilator`) and rely on `admin/.htaccess`. Because routes are nested, `layout-top.php` emits `<base href="<?= adminUrl() ?>">` and `redirect()` builds absolute URLs via `adminUrl()` — keep links relative to the admin root, never `../`-relative to the current page (except `../` for site assets, which resolves from the admin root).
- Every visible input/textarea carries a descriptive `placeholder` with an example value — keep that for new fields.

## 14. Public Forms, Lead Capture & Email

| Form | Front-end | Endpoint | Table | Admin page |
|---|---|---|---|---|
| Contact (index.html `#contact-form`) | inline script in index.html | `api/enquiry.php` | `enquiries` | Contact Enquiries |
| Footer newsletter (every page) | `main.js` (adds `p.newsletter-status` after the form) | `api/newsletter.php` | `newsletterSubscribers` (unique email) | Newsletter (+CSV) |
| Product documentation cards | `product-details.js` (card link → inline email form) | `api/document-request.php` | `documentRequests` | Document Requests (+CSV) |

- **Genuine-record guards** (all in `app/public-api.php`, every endpoint uses them): POST only; cross-site `Origin` rejected; hidden `website` honeypot; `_t` = ms the page was open, added by `withBotCheck()` in `main.js` — under 2000 ms is treated as a bot; bots receive a fake `{"ok":true}` and nothing is stored. Emails: strict format, disposable-domain blocklist, DNS MX/A check, lower-cased before saving. Per-IP hourly caps (contact 5, docs 10, newsletter 5). Duplicates: newsletter by email; contact by email+message within 24h; documents not re-sent for the same email+product+document within 24h. Contact names must be letters only; messages allow at most 2 links. All SQL is prepared statements.
- **Document resolution is server-side**: the browser sends only product slug + document label. Labels must be one of the 4 standard cards or a document label set on that product in the admin. Files under `uploads/` are attached; `https://` documents are sent as a download button; no file → a "team will send it shortly" email (admin shows "No file uploaded — send manually"). Admin-added labels beyond the standard 4 are rendered as extra cards.
- **Email delivery** needs SMTP in `app/config.php` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE` tls|ssl, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`). With `SMTP_HOST` empty it falls back to PHP `mail()`, which fails on a stock WAMP install — the request is still logged with `mailStatus = failed` and the visitor sees "our team will email it shortly".

## 15. Blog System (MySQL + admin)

**Tables** (schema v5, `migrateSchema()` in `app/schema.php`):
- `blogCategories`: id, name, slug (unique), description, image, isActive, sortOrder, createdAt, updatedAt. Index `(isActive, sortOrder)`.
- `blogs`: id, categoryId (FK → blogCategories.id, **ON DELETE RESTRICT**), title, slug (unique, ≤150), excerpt, content (MEDIUMTEXT, sanitized HTML), featuredImage, authorName (default "MarkEn Team"), status `published|draft`, featured (homepage), relatedProductId (FK → products.id, ON DELETE SET NULL; drives the article CTA), publishedAt (DATETIME, date-only in the UI → `00:00:00`), metaTitle, metaDescription, createdAt, updatedAt. Indexes: `(status, publishedAt)`, `categoryId`, `relatedProductId`.

**Rules**
- *Public* = `status = 'published' AND publishedAt <= NOW()` (constant `BLOG_PUBLIC` in `api/blog.php`; `admin/blogs.php` uses the same SQL to show "Scheduled"). Drafts and future-dated posts are never returned without an admin preview.
- Category **inactive** → hidden from the public filter only; its posts stay listed under "All Articles" and their URLs keep working (breadcrumb then links to `blog.html`). Category **delete** → blocked (UI + FK) while any post uses it; move the posts first. Posts are never cascade-deleted.
- Content is sanitized by `sanitizeHtml()` (`app/html-sanitizer.php`) **on save and again on output**: allowlist p/br/h2–h4/strong/b/em/i/u/s/ul/ol/li/blockquote/hr/figure/figcaption/a[href,title]/img[src,alt]; script/style/iframe/svg/form… dropped with contents, other tags unwrapped, `h1` → `h2`, `div` → `p`; `javascript:`/`data:` URLs removed; external links get `target=_blank rel="noopener noreferrer"`; images get `loading=lazy`.

**Public API** — `api/blog.php` (GET only, `{ok:false,message}` + 404/405/500 on errors):
- no params → `{ok, categories:[active {slug,name,description}], posts:[cards newest first]}`; cards = `slug,title,excerpt,image,date,featured,category,categoryName` (no content).
- `?featured=1&limit=3` → featured first, then newest (homepage).
- `?slug=x` → `{ok, post:{…card, content, authorName, categoryActive, updatedAt, metaTitle, metaDescription, relatedProduct:{slug,name}|null, isPreview}, related:[3 cards, same category first]}`. Slugs are regex-validated.
- `?slug=x&preview=1` → also drafts/scheduled, only when the request carries a logged-in admin session (read with `read_and_close`; no session is started for normal visitors). Admin links use this for the eye/Preview buttons.

**URLs**: canonical article URL is `/blog-details/{slug}` (same pattern as product-details, §7). Root `.htaccess` rewrites it to `blog-details.html?slug=` and 301s old `blog-details(.html)?slug=x` links to it — only when `slug` is the sole parameter, so admin previews (`/blog-details/{slug}?preview=1`) are untouched. `blog-details.html` writes a `<base href>` at the site root as the first thing in `<head>`, and `blog-details.js` re-points `#anchor` links (footer + article body) at the current URL. Cards (`blogCardHTML`) and admin view/preview buttons link to the clean URL.

**Admin editor** (`blog-form.php`): dependency-free contenteditable + `execCommand` toolbar (H2/H3/P, bold, italic, lists, quote, link, image upload via `upload.php`, HTML source toggle); paste is inserted as plain text. Images are stored site-root relative (`uploads/…`) and displayed via `../` inside the admin (rewritten on load/submit). Featured image must be an uploaded/`images/` JPG/PNG/WebP/GIF.

**Migration**: `app/blog-seed.json` is an exact export of the old `js/blog-data.js` (3 categories, 9 posts). `importBlogSeed()` maps `image→featuredImage`, `date→publishedAt`, `relatedProductSlug→relatedProductId`, `sections[{heading,body}]→content` (`<h2>` + `<p>` per blank-line paragraph), author "MarkEn Team"; matches by slug with `INSERT IGNORE`, so reruns never duplicate or overwrite admin edits. It runs automatically once (existing DBs: when the v5 migration creates `blogs`; fresh installs: after `seedCatalogue()` so product links resolve). `php scripts/import-blog.php` reruns it and reports missing images / invalid slugs / unmapped fields.

**SEO limitation**: blog pages are still client-rendered (static HTML + fetch), like the product pages. Title/description/JSON-LD are set by JS, so crawlers that don't execute JS see the placeholder `<head>`. Googlebot renders JS; server-side rendering would be the next step if needed. No canonical tags exist on the site.

## 16. Product Page Section Order

Admins drag the product page sections into any order per product; the Hero is locked first.

- **Keys** (`PRODUCT_SECTIONS` in `app/product-sections.php`, = `data-section` attributes in `product-details.html`): `hero` (locked, outside `<main>`), `gallery` (`.pd-slider`), `applications`, `certifications`, `whyChoose`, `documentation`, `faq`. The breadcrumb is not a section and always stays directly under the hero. Default order = array order of `PRODUCT_SECTIONS` (the original page layout).
- **Storage**: `products.sectionOrder` JSON (schema v6, `addColumnIfMissing`). `NULL` = default order — `sectionOrderForDb()` stores `NULL` whenever the order equals the default, so untouched products follow future default changes. No data migration was needed; existing products are `NULL`.
- **Reading** (`normalizeSectionOrder()`, used by `api/catalog.php` and the admin form): drops unknown/duplicate keys, forces `hero` first, and slots any key missing from a stored order (e.g. a section added later) in after its default predecessor — no DB write on page views.
- **Saving** (`parseSectionOrder()`, strict): must be a list of every key exactly once with `hero` at index 0, otherwise rejected (endpoint → 422; form → field error, nothing saved). Two paths, both server-validated: the card's "Save Section Order" button (edit mode only) → `admin/product-sections.php` (login + CSRF header, product by slug, single `UPDATE products SET sectionOrder = ?` — no other column touched); and the hidden `sectionOrder[]` inputs submitted with the whole product form ("Save/Update Product", the only path for new products).
- **Admin UI** (`product-form.php` card + `.section-order*` styles in `admin.css`): dependency-free pointer-event drag on the grip button (mouse + touch, `touch-action: none`) and ArrowUp/ArrowDown on the focused grip. The hero row has no grip and rows are never inserted above it. Save button is enabled only with unsaved changes, disabled while saving; SweetAlert toast only after the server confirms; on failure the error is shown, the DB keeps the previous order and the list stays "Unsaved changes". Concurrency: last write wins, like the rest of the admin.
- **Public page**: `api/catalog.php` emits `sectionOrder` (always a complete normalized list) on every product; `product-details.js` appends each `main > [data-section]` in that order before wiring anything up (moving nodes keeps ids, anchors like `#pd-documentation`, and listeners). `product-details.css` adds top padding to `.pd-slider` / `.pd-applications` when they follow a grey band (`.pd-certifications` / `.pd-documentation`), since they normally only pad their bottom.
- **Testing**: log in, edit a product, reorder, save, reload (order persists), open `/product-details/{slug}` and check the section order; other products keep theirs. Invalid requests: POST `admin/product-sections` with `{"product":slug,"order":[…]}` and a hero-not-first / duplicate / unknown key → 422.

## 12. Future Claude Instructions

- Always read this `CLAUDE.md` before making changes to this repository.
- Treat `CLAUDE.md` as cached project context — do not re-scan the entire repository for every request.
- Inspect only the files directly relevant to the requested change unless this file's context is insufficient for the task.
- If an implementation change makes part of this file outdated, update the relevant section as part of that change.
- Preserve existing functionality unless the user explicitly requests a change.
- Prefer minimal, targeted modifications over unnecessary refactoring.
- Never redesign unrelated sections while implementing a requested change.
- Check both desktop and mobile implications before modifying shared CSS (`base.css`, `header.css`, `footer.css`, `hero.css`).
- Before changing a global/shared component (header, footer, hero, `.reveal`, `.btn*`, `data.js`/`blog-render.js` helpers), determine which other pages use it (see §11) so the change doesn't silently break them.
