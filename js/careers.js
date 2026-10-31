// Careers: open jobs from api/careers.php, filtered client-side by keyword, department and location.
const crList = document.getElementById("cr-jobs");
const crCount = document.getElementById("cr-count");
const crSearch = document.getElementById("cr-search");
const crDept = document.getElementById("cr-department");
const crLoc = document.getElementById("cr-location");
let crJobs = [];

const fillOptions = (select, values) =>
  [...new Set(values)].sort().forEach((v) => select.add(new Option(v, v)));

function renderJobs() {
  const q = crSearch.value.trim().toLowerCase();
  const jobs = crJobs.filter((j) =>
    (!crDept.value || j.department === crDept.value)
    && (!crLoc.value || j.location === crLoc.value)
    && (!q || `${j.title} ${j.department} ${j.location} ${j.employmentType}`.toLowerCase().includes(q)));

  crCount.textContent = jobs.length === 1 ? "1 open position" : `${jobs.length} open positions`;
  crList.innerHTML = jobs.length
    ? jobs.map((j) => `
      <article class="cr-job">
        <div class="cr-job__title">
          <h3>${esc(j.title)}</h3>
          <span>${esc(j.department)}</span>
        </div>
        <div class="cr-job__meta"><small>Location</small>${esc(j.location)}</div>
        <div class="cr-job__meta"><small>${esc(j.employmentType)}</small>${esc(j.experience || "—")}</div>
        <a href="careers/${encodeURIComponent(j.slug)}" class="btn btn-dark">View Details</a>
      </article>`).join("")
    : `<div class="pg-empty">${crJobs.length
        ? '<strong>No positions match your search.</strong>Try a different keyword or clear the filters.'
        : '<strong>There are no open positions right now.</strong>New roles are posted here as they open. You are welcome to send your resume to <a href="mailto:info@markenworld.com">info@markenworld.com</a>.'}</div>`;
}

fetchApi("careers")
  .then(({ jobs }) => {
    crJobs = jobs;
    fillOptions(crDept, jobs.map((j) => j.department));
    fillOptions(crLoc, jobs.map((j) => j.location));
    renderJobs();
  })
  .catch((err) => {
    crCount.textContent = "";
    crList.innerHTML = `<div class="pg-empty">${esc(err.message)}</div>`;
  });

[crSearch, crDept, crLoc].forEach((el) => el.addEventListener("input", renderJobs));
