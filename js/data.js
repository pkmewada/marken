// Lookup helpers over the CATEGORIES / PRODUCTS globals, which api/catalog.php
// serves from the database. That script must load before this one.

function getCategory(slug) {
  return CATEGORIES.find((c) => c.slug === slug);
}
function getProduct(slug) {
  return PRODUCTS.find((p) => p.slug === slug);
}
function getProductsByCategory(slug) {
  return PRODUCTS.filter((p) => p.category === slug);
}
function getRelatedProducts(product, limit = 4) {
  return PRODUCTS.filter((p) => p.category === product.category && p.slug !== product.slug).slice(0, limit);
}

// Product card shared by products.html and the homepage featured grid (styles: css/products.css).
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
