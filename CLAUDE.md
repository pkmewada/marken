# MarkEn Website — Project Reference

This file is a cached technical reference for the MarkEn marketing/catalogue website. It was produced by a full read-through of every HTML, CSS and JS file in the repo (September 2026). Read this file before making changes instead of re-scanning the whole repo.

## 1. Project Overview

MarkEn is a B2B medical-equipment manufacturer (cold chain, blood bank, hospital furniture, medical equipment). This repo is its **marketing/catalogue website**: a plain, static multi-page site — no framework, no bundler, no build step, no backend/CMS/API.

- Ship as-is: open/serve the repository root directly (e.g. via WAMP) and it works.
- `package.json` only holds **dev-only** tooling (`sharp`, `ffmpeg-static`) for one-off asset generation (`npm run optimize-images`, `npm run generate-hero-video`) — irrelevant to how the site runs in a browser.
- All product/blog content is hardcoded in JS data files, not fetched from any API.
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
js/
  main.js                    Shared: header scroll-solid toggle, mega menu, mobile menu, .reveal animation, homepage collage carousel, newsletter form — loaded on every page
  data.js                    Shared PRODUCTS/CATEGORIES array + getProduct/getCategory/etc. — loaded by index, products, product-details, blog-details
  blog-data.js                Shared BLOG_POSTS/BLOG_CATEGORIES + helpers — loaded by index, blog, blog-details
  blog-render.js              Shared blogCardHTML() template — loaded wherever blog cards render
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

- **`main.js`** (every page): header scroll-solid toggle (adds `.is-solid` to `.site-header` once `window.scrollY > 40`, or immediately if the page has no `.hero`), mega-menu hover-to-open, mobile menu open/close, a generic `IntersectionObserver` that adds `.is-visible` to `.reveal` elements present at initial load, the homepage "our story" collage carousel (guarded — no-ops if `.collage__nav` isn't present), and the newsletter form handler (guarded similarly).
- **`data.js`** (index, products, product-details, blog-details): hardcoded `CATEGORIES` and `PRODUCTS` arrays plus `getCategory()`, `getProduct()`, `getProductsByCategory()`, `getRelatedProducts()`.
- **`blog-data.js`** (index, blog, blog-details): hardcoded `BLOG_CATEGORIES` and `BLOG_POSTS` arrays plus `getBlogCategory()`, `getBlogPost()`, `getRelatedBlogPosts()`, `formatBlogDate()`.
- **`blog-render.js`** (index, blog, blog-details): the single shared `blogCardHTML(post)` template function used everywhere a blog card is rendered.
- **`products-page.js`** (products.html only): reads `?category=` from the URL, renders/filters `.products-grid` client-side, syncs category/search state back into the URL via `history.replaceState`.
- **`blog-listing.js`** (blog.html only): same pattern as `products-page.js` but for `BLOG_POSTS`.
- **`blog-details.js`** (blog-details.html only): reads `?slug=`, redirects to `blog.html` if not found, otherwise populates breadcrumb/title/meta/body/CTA/related-articles and injects a `BlogPosting` JSON-LD block. Re-runs a fresh `IntersectionObserver` over freshly-injected `.reveal` elements (main.js's own observer already ran before this content existed).
- **`product-details.js`** (product-details.html only): reads `?slug=`, redirects to `products.html` if not found, otherwise populates hero/breadcrumb/media-slider/applications/certifications/documentation-links/FAQ. Also re-runs its own `IntersectionObserver` for the same "content injected after main.js ran" reason.
- **Exception to the "external JS only" pattern**: `index.html` has three inline `<script>` blocks — the vertical slider's wheel/touch/keyboard logic, the cert-card accordion + contact form handler, and the homepage blog-grid render call. These exist only in index.html's markup, not in `js/`.

**Initialization pattern**: every page-specific script (`product-details.js`, `blog-details.js`, `products-page.js`, `blog-listing.js`) executes top-level immediately on load (no `DOMContentLoaded` wrapper) — this works because the `<script>` tags sit at the end of `<body>`, after all the DOM they touch already exists.

## 6. Shared Components

| Component | Markup location | Styles | Behavior |
|---|---|---|---|
| Header (`.site-header`) | Duplicated inline in all 5 HTML files | `header.css` | `main.js` (scroll-solid, mega menu, burger) |
| Mobile menu (`.mobile-menu`) | Duplicated inline in all 5 HTML files | `header.css` | `main.js` |
| Footer (`.site-footer`) | Duplicated inline in all 5 HTML files (near-identical; `id="contact"` varies) | `footer.css` | none (newsletter form handler in `main.js`) |
| Buttons (`.btn`, `.btn-dark`, `.btn-light`, `.btn-fill`) | n/a (utility classes) | `base.css` | none |
| Product card (`.product-card`) | Rendered via `productCardHTML()` in `products-page.js` | `products.css` | click → `product-details.html?slug=` |
| Product card, mini variant (`.product-card-mini`) | Hardcoded once, inline in index.html's featured section | `home.css` | none — not reused elsewhere |
| Blog card (`.blog-card`) | Rendered via shared `blogCardHTML()` in `blog-render.js` | `blog.css` | click → `blog-details.html?slug=` |
| Scroll-reveal (`.reveal`) | Applied as a class on many elements across all pages | `base.css` | `IntersectionObserver` in `main.js`, re-run per-page for late-injected content (see §5) |

## 7. Product Details Page (`product-details.html`)

**Loads**: `base.css`, `header.css`, `hero.css`, `product-details.css`, `footer.css` → `data.js`, `main.js`, `product-details.js`.

**Structure (top to bottom)**:
1. `<header class="site-header">` + `<div class="mobile-menu">` — identical to every other page.
2. `<section class="hero">` — the shared pinned hero (see §8), but here it holds an `<img id="pd-hero-image">` (a still image) rather than the `<video>` element index.html uses. Populated by JS with the product's photo, category eyebrow, and product-name title. `.pd-hero-actions` (Request a Quote / Download Brochure / Enquire Now / WhatsApp) is absolutely positioned over the bottom-right of the hero.
3. `<div class="hero-spacer">` then `<div class="page-wrap"><main>…</main><footer>…</footer></div>`.
4. Inside `<main>`: breadcrumb → media slider (`.pd-slider`, JS-driven, up to 2 images: product photo + category banner) → applications section (banner image + tag list, hidden entirely if the product has no `applications`) → certifications badge grid (company-wide, static list, same 5 certs on every product) → "Why Choose MarkEn" 4-card grid (fully static, not product-specific) → documentation request cards (4 static cards, each a `mailto:` link pre-filled per product) → FAQ accordion (5 static Q&As, not product-specific).

**Data source**: everything product-specific comes from `PRODUCTS` in `js/data.js`, looked up by the `?slug=` query param in `product-details.js`. Fields used: `name`, `category`, `image`, `description` (used only for the `<meta name="description">`), `applications` (optional — drives the applications section). Fields defined in `data.js` but **not used** on this page: `features` (see below).

**No pricing, no variants/sizes, no add-to-cart** — this is not an e-commerce product page. The CTAs are all lead-gen: "Request a Quote" and "Enquire Now" open pre-filled `mailto:` links, "WhatsApp" opens a pre-filled `wa.me` link, "Download Brochure" is an in-page anchor jump to the Documentation section (also `mailto:`-based, since no PDF assets exist to serve).

**Notable gap**: `PRODUCTS[].features` (title+description pairs, present on the 4 "featured" products) is defined in `data.js` but never rendered anywhere in `product-details.js` — dead data as far as this page is concerned.

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

- This is **intentional**, not an accident — `hero.css:1-3` carries an explicit comment: *"The hero is pinned to the viewport while the page content scrolls up and over it — the spacer below reserves the scroll distance the fixed hero occupies, and .page-wrap (opaque, higher z-index) slides over it."* The header is deliberately kept transparent (see `.site-header` default state, no background) so the full hero photo/video shows through edge-to-edge behind the floating nav, then `main.js` adds `.is-solid` (opaque white background, defined in `header.css:112-137`) once `window.scrollY > 40`, so the "floating over hero" look only applies at the very top of the page.

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
- Cache-busting via `?v=2` query strings on every `<link>`/`<script>` tag referencing `css/`/`js/` files — bump this manually (repo-wide, since it's hardcoded per tag) when a change needs to force-refresh cached assets for returning visitors.
- BEM-ish naming with page-scoped prefixes: `pd-` (product-details), `bd-` (blog-details), `vs-` (homepage vertical slider), unprefixed for genuinely shared components (`.site-header`, `.site-footer`, `.btn`, `.product-card`, `.blog-card`).
- Header/mobile-menu/footer markup is intentionally copy-pasted into every HTML file (no templating system exists) — any edit to shared chrome must be manually replicated across all 5 HTML files or pages will visually diverge.
- Data lives in hardcoded JS arrays (`data.js`, `blog-data.js`), not an API/CMS — adding a product/post means editing those files directly.
- Forms have no backend — they resolve to `mailto:`/`wa.me` links, not fetch/XHR calls.
- Source comments in this codebase explain **why**, not what (e.g. the hero.css pinned-hero comment, the product-details.css `object-fit` rationale, the `optimize-images.mjs`/`generate-hero-video.mjs` asset-choice comments) — match that style for any new non-obvious code.

## 11. Important Warnings

- **`.site-header` rules in `header.css` affect all 5 pages.** Any change to header position, sizing, or stacking affects the whole site, not just one page.
- **`.hero` / `.hero-spacer` / `.page-wrap` rules in `hero.css` affect both index.html and product-details.html identically** — these are the only two pages that load `hero.css`, and they share those exact class names with no scoping. A change meant only for product-details.html (like the upcoming header/hero separation) must not edit these shared selectors directly — see the scoping approach described in §8.
- **`main.js`'s `hasHero` check** (`!!document.querySelector(".hero")`) determines whether the header starts transparent or always-solid on load. If a future change renames `.hero` or removes it from a page without updating this check's assumptions, the header's transparent-over-hero behavior could silently break.
- **`data.js` and `blog-data.js` are multi-page dependencies.** `data.js` is consumed by index.html, products.html, product-details.html, and blog-details.html (for the related-product CTA link); `blog-data.js` is consumed by index.html, blog.html, and blog-details.html. Renaming or removing any exported helper (`getProduct`, `getCategory`, `getProductsByCategory`, `getRelatedProducts`, `getBlogPost`, `getBlogCategory`, `getRelatedBlogPosts`, `formatBlogDate`) requires checking every consumer.
- **Footer markup differs slightly per page** (which element carries `id="contact"`, and product-details.html nests its footer one level deeper inside `.page-wrap`) — don't assume a straight copy-paste of one page's footer onto another without checking anchor links (`#contact`) still resolve correctly.
- **index.html has inline `<style>`/`<script>` blocks** not mirrored in `css/`/`js/` (the vertical-slider CSS, and three inline scripts) — changes to homepage-only behavior may need to happen inside index.html itself, not in the shared files.

## 12. Future Claude Instructions

- Always read this `CLAUDE.md` before making changes to this repository.
- Treat `CLAUDE.md` as cached project context — do not re-scan the entire repository for every request.
- Inspect only the files directly relevant to the requested change unless this file's context is insufficient for the task.
- If an implementation change makes part of this file outdated, update the relevant section as part of that change.
- Preserve existing functionality unless the user explicitly requests a change.
- Prefer minimal, targeted modifications over unnecessary refactoring.
- Never redesign unrelated sections while implementing a requested change.
- Check both desktop and mobile implications before modifying shared CSS (`base.css`, `header.css`, `footer.css`, `hero.css`).
- Before changing a global/shared component (header, footer, hero, `.reveal`, `.btn*`, `data.js`/`blog-data.js` helpers), determine which other pages use it (see §11) so the change doesn't silently break them.
