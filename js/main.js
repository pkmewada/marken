// Header: transparent-over-hero -> solid on scroll (always solid on
// interior pages that have no hero to sit over)
const header = document.querySelector(".site-header");
if (header) {
  const hasHero = !!document.querySelector(".hero");
  const onScroll = () => {
    header.classList.toggle("is-solid", !hasHero || window.scrollY > 40);
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
}

// Products mega menu (hover on desktop, click on touch)
document.querySelectorAll(".nav-item.has-mega").forEach((item) => {
  item.addEventListener("mouseenter", () => item.classList.add("is-open"));
  item.addEventListener("mouseleave", () => item.classList.remove("is-open"));
});

// Mobile menu
const burger = document.querySelector(".header-burger");
const mobileMenu = document.querySelector(".mobile-menu");
if (burger && mobileMenu) {
  burger.addEventListener("click", () => {
    const open = mobileMenu.classList.toggle("is-open");
    document.body.classList.toggle("menu-open", open);
  });
  mobileMenu.querySelectorAll("a").forEach((a) =>
    a.addEventListener("click", () => {
      mobileMenu.classList.remove("is-open");
      document.body.classList.remove("menu-open");
    })
  );
}

// Scroll-reveal animation
const revealTargets = document.querySelectorAll(".reveal");
if (revealTargets.length) {
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

// Milestone collage carousel (text cycles through real MarkEn facts;
// images stay fixed since we don't have per-slide photography for each)
const collageNav = document.querySelector(".collage__nav");
const collageSlidesEl = document.getElementById("collage-slides");
if (collageNav && collageSlidesEl) {
  const slides = JSON.parse(collageSlidesEl.textContent);
  const copy = document.querySelector(".collage__copy");
  const eyebrowEl = copy.querySelector('[data-field="eyebrow"]');
  const titleEl = copy.querySelector('[data-field="title"]');
  const textEl = copy.querySelector('[data-field="text"]');
  const counterEl = copy.querySelector('[data-field="counter"]');
  let index = 0;

  function renderSlide() {
    const s = slides[index];
    eyebrowEl.textContent = s.eyebrow;
    titleEl.textContent = s.title;
    textEl.textContent = s.text;
    counterEl.textContent = `${String(index + 1).padStart(2, "0")} / ${String(slides.length).padStart(2, "0")}`;
  }

  collageNav.querySelector('[data-dir="prev"]').addEventListener("click", () => {
    index = (index - 1 + slides.length) % slides.length;
    renderSlide();
  });
  collageNav.querySelector('[data-dir="next"]').addEventListener("click", () => {
    index = (index + 1) % slides.length;
    renderSlide();
  });
}

// Newsletter form (no backend — just a friendly acknowledgement)
const newsletterForm = document.querySelector(".newsletter-form");
if (newsletterForm) {
  newsletterForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const input = newsletterForm.querySelector("input");
    if (input.value) {
      input.value = "";
      input.placeholder = "Thank you — we'll be in touch";
    }
  });
}
