// Public forms send how long the page was open; the server ignores
// submissions made faster than a person could type (bots).
const PAGE_LOADED_AT = Date.now();
function withBotCheck(formData) {
  formData.set("_t", String(Date.now() - PAGE_LOADED_AT));
  return formData;
}

// Inline form message. Success/error messages fade out after 3s, then
// onHidden runs (e.g. to restore a form). Without a type it stays (e.g. "Sending…").
function showFormMessage(el, text, type, onHidden) {
  clearTimeout(el._fadeTimer);
  el.classList.remove("is-success", "is-error", "is-fading");
  el.textContent = text;
  if (!type) return;
  el.classList.add(`is-${type}`);
  el._fadeTimer = setTimeout(() => {
    el.classList.add("is-fading");
    el._fadeTimer = setTimeout(() => {
      el.textContent = "";
      el.classList.remove("is-success", "is-error", "is-fading");
      if (onHidden) onHidden();
    }, 400);
  }, 3000);
}

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
  // Delegated so category links rendered later (see below) also close the menu.
  mobileMenu.addEventListener("click", (e) => {
    if (!e.target.closest("a")) return;
    mobileMenu.classList.remove("is-open");
    document.body.classList.remove("menu-open");
  });
}

// Header categories from the admin database. The hardcoded links in the HTML
// stay as a fallback if the request fails.
const megaGrid = document.querySelector(".mega-menu__grid");
const mobileCats = document.querySelector(".mobile-menu__categories");
if (megaGrid || mobileCats) {
  fetch("api/categories.php")
    .then((res) => (res.ok ? res.json() : Promise.reject()))
    .then((categories) => {
      if (!categories.length) return;
      const link = (cat, className) => {
        const a = document.createElement("a");
        if (className) a.className = className;
        a.href = `products.html?category=${encodeURIComponent(cat.slug)}`;
        return a;
      };
      if (megaGrid) {
        megaGrid.replaceChildren(
          ...categories.flatMap((cat, i) => {
            const a = link(cat, "mega-menu__col");
            const name = document.createElement("span");
            name.className = "mega-menu__name";
            name.textContent = cat.name;
            const tagline = document.createElement("span");
            tagline.className = "mega-menu__tagline";
            tagline.textContent = cat.tagline || "";
            a.append(name, tagline);
            // Thin separator before each new row of 4.
            if (i > 0 && i % 4 === 0) {
              const divider = document.createElement("hr");
              divider.className = "mega-menu__divider";
              return [divider, a];
            }
            return [a];
          })
        );
      }
      if (mobileCats) {
        mobileCats.replaceChildren(
          ...categories.map((cat) => {
            const a = link(cat);
            a.textContent = cat.name;
            return a;
          })
        );
      }
    })
    .catch(() => {});
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

// Footer newsletter → api/newsletter.php. The status line is created here so
// the footer markup (copied into every page) doesn't need to change.
const newsletterForm = document.querySelector(".newsletter-form");
if (newsletterForm) {
  const input = newsletterForm.querySelector('input[type="email"]');
  const button = newsletterForm.querySelector("button");
  const status = Object.assign(document.createElement("p"), { className: "newsletter-status" });
  status.setAttribute("role", "status");
  newsletterForm.after(status);
  newsletterForm.noValidate = true;   // show our inline error instead of the browser bubble

  newsletterForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!input.value.trim() || !input.checkValidity()) {
      showFormMessage(status, "Please enter a valid email address.", "error");
      return;
    }
    button.disabled = true;
    showFormMessage(status, "Subscribing…");
    const body = withBotCheck(new FormData());
    body.append("email", input.value.trim());
    body.append("page", location.pathname);
    try {
      const res = await fetch("api/newsletter.php", { method: "POST", body });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      newsletterForm.reset();
      showFormMessage(status, data.message, "success");
    } catch (err) {
      showFormMessage(status, err.message || "Something went wrong. Please try again.", "error");
    } finally {
      button.disabled = false;
    }
  });
}
