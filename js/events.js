// Events listing: upcoming/ongoing and past (completed/cancelled) events from api/events.php.
const evPhaseLabel = { upcoming: "Upcoming", ongoing: "Happening Now", completed: "Completed", cancelled: "Cancelled" };

/** "12 – 14 February 2027" / "12 February 2027" */
function eventDateRange(e) {
  if (!e.endDate || e.endDate === e.startDate) return formatDate(e.startDate);
  const [s, end] = [new Date(e.startDate + "T00:00:00"), new Date(e.endDate + "T00:00:00")];
  return s.getMonth() === end.getMonth() && s.getFullYear() === end.getFullYear()
    ? `${s.getDate()} – ${formatDate(e.endDate)}`
    : `${formatDate(e.startDate)} – ${formatDate(e.endDate)}`;
}

function eventCardHTML(e) {
  const d = new Date(e.startDate + "T00:00:00");
  const time = e.startTime ? ` · ${formatTime(e.startTime)}${e.endTime ? ` – ${formatTime(e.endTime)}` : ""}` : "";
  return `
    <a href="events/${encodeURIComponent(e.slug)}" class="ev-card reveal">
      <div class="ev-card__media">
        <img src="${esc(e.image)}" alt="${esc(e.title)}" loading="lazy" />
        <span class="ev-card__date"><strong>${d.getDate()}</strong><span>${d.toLocaleDateString("en-IN", { month: "short", year: "numeric" })}</span></span>
        ${e.phase !== "upcoming" ? `<span class="pg-badge pg-badge--${e.phase}">${evPhaseLabel[e.phase]}</span>` : ""}
      </div>
      <div class="ev-card__body">
        <span class="ev-card__meta">${esc(eventDateRange(e))}${esc(time)}</span>
        <h3>${esc(e.title)}</h3>
        <span class="ev-card__meta">${esc(e.venue)}, ${esc(e.city)}</span>
        <p>${esc(e.excerpt)}</p>
        <span class="ev-card__link">View Details <i>&rarr;</i></span>
      </div>
    </a>`;
}

const evUpcoming = document.getElementById("ev-upcoming");
if (evUpcoming) {
  fetchApi("events")
    .then(({ upcoming, past }) => {
      evUpcoming.innerHTML = upcoming.length
        ? upcoming.map(eventCardHTML).join("")
        : '<div class="pg-empty"><strong>No upcoming events are scheduled right now.</strong>New exhibitions and launches are announced here — please check back soon.</div>';
      if (past.length) {
        document.getElementById("ev-past").innerHTML = past.map(eventCardHTML).join("");
        document.getElementById("ev-past-section").hidden = false;
      }
      revealInjected();
    })
    .catch((err) => (evUpcoming.innerHTML = `<div class="pg-empty">${esc(err.message)}</div>`));
}
