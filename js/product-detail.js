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

const slug = new URLSearchParams(window.location.search).get("slug");
const product = slug ? getProduct(slug) : null;

if (!product) {
  window.location.replace("products.html");
} else {
  const category = getCategory(product.category);
  const enquirySubject = encodeURIComponent(`Enquiry: ${product.name}`);

  document.title = `${product.name} | MarkEn`;
  document.querySelector('meta[name="description"]').setAttribute(
    "content",
    product.description ? product.description.slice(0, 155) : `${product.name} — ${category.name} by MarkEn.`
  );

  document.getElementById("breadcrumb-category").textContent = category.name;
  document.getElementById("breadcrumb-category").href = `products.html?category=${category.slug}`;
  document.getElementById("breadcrumb-name").textContent = product.name;

  document.getElementById("product-image").src = product.image;
  document.getElementById("product-image").alt = product.name;
  document.getElementById("product-category").textContent = category.name;
  document.getElementById("product-name").textContent = product.name;
  document.getElementById("product-desc").textContent =
    product.description ||
    "Full technical specifications for this product are available on request. Contact our team for detailed documentation.";
  document.getElementById("enquiry-link").href = `mailto:info@markenworld.com?subject=${enquirySubject}`;

  if (product.features) {
    document.getElementById("features-section").style.display = "";
    document.getElementById("features-grid").innerHTML = product.features
      .map((f) => `<div class="reveal"><h3>${f.title}</h3><p>${f.description}</p></div>`)
      .join("");
  }

  if (product.applications) {
    document.getElementById("applications-section").style.display = "";
    document.getElementById("applications-list").innerHTML = product.applications
      .map((a) => `<li>${a}</li>`)
      .join("");
  }

  const related = getRelatedProducts(product);
  if (related.length) {
    document.getElementById("related-section").style.display = "";
    document.getElementById("related-grid").innerHTML = related.map(productCardHTML).join("");
  }

  // Re-run reveal observer for content injected after main.js already ran
  const revealTargets = document.querySelectorAll(".reveal:not(.is-visible)");
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -8% 0px" }
  );
  revealTargets.forEach((el) => observer.observe(el));
}
