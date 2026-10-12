function productCardHTML(product) {
  const category = getCategory(product.category);
  return `
    <a href="product-details/${product.slug}" class="product-card">
      <div class="product-card__frame">
        <img src="${product.image}" alt="${product.name}" loading="lazy" />
      </div>
      <div class="product-card__info">
        <span class="product-card__category">${category ? category.name : ""}</span>
        <h3 class="product-card__name">${product.name}</h3>
        <span class="product-card__link">View Product <i>&rarr;</i></span>
      </div>
    </a>
  `;
}

const grid = document.querySelector(".products-grid");
const emptyState = document.querySelector(".products-empty");
const searchInput = document.querySelector(".products-toolbar__search");
const categoryBar = document.querySelector(".products-toolbar__categories");

// One button per category in the admin database (after the static "All Products").
categoryBar.append(
  ...CATEGORIES.map((cat) => {
    const btn = document.createElement("button");
    btn.dataset.category = cat.slug;
    btn.textContent = cat.name;
    return btn;
  })
);
const categoryButtons = categoryBar.querySelectorAll("button");

// The page's <base> points at the site root, so in-page "#section" links must
// be pinned to the current URL or they would jump to the homepage.
document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((a) => {
  a.href = window.location.pathname + window.location.search + a.getAttribute("href");
});

// Support /products/category (clean URL) and legacy ?category= query param
const _pathCategory = window.location.pathname.split("/products/")[1]?.replace(/\/$/, "");
let activeCategory = decodeURIComponent(_pathCategory || new URLSearchParams(window.location.search).get("category") || "") || "all";
if (activeCategory !== "all" && !getCategory(activeCategory)) activeCategory = "all";
let query = "";

function render() {
  const filtered = PRODUCTS.filter((p) => {
    const matchesCategory = activeCategory === "all" || p.category === activeCategory;
    const matchesQuery = p.name.toLowerCase().includes(query.trim().toLowerCase());
    return matchesCategory && matchesQuery;
  });

  grid.innerHTML = filtered.map(productCardHTML).join("");
  emptyState.style.display = filtered.length ? "none" : "block";

  categoryButtons.forEach((btn) => {
    btn.classList.toggle("is-active", btn.dataset.category === activeCategory);
  });
}

categoryButtons.forEach((btn) => {
  btn.addEventListener("click", () => {
    activeCategory = btn.dataset.category;
    // Resolved against <base> (the site root), so a sub-folder install keeps its prefix.
    const path = activeCategory === "all" ? "products" : `products/${encodeURIComponent(activeCategory)}`;
    window.history.replaceState({}, "", new URL(path, document.baseURI));
    render();
  });
});

searchInput.addEventListener("input", (e) => {
  query = e.target.value;
  render();
});

render();
