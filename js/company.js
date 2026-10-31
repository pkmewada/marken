// Shared helpers for the company pages (about, certifications, careers, service center, events).
// Loaded after main.js. Data from api/*.php is admin-entered text, so every value is escaped
// before it reaches innerHTML.
function esc(s) {
  return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

/** Plain admin text → escaped <p> per blank-line-separated paragraph (single newlines become <br>). */
function paragraphs(text) {
  return String(text || "").trim().split(/\n\s*\n/).filter(Boolean)
    .map((p) => `<p>${esc(p.trim()).replace(/\n/g, "<br>")}</p>`).join("");
}

/** GET api/<endpoint>.php; resolves to the JSON body or throws an Error (with .status) carrying a user-facing message. */
async function fetchApi(endpoint, params = {}) {
  const res = await fetch(`api/${endpoint}.php?` + new URLSearchParams(params), { credentials: "same-origin" });
  const data = await res.json().catch(() => null);
  if (!data || !data.ok) {
    const err = new Error((data && data.message) || "This content is unavailable right now. Please try again later.");
    err.status = res.status;
    throw err;
  }
  return data;
}

function formatDate(iso, options = { day: "numeric", month: "long", year: "numeric" }) {
  return iso ? new Date(iso + "T00:00:00").toLocaleDateString("en-IN", options) : "";
}

function formatTime(hhmm) {
  if (!hhmm) return "";
  const [h, m] = hhmm.split(":").map(Number);
  return new Date(2000, 0, 1, h, m).toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit" });
}

/** "Get Directions": the admin's Google Maps link, else a directions search for the address. */
function directionsUrl(mapUrl, query) {
  return mapUrl || `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(query)}`;
}
/** Keyless Google Maps embed for an address. */
function mapEmbedUrl(query) {
  return `https://maps.google.com/maps?q=${encodeURIComponent(query)}&output=embed`;
}

// Content injected after main.js ran needs its own reveal observer.
function revealInjected(scope = document) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
  scope.querySelectorAll(".reveal:not(.is-visible)").forEach((el) => observer.observe(el));
}

// Detail pages (/careers/slug, /events/slug) set <base> to the site root, so in-page "#x" links
// must be pinned to the current URL or they would jump to the homepage.
function pinAnchorLinks(scope = document) {
  scope.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((a) => {
    a.href = location.pathname + location.search + a.getAttribute("href");
  });
}
if (document.querySelector("base")) pinAnchorLinks();

/** Modal <dialog> with a close button; removed from the DOM when closed. Returns the dialog. */
function openLightbox(html, className = "") {
  const dialog = document.createElement("dialog");
  dialog.className = `lightbox ${className}`;
  dialog.innerHTML = `<button type="button" class="lightbox__close" aria-label="Close">&times;</button><div class="lightbox__body">${html}</div>`;
  dialog.querySelector(".lightbox__close").addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (e) => { if (e.target === dialog) dialog.close(); });   // backdrop click
  dialog.addEventListener("close", () => { dialog.remove(); document.body.classList.remove("has-lightbox"); });
  document.body.appendChild(dialog);
  document.body.classList.add("has-lightbox");
  dialog.showModal();
  return dialog;
}

/** Image gallery lightbox: items = [{ src, title, text, label }], with prev/next + arrow keys. */
function openGallery(items, start = 0, photo = false) {
  let index = start;
  const dialog = openLightbox(`
    <div class="lightbox-gallery${photo ? " lightbox-gallery--photo" : ""}">
      <div class="lightbox-gallery__media"><img alt="" /></div>
      <div class="lightbox-gallery__info">
        ${photo ? "" : '<span class="eyebrow"></span><h3></h3><p></p>'}
        <div class="lightbox-gallery__nav"${items.length < 2 ? " hidden" : ""}>
          <button type="button" data-step="-1" aria-label="Previous">&larr;</button>
          <button type="button" data-step="1" aria-label="Next">&rarr;</button>
          <span></span>
        </div>
      </div>
    </div>`);
  const show = () => {
    const item = items[index];
    const img = dialog.querySelector("img");
    img.src = item.src;
    img.alt = item.title || "";
    if (!photo) {
      dialog.querySelector(".eyebrow").textContent = item.label || "";
      dialog.querySelector("h3").textContent = item.title || "";
      dialog.querySelector(".lightbox-gallery__info p").textContent = item.text || "";
    }
    dialog.querySelector(".lightbox-gallery__nav span").textContent = `${index + 1} / ${items.length}`;
  };
  const step = (d) => { index = (index + d + items.length) % items.length; show(); };
  dialog.querySelectorAll("[data-step]").forEach((b) => b.addEventListener("click", () => step(+b.dataset.step)));
  dialog.addEventListener("keydown", (e) => {
    if (e.key === "ArrowLeft") step(-1);
    if (e.key === "ArrowRight") step(1);
  });
  show();
}

// Static galleries: <button data-gallery="group" data-src data-title data-text data-label>
document.querySelectorAll("[data-gallery]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const group = [...document.querySelectorAll(`[data-gallery="${btn.dataset.gallery}"]`)];
    openGallery(group.map((b) => ({ ...b.dataset })), group.indexOf(btn));
  });
});

// Video triggers: <button data-youtube="VIDEO_ID">
document.querySelectorAll("[data-youtube]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const id = encodeURIComponent(btn.dataset.youtube);
    openLightbox(`<iframe src="https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0" title="MarkEn video"
      allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`, "lightbox--video");
  });
});
