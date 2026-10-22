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

// Support /product-details/slug (clean URL) and legacy ?slug= query param
const _pathSlug = window.location.pathname.split("/product-details/")[1]?.replace(/\/$/, "");
const slug = decodeURIComponent(_pathSlug || new URLSearchParams(window.location.search).get("slug") || "");

// The page's <base> points at the site root, so in-page "#section" links must
// be pinned to the current URL or they would jump to the homepage.
document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((a) => {
  a.href = window.location.pathname + window.location.search + a.getAttribute("href");
});
const product = slug ? getProduct(slug) : null;

if (!product) {
  window.location.replace("products.html");
} else {
  const category = getCategory(product.category);
  const phone = "+919130305959";
  const waNumber = phone.replace(/[^\d]/g, "");
  const mailto = (subject, body) =>
    `mailto:info@markenworld.com?subject=${encodeURIComponent(subject)}${body ? `&body=${encodeURIComponent(body)}` : ""}`;

  // Section order saved in the admin (normalised by api/catalog.php: every key once, hero first).
  // The hero sits outside <main> and the breadcrumb is not a section, so neither ever moves.
  // Nodes are moved before anything is wired up; moving keeps ids, anchors and listeners intact.
  const main = document.querySelector(".page-wrap main");
  (product.sectionOrder || []).forEach((key) => {
    const section = main.querySelector(`:scope > [data-section="${CSS.escape(key)}"]`);
    if (section) main.appendChild(section);
  });

  document.title = `${product.name} | MarkEn`;
  document.querySelector('meta[name="description"]').setAttribute(
    "content",
    product.description ? product.description.slice(0, 155) : `${product.name} — ${category.name} by MarkEn.`
  );

  // Breadcrumb
  document.getElementById("pd-breadcrumb-category").textContent = category.name;
  document.getElementById("pd-breadcrumb-category").href = `products/${encodeURIComponent(category.slug)}`;
  document.getElementById("pd-breadcrumb-name").textContent = product.name;

  // Hero
  // Hero media (set in the admin) fills the whole hero; otherwise the product
  // photo is shown contained, as designed for studio shots.
  const heroImg = document.getElementById("pd-hero-image");
  heroImg.alt = relatedMediaAlt(product.name);
  if (product.heroMedia) {
    document.querySelector(".hero").classList.add("pd-hero--cover");
    if (product.heroMediaType === "video") {
      const video = Object.assign(document.createElement("video"), {
        className: "hero__video",
        src: product.heroMedia,
        poster: product.image,
        muted: true,         // browsers only autoplay muted video
        loop: true,
        playsInline: true,
        autoplay: !window.matchMedia("(prefers-reduced-motion: reduce)").matches,
      });
      video.setAttribute("muted", "");
      video.setAttribute("aria-hidden", "true");
      heroImg.replaceWith(video);
    } else {
      heroImg.src = product.heroMedia;
    }
  } else {
    heroImg.src = product.image;
  }
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
  (product.gallery || []).forEach((src, i) => slides.push({ src, alt: `${product.name} — view ${i + 2}` }));
  if (slides.length === 1 && category.image && category.image !== product.image) {
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
    // Removed rather than hidden so the section-spacing sibling rules in product-details.css skip it.
    applicationsSection.remove();
  }

  // Certifications (company-wide, shown on every product)
  const certifications = ["WHO PQS", "ISO 9001:2015", "ISO 13485:2016", "ISO 14001:2015", "WHO GMP"];
  document.getElementById("pd-certifications-grid").innerHTML = certifications
    .map((c) => `<span class="pd-cert-badge">${ICONS.badge}${c}</span>`)
    .join("");

  // Documentation — "Request by Email" turns into an email field; the server
  // emails the document (attached when uploaded in the admin) and logs the lead.
  const docGrid = document.querySelector(".pd-documentation__grid");
  const docTemplate = docGrid.querySelector(".pd-doc-card");
  const shownDocs = [...docGrid.querySelectorAll("[data-doc]")].map((a) => a.dataset.doc.toLowerCase());
  (product.documents || []).forEach((d) => {
    if (!d.label || shownDocs.includes(d.label.toLowerCase())) return;
    const card = docTemplate.cloneNode(true);
    card.querySelector("h3").textContent = d.label;
    card.querySelector("p").textContent = "Additional product document";
    card.querySelector("[data-doc]").dataset.doc = d.label;
    docGrid.appendChild(card);
  });

  docGrid.addEventListener("click", (e) => {
    const link = e.target.closest("a[data-doc]");
    if (!link) return;
    e.preventDefault();
    const form = document.createElement("form");
    form.className = "pd-doc-form";
    form.noValidate = true;
    form.innerHTML =
      '<input type="email" name="email" required maxlength="200" placeholder="Enter your email address" aria-label="Your email address">' +
      '<input type="text" name="website" tabindex="-1" autocomplete="off" hidden>' +
      '<button type="submit">Send &rarr;</button>' +
      '<p class="pd-doc-form__msg" role="status"></p>';
    link.replaceWith(form);
    const input = form.querySelector('input[name="email"]');
    const msg = form.querySelector(".pd-doc-form__msg");
    input.focus();

    form.addEventListener("submit", async (ev) => {
      ev.preventDefault();
      if (!input.value.trim() || !input.checkValidity()) {
        showFormMessage(msg, "Please enter a valid email address.", "error");
        return;
      }
      const button = form.querySelector("button");
      button.disabled = true;
      showFormMessage(msg, "Sending…");
      const body = withBotCheck(new FormData(form));
      body.set("email", input.value.trim());
      body.append("product", product.slug);
      body.append("document", link.dataset.doc);
      try {
        const res = await fetch("api/document-request.php", { method: "POST", body });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
        form.reset();
        input.hidden = button.hidden = true;
        // After the message fades, the card goes back to its "Request by Email" link.
        showFormMessage(msg, data.message, "success", () => form.replaceWith(link));
      } catch (err) {
        showFormMessage(msg, err.message || "Something went wrong. Please try again.", "error");
        button.disabled = false;
      }
    });
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
