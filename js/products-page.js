function productCardHTML(product) {
  const category = getCategory(product.category);
  return `
    <a href="product-details.html?slug=${product.slug}" class="product-card">
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
const categoryButtons = document.querySelectorAll(".products-toolbar__categories button");

const params = new URLSearchParams(window.location.search);
let activeCategory = params.get("category") || "all";
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
    const url = new URL(window.location);
    if (activeCategory === "all") {
      url.searchParams.delete("category");
    } else {
      url.searchParams.set("category", activeCategory);
    }
    window.history.replaceState({}, "", url);
    render();
  });
});

searchInput.addEventListener("input", (e) => {
  query = e.target.value;
  render();
});

render();
