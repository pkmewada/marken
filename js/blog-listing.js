const blogGrid = document.querySelector(".blog-grid");
const blogEmptyState = document.querySelector(".blog-empty");
const blogSearchInput = document.querySelector(".blog-toolbar__search");
const blogCategoryButtons = document.querySelectorAll(".blog-toolbar__categories button");

const blogParams = new URLSearchParams(window.location.search);
let activeBlogCategory = blogParams.get("category") || "all";
let blogQuery = "";

function renderBlogGrid() {
  const filtered = BLOG_POSTS.filter((p) => {
    const matchesCategory = activeBlogCategory === "all" || p.category === activeBlogCategory;
    const q = blogQuery.trim().toLowerCase();
    const matchesQuery = !q || p.title.toLowerCase().includes(q) || p.excerpt.toLowerCase().includes(q);
    return matchesCategory && matchesQuery;
  });

  blogGrid.innerHTML = filtered.map(blogCardHTML).join("");
  blogEmptyState.style.display = filtered.length ? "none" : "block";

  blogCategoryButtons.forEach((btn) => {
    btn.classList.toggle("is-active", btn.dataset.category === activeBlogCategory);
  });

  blogGrid.querySelectorAll(".reveal").forEach((el) => el.classList.add("is-visible"));
}

blogCategoryButtons.forEach((btn) => {
  btn.addEventListener("click", () => {
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
});

blogSearchInput.addEventListener("input", (e) => {
  blogQuery = e.target.value;
  renderBlogGrid();
});

renderBlogGrid();
