// Shared blog helpers. Posts come from api/blog.php (MySQL) — every value is escaped before it
// reaches innerHTML because it is admin-entered text, not trusted markup.
function escBlog(s) {
  return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

function formatBlogDate(iso) {
  if (!iso) return "";
  const d = new Date(iso + "T00:00:00");
  return d.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
}

/** GET api/blog.php with the given query params; resolves to the JSON body or throws with a user-facing message. */
async function fetchBlog(params = {}) {
  const res = await fetch("api/blog.php?" + new URLSearchParams(params), { credentials: "same-origin" });
  const data = await res.json().catch(() => null);
  if (!data || !data.ok) {
    const err = new Error((data && data.message) || "Articles are unavailable right now. Please try again later.");
    err.status = res.status;
    throw err;
  }
  return data;
}

function blogCardHTML(post) {
  return `
    <a href="blog-details.html?slug=${encodeURIComponent(post.slug)}" class="blog-card reveal">
      <div class="blog-card__frame">
        <img src="${escBlog(post.image)}" alt="${escBlog(post.title)}" loading="lazy" />
      </div>
      <div class="blog-card__info">
        <span class="blog-card__meta">
          <span class="blog-card__category">${escBlog(post.categoryName)}</span>
          <span class="blog-card__date">${formatBlogDate(post.date)}</span>
        </span>
        <h3 class="blog-card__title">${escBlog(post.title)}</h3>
        <p class="blog-card__excerpt">${escBlog(post.excerpt)}</p>
        <span class="blog-card__link">Read Article <i>&rarr;</i></span>
      </div>
    </a>
  `;
}
