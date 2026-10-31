// Service center directory: published centers from api/service-centers.php; the filters
// (state → city, free-text location, product category, service type) all apply together.
const scForm = document.getElementById("sc-filters");
const scGrid = document.getElementById("sc-grid");
const scCount = document.getElementById("sc-count");
let scCenters = [];

const scIcons = {
  pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
  phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
  mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/></svg>',
  arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
};
const fullAddress = (c) => [c.address, c.city, c.state].filter(Boolean).join(", ");
const telHref = (phone) => "tel:" + phone.replace(/[^\d+]/g, "");

function setOptions(select, values, keep) {
  const current = keep ? select.value : "";
  select.length = 1;   // keep the "All …" option
  values.forEach(([value, label]) => select.add(new Option(label, value)));
  select.value = values.some(([v]) => v === current) ? current : "";
}

function scCardHTML(c, i) {
  const media = c.image
    ? `<img src="${esc(c.image)}" alt="${esc(c.name)}" loading="lazy" />`
    : '<img src="images/logo.svg" alt="" />';
  const action = (href, icon, label, extra = "") =>
    href ? `<a href="${esc(href)}" ${extra}>${icon}${label}</a>` : `<span class="is-disabled">${icon}${label}</span>`;
  return `
    <article class="sc-card reveal">
      <div class="sc-card__media${c.image ? "" : " sc-card__media--empty"}">
        ${media}
        <span class="pg-badge sc-card__state">${esc(c.city)}</span>
      </div>
      <div class="sc-card__body">
        <h3>${esc(c.name)}</h3>
        <p class="sc-card__line">${scIcons.pin}<span>${esc(fullAddress(c))}</span></p>
        ${c.phone ? `<p class="sc-card__line">${scIcons.phone}<a href="${telHref(c.phone)}">${esc(c.phone)}</a></p>` : ""}
        ${c.email ? `<p class="sc-card__line">${scIcons.mail}<a href="mailto:${esc(c.email)}">${esc(c.email)}</a></p>` : ""}
        ${c.categories.length ? `<div class="pg-tags">${c.categories.map((cat) => `<span>${esc(cat.name)}</span>`).join("")}</div>` : ""}
      </div>
      <div class="sc-card__actions">
        <button type="button" data-details="${i}">${scIcons.info}View Details</button>
        ${action(directionsUrl(c.mapUrl, fullAddress(c)), scIcons.arrow, "Get Directions", 'target="_blank" rel="noopener"')}
        ${action(c.phone && telHref(c.phone), scIcons.phone, "Call Now")}
        ${action(c.email && `mailto:${c.email}`, scIcons.mail, "Email")}
      </div>
    </article>`;
}

function openCenterDetails(c) {
  openLightbox(`
    <iframe class="pg-map" src="${esc(mapEmbedUrl(`${c.name}, ${fullAddress(c)}`))}" title="Map: ${esc(c.name)}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    <div class="sc-detail__body">
      <span class="eyebrow">${esc([c.locality, c.city, c.state].filter(Boolean).join(" · "))}</span>
      <h2>${esc(c.name)}</h2>
      <p class="sc-card__line">${scIcons.pin}<span>${esc(fullAddress(c))}</span></p>
      ${c.phone ? `<p class="sc-card__line">${scIcons.phone}<a href="${telHref(c.phone)}">${esc(c.phone)}</a></p>` : ""}
      ${c.email ? `<p class="sc-card__line">${scIcons.mail}<a href="mailto:${esc(c.email)}">${esc(c.email)}</a></p>` : ""}
      <div class="sc-detail__cols">
        <div><h4>Supported Products</h4>${c.categories.length ? `<div class="pg-tags">${c.categories.map((cat) => `<span>${esc(cat.name)}</span>`).join("")}</div>` : "<p>Contact the center for supported products.</p>"}</div>
        <div><h4>Services</h4>${c.serviceTypes.length ? `<div class="pg-tags">${c.serviceTypes.map((s) => `<span>${esc(s)}</span>`).join("")}</div>` : "<p>Contact the center for details.</p>"}</div>
      </div>
      ${c.serviceInfo ? `<div class="pg-prose sc-detail__info"><h4 class="eyebrow">Service Information</h4>${paragraphs(c.serviceInfo)}</div>` : ""}
      <div class="sc-detail__actions">
        <a href="${esc(directionsUrl(c.mapUrl, fullAddress(c)))}" target="_blank" rel="noopener" class="btn btn-fill">Get Directions</a>
        ${c.phone ? `<a href="${telHref(c.phone)}" class="btn btn-dark">Call Now</a>` : ""}
        ${c.email ? `<a href="mailto:${esc(c.email)}" class="btn btn-dark">Email</a>` : ""}
      </div>
    </div>`, "lightbox--narrow");
}

function renderCenters() {
  const f = Object.fromEntries(new FormData(scForm));
  // Cities follow the chosen state.
  const cities = [...new Set(scCenters.filter((c) => !f.state || c.state === f.state).map((c) => c.city))].sort();
  setOptions(scForm.city, cities.map((c) => [c, c]), true);
  f.city = scForm.city.value;

  const q = (f.q || "").trim().toLowerCase();
  const list = scCenters.filter((c) =>
    (!f.state || c.state === f.state)
    && (!f.city || c.city === f.city)
    && (!f.category || c.categories.some((cat) => cat.slug === f.category))
    && (!f.service || c.serviceTypes.includes(f.service))
    && (!q || `${c.name} ${c.locality} ${c.address} ${c.city} ${c.state}`.toLowerCase().includes(q)));

  scCount.textContent = scCenters.length
    ? `${list.length} of ${scCenters.length} service center${scCenters.length === 1 ? "" : "s"}`
    : "";
  scGrid.innerHTML = list.length
    ? list.map((c) => scCardHTML(c, scCenters.indexOf(c))).join("")
    : `<div class="pg-empty">${scCenters.length
        ? '<strong>No service centers match your filters.</strong>Try another city or clear the filters — or call our helpline on <a href="tel:+919130305959">+91 91 3030 5959</a>.'
        : '<strong>Service center details are being updated.</strong>For service and support, call <a href="tel:+919130305959">+91 91 3030 5959</a> or email <a href="mailto:info@markenworld.com">info@markenworld.com</a>.'}</div>`;
  revealInjected(scGrid);
}

scGrid.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-details]");
  if (btn) openCenterDetails(scCenters[+btn.dataset.details]);
});
scForm.addEventListener("submit", (e) => e.preventDefault());
scForm.addEventListener("input", renderCenters);
scForm.addEventListener("reset", () => setTimeout(renderCenters));   // after the browser clears the fields

fetchApi("service-centers")
  .then(({ centers, categories, serviceTypes }) => {
    scCenters = centers;
    setOptions(scForm.state, [...new Set(centers.map((c) => c.state))].sort().map((s) => [s, s]));
    setOptions(scForm.category, categories.map((c) => [c.slug, c.name]));
    setOptions(scForm.service, serviceTypes.map((s) => [s, s]));
    renderCenters();
  })
  .catch((err) => {
    scCount.textContent = "";
    scGrid.innerHTML = `<div class="pg-empty">${esc(err.message)}</div>`;
  });
