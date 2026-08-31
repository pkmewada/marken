// product-details.html — Apple-style product page (test).
// Reuses the same product/category data as product-detail.js; only the
// presentation differs.

const ICONS = {
  badge:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6.5"/><path d="M8.5 14.5 7 22l5-2.5 5 2.5-1.5-7.5"/></svg>',
};

function relatedMediaAlt(name) {
  return `${name} — MarkEn`;
}

const slug = new URLSearchParams(window.location.search).get("slug");
const product = slug ? getProduct(slug) : null;

if (!product) {
  window.location.replace("products.html");
} else {
  const category = getCategory(product.category);
  const phone = "+919130305959";
  const waNumber = phone.replace(/[^\d]/g, "");
  const mailto = (subject, body) =>
    `mailto:info@markenworld.com?subject=${encodeURIComponent(subject)}${body ? `&body=${encodeURIComponent(body)}` : ""}`;

  document.title = `${product.name} | MarkEn`;
  document.querySelector('meta[name="description"]').setAttribute(
    "content",
    product.description ? product.description.slice(0, 155) : `${product.name} — ${category.name} by MarkEn.`
  );

  // Breadcrumb
  document.getElementById("pd-breadcrumb-category").textContent = category.name;
  document.getElementById("pd-breadcrumb-category").href = `products.html?category=${category.slug}`;
  document.getElementById("pd-breadcrumb-name").textContent = product.name;

  // Hero
  document.getElementById("pd-hero-image").src = product.image;
  document.getElementById("pd-hero-image").alt = relatedMediaAlt(product.name);
  document.getElementById("pd-hero-category").textContent = category.name;
  document.getElementById("pd-hero-name").textContent = product.name;

  // Hero CTAs
  document.getElementById("pd-cta-quote").href = mailto(`Quote Request: ${product.name}`);
  document.getElementById("pd-cta-enquire").href = mailto(`Enquiry: ${product.name}`);
  document.getElementById("pd-cta-whatsapp").href = `https://wa.me/${waNumber}?text=${encodeURIComponent(
    `Hi MarkEn, I'm interested in ${product.name}.`
  )}`;

  // Media slider — the product's own photo, plus its category banner as a
  // second angle. Both are real assets already used elsewhere on the site.
  const slides = [{ src: product.image, alt: product.name }];
  if (category.image && category.image !== product.image) {
    slides.push({ src: category.image, alt: `${category.name} — MarkEn` });
  }
  const stage = document.querySelector(".pd-slider__stage");
  const dotsEl = document.getElementById("pd-slider-dots");
  slides.forEach((slide, i) => {
    const img = document.createElement("img");
    img.className = "pd-slider__img" + (i === 0 ? " is-active" : "");
    img.src = slide.src;
    img.alt = slide.alt;
    stage.insertBefore(img, document.getElementById("pd-slider-prev"));

    const dot = document.createElement("button");
    dot.setAttribute("aria-label", `Show image ${i + 1}`);
    if (i === 0) dot.classList.add("is-active");
    dotsEl.appendChild(dot);
  });
  if (slides.length < 2) {
    document.querySelectorAll(".pd-slider__arrow, .pd-slider__dots").forEach((el) => (el.style.display = "none"));
  } else {
    let current = 0;
    const imgs = () => stage.querySelectorAll(".pd-slider__img");
    const dots = () => dotsEl.querySelectorAll("button");
    function showSlide(index) {
      current = (index + slides.length) % slides.length;
      imgs().forEach((el, i) => el.classList.toggle("is-active", i === current));
      dots().forEach((el, i) => el.classList.toggle("is-active", i === current));
    }
    document.getElementById("pd-slider-prev").addEventListener("click", () => showSlide(current - 1));
    document.getElementById("pd-slider-next").addEventListener("click", () => showSlide(current + 1));
    dots().forEach((dot, i) => dot.addEventListener("click", () => showSlide(i)));
  }

  // Applications (visual)
  const applicationsSection = document.getElementById("pd-applications-section");
  if (product.applications && product.applications.length) {
    document.getElementById("pd-applications-image").src = category.image;
    document.getElementById("pd-applications-image").alt = `${category.name} — MarkEn`;
    document.getElementById("pd-applications-tags").innerHTML = product.applications
      .map((a) => `<li>${a}</li>`)
      .join("");
  } else {
    applicationsSection.style.display = "none";
  }

  // Certifications (company-wide, shown on every product)
  const certifications = ["WHO PQS", "ISO 9001:2015", "ISO 13485:2016", "ISO 14001:2015", "WHO GMP"];
  document.getElementById("pd-certifications-grid").innerHTML = certifications
    .map((c) => `<span class="pd-cert-badge">${ICONS.badge}${c}</span>`)
    .join("");

  // Documentation — no files exist to serve, so each card routes to a
  // pre-filled enquiry email requesting that document for this product.
  document.querySelectorAll(".pd-doc-card a").forEach((a) => {
    a.href = mailto(`${a.dataset.doc} Request: ${product.name}`, `Please send the ${a.dataset.doc.toLowerCase()} for ${product.name}.`);
  });

  // FAQ accordion
  document.querySelectorAll(".pd-faq__item").forEach((item) => {
    const question = item.querySelector(".pd-faq__question");
    const answer = item.querySelector(".pd-faq__answer");
    question.addEventListener("click", () => {
      const isOpen = item.classList.toggle("is-open");
      answer.style.maxHeight = isOpen ? `${answer.scrollHeight}px` : "";
    });
  });

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
