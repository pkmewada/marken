const blogGrid = document.querySelector(".blog-grid");
const blogEmptyState = document.querySelector(".blog-empty");
const blogSearchInput = document.querySelector(".blog-toolbar__search");
const blogCategoryBar = document.querySelector(".blog-toolbar__categories");

const blogParams = new URLSearchParams(window.location.search);
let activeBlogCategory = blogParams.get("category") || "all";
let blogQuery = "";
let blogPosts = [];

function showBlogMessage(text) {
  blogEmptyState.textContent = text;
  blogEmptyState.style.display = "block";
}

function renderBlogGrid() {
  const q = blogQuery.trim().toLowerCase();
  const filtered = blogPosts.filter((p) => {
    const matchesCategory = activeBlogCategory === "all" || p.category === activeBlogCategory;
    const matchesQuery = !q || p.title.toLowerCase().includes(q) || p.excerpt.toLowerCase().includes(q);
    return matchesCategory && matchesQuery;
  });

  blogGrid.innerHTML = filtered.map(blogCardHTML).join("");
  if (filtered.length) blogEmptyState.style.display = "none";
  else showBlogMessage(blogPosts.length ? "No articles match your search." : "No articles have been published yet.");

  blogCategoryBar.querySelectorAll("button").forEach((btn) => {
    btn.classList.toggle("is-active", btn.dataset.category === activeBlogCategory);
  });

  blogGrid.querySelectorAll(".reveal").forEach((el) => el.classList.add("is-visible"));
}

blogCategoryBar.addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-category]");
  if (!btn) return;
  activeBlogCategory = btn.dataset.category;
  const url = new URL(window.location);
  if (activeBlogCategory === "all") {
    url.searchParams.delete("category");
  } else {
    url.searchParams.set("category", activeBlogCategory);
  }
  window.history.replaceState({}, "", url);
  renderBlogGrid();
});

blogSearchInput.addEventListener("input", (e) => {
  blogQuery = e.target.value;
  renderBlogGrid();
});

// Only "All Articles" is static; the rest are the active categories from the admin.
showBlogMessage("Loading articles…");
fetchBlog()
  .then(({ categories, posts }) => {
    blogPosts = posts;
    blogCategoryBar.insertAdjacentHTML(
      "beforeend",
      categories.map((c) => `<button data-category="${escBlog(c.slug)}">${escBlog(c.name)}</button>`).join("")
    );
    renderBlogGrid();
  })
  .catch((err) => showBlogMessage(err.message));
