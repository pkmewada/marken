// Event details: /events/{slug} (rewritten to event-details.html?slug=) from api/events.php.
// Uses evPhaseLabel / eventDateRange from js/events.js.
const edSlug = decodeURIComponent(
  location.pathname.split("/events/")[1]?.replace(/\/$/, "") || new URLSearchParams(location.search).get("slug") || ""
);
const edIcons = {
  date: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="1"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
  time: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
};

function renderEvent(e) {
  document.title = `${e.title} | MarkEn Events`;
  document.querySelector('meta[name="description"]').content = e.excerpt;

  const time = e.startTime ? `${formatTime(e.startTime)}${e.endTime ? ` – ${formatTime(e.endTime)}` : ""}` : "";
  const place = [e.venue, e.address, e.city].filter(Boolean).join(", ");
  const mapQuery = e.address ? `${e.address}, ${e.city}` : `${e.venue}, ${e.city}`;

  document.getElementById("ed-crumb").textContent = e.title;
  document.getElementById("ed-phase").innerHTML = `<span class="pg-badge pg-badge--${e.phase}">${evPhaseLabel[e.phase]}</span>`;
  document.getElementById("ed-title").textContent = e.title;
  document.getElementById("ed-chips").innerHTML = [
    [edIcons.date, eventDateRange(e)], [edIcons.time, time], [edIcons.pin, `${e.venue}, ${e.city}`],
  ].filter(([, t]) => t).map(([icon, t]) => `<span>${icon}${esc(t)}</span>`).join("");

  const banner = document.getElementById("ed-banner");
  banner.src = e.image;
  banner.alt = e.title;
  document.getElementById("ed-description").innerHTML = paragraphs(e.description);

  if (e.gallery.length) {
    const gallery = document.getElementById("ed-gallery");
    gallery.innerHTML = e.gallery.map((src, i) =>
      `<button type="button" data-index="${i}" aria-label="View photo ${i + 1}"><img src="${esc(src)}" alt="" loading="lazy" /></button>`).join("");
    gallery.addEventListener("click", (ev) => {
      const btn = ev.target.closest("[data-index]");
      if (btn) openGallery(e.gallery.map((src) => ({ src, title: e.title })), +btn.dataset.index, true);
    });
    document.getElementById("ed-gallery-wrap").hidden = false;
  }

  document.getElementById("ed-map").src = mapEmbedUrl(mapQuery);
  document.getElementById("ed-directions").href = directionsUrl(e.mapUrl, mapQuery);
  const contact = e.contactInfo || "Email info@markenworld.com or call +91 91 3030 5959.";
  document.getElementById("ed-details").innerHTML = [
    ["Date", eventDateRange(e)], ["Time", time], ["Venue & Address", place], ["Contact & Enquiries", contact],
  ].filter(([, v]) => v).map(([k, v]) => `<dt>${k}</dt><dd>${esc(v)}</dd>`).join("");
  document.getElementById("ed-content").hidden = false;

  const ld = document.createElement("script");
  ld.type = "application/ld+json";
  ld.textContent = JSON.stringify({
    "@context": "https://schema.org",
    "@type": "Event",
    name: e.title,
    description: e.excerpt,
    image: new URL(e.image, document.baseURI).href,
    startDate: e.startTime ? `${e.startDate}T${e.startTime}` : e.startDate,
    endDate: (e.endDate || e.startDate) + (e.endTime ? `T${e.endTime}` : ""),
    eventStatus: e.phase === "cancelled" ? "https://schema.org/EventCancelled" : "https://schema.org/EventScheduled",
    eventAttendanceMode: "https://schema.org/OfflineEventAttendanceMode",
    location: { "@type": "Place", name: e.venue, address: [e.address, e.city].filter(Boolean).join(", ") },
    organizer: { "@type": "Organization", name: "MarkEn", url: new URL(".", document.baseURI).href },
  });
  document.head.appendChild(ld);
}

function renderEventMessage(title, html, notFound) {
  document.getElementById("ed-title").textContent = title;
  document.getElementById("ed-crumb").textContent = title;
  const box = document.getElementById("ed-message");
  box.innerHTML = `<div class="pg-empty">${html}</div>`;
  box.hidden = false;
  if (notFound) {
    document.title = `${title} | MarkEn Events`;
    document.head.appendChild(Object.assign(document.createElement("meta"), { name: "robots", content: "noindex" }));
  }
}

fetchApi("events", { slug: edSlug })
  .then(({ event }) => renderEvent(event))
  .catch((err) => err.status === 404
    ? renderEventMessage("Event not found", '<strong>This event does not exist or is no longer available.</strong><a href="events">See all events &rarr;</a>', true)
    : renderEventMessage("Event unavailable", esc(err.message)));
