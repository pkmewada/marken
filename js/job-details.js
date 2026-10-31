// Job details: /careers/{slug} (rewritten to job-details.html?slug=) from api/careers.php.
const jdSlug = decodeURIComponent(
  location.pathname.split("/careers/")[1]?.replace(/\/$/, "") || new URLSearchParams(location.search).get("slug") || ""
);
const jdIcons = {
  location: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
  type: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="1"/><path d="M9 7V4h6v3M3 12h18"/></svg>',
  experience: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>',
  deadline: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="1"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
};

function renderJob(job) {
  document.title = `${job.title} | Careers at MarkEn`;
  document.querySelector('meta[name="description"]').content =
    `${job.title} (${job.employmentType}, ${job.location}) — ${job.department} at MarkEn. View the role and apply online.`;

  document.getElementById("jd-crumb").textContent = job.title;
  document.getElementById("jd-department").textContent = job.department;
  document.getElementById("jd-title").textContent = job.title;
  const chips = [
    [jdIcons.location, job.location],
    [jdIcons.type, job.employmentType],
    [jdIcons.experience, job.experience && `Experience: ${job.experience}`],
    [jdIcons.deadline, job.deadline && `Apply by ${formatDate(job.deadline)}`],
  ].filter(([, text]) => text);
  document.getElementById("jd-chips").innerHTML = chips.map(([icon, text]) => `<span>${icon}${esc(text)}</span>`).join("");

  document.getElementById("jd-description").innerHTML = paragraphs(job.description);
  const list = (items) => items.map((i) => `<li>${esc(i)}</li>`).join("");
  document.getElementById("jd-responsibilities").innerHTML = list(job.responsibilities);
  document.getElementById("jd-qualifications").innerHTML = list(job.qualifications);
  if (job.applicationInstructions) {
    document.getElementById("jd-instructions").innerHTML = paragraphs(job.applicationInstructions);
  } else {
    document.getElementById("jd-instructions-wrap").hidden = true;
  }

  const summary = [
    ["Department", job.department], ["Location", job.location], ["Employment Type", job.employmentType],
    ["Experience", job.experience], ["Application Deadline", job.deadline && formatDate(job.deadline)],
  ].filter(([, v]) => v);
  document.getElementById("jd-summary").innerHTML = summary.map(([k, v]) => `<dt>${k}</dt><dd>${esc(v)}</dd>`).join("");
  document.getElementById("jd-layout").hidden = false;
  document.getElementById("jd-apply").addEventListener("click", () => openApplication(job));

  // Structured data for job search engines
  const ld = document.createElement("script");
  ld.type = "application/ld+json";
  ld.textContent = JSON.stringify({
    "@context": "https://schema.org",
    "@type": "JobPosting",
    title: job.title,
    description: [job.description, ...job.responsibilities, ...job.qualifications].join("\n"),
    datePosted: job.datePosted,
    validThrough: job.deadline || undefined,
    employmentType: job.employmentType.toUpperCase().replace(/[^A-Z]/g, "_"),
    hiringOrganization: { "@type": "Organization", name: "MarkEn", sameAs: new URL(".", document.baseURI).href },
    jobLocation: { "@type": "Place", address: { "@type": "PostalAddress", addressLocality: job.location, addressCountry: "IN" } },
  });
  document.head.appendChild(ld);
}

function openApplication(job) {
  const dialog = openLightbox(document.getElementById("jd-form-template").innerHTML, "lightbox--narrow");
  dialog.querySelector("[data-job-title]").textContent = job.title;
  const form = dialog.querySelector("form");
  const status = form.querySelector(".contact-us__status");
  const button = form.querySelector('button[type="submit"]');
  const resume = form.querySelector('input[name="resume"]');

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const file = resume.files[0];
    if (!/\.(pdf|docx?)$/i.test(file.name)) { showFormMessage(status, "Please upload your resume as a PDF, DOC or DOCX file.", "error"); return; }
    if (file.size > 5 * 1024 * 1024) { showFormMessage(status, "Your resume is too large. Please upload a file under 5 MB.", "error"); return; }

    button.disabled = true;
    showFormMessage(status, "Submitting your application…");
    const body = withBotCheck(new FormData(form));
    body.append("job", job.slug);
    try {
      const res = await fetch("api/job-application.php", { method: "POST", body });
      const data = await res.json().catch(() => ({ message: "Something went wrong. Please try again." }));
      if (!data.ok) throw new Error(data.message);
      form.outerHTML = `<p class="contact-us__status is-success">${esc(data.message)}</p>`;
    } catch (err) {
      showFormMessage(status, err.message || "Something went wrong. Please try again.", "error");
      button.disabled = false;
    }
  });
}

function renderJobMessage(title, html, notFound) {
  document.getElementById("jd-title").textContent = title;
  document.getElementById("jd-crumb").textContent = title;
  const box = document.getElementById("jd-message");
  box.innerHTML = `<div class="pg-empty">${html}</div>`;
  box.hidden = false;
  if (notFound) {
    document.title = `${title} | MarkEn Careers`;
    document.head.appendChild(Object.assign(document.createElement("meta"), { name: "robots", content: "noindex" }));
  }
}

fetchApi("careers", { slug: jdSlug })
  .then(({ job }) => renderJob(job))
  .catch((err) => err.status === 404
    ? renderJobMessage("Position not available", `<strong>${esc(err.message)}</strong><a href="careers">See all open positions &rarr;</a>`, true)
    : renderJobMessage("Job unavailable", esc(err.message)));
