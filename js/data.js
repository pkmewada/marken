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
