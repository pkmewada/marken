const blogSlug = new URLSearchParams(window.location.search).get("slug");
const post = blogSlug ? getBlogPost(blogSlug) : null;

if (!post) {
  window.location.replace("blog.html");
} else {
  const category = getBlogCategory(post.category);

  document.title = `${post.title} | MarkEn Blog`;
  document.querySelector('meta[name="description"]').setAttribute("content", post.excerpt);

  // Structured data for search engines
  const jsonLd = document.createElement("script");
  jsonLd.type = "application/ld+json";
  jsonLd.textContent = JSON.stringify({
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    headline: post.title,
    description: post.excerpt,
    image: new URL(post.image, window.location.href).href,
    datePublished: post.date,
    author: { "@type": "Organization", name: "MarkEn" },
    publisher: { "@type": "Organization", name: "MarkEn" },
  });
  document.head.appendChild(jsonLd);

  // Breadcrumb
  document.getElementById("bd-breadcrumb-category").textContent = category.name;
  document.getElementById("bd-breadcrumb-category").href = `blog.html?category=${category.slug}`;
  document.getElementById("bd-breadcrumb-title").textContent = post.title;

  // Header
  document.getElementById("bd-category").textContent = category.name;
  document.getElementById("bd-title").textContent = post.title;
  document.getElementById("bd-date").textContent = formatBlogDate(post.date);

  // Cover
  const cover = document.getElementById("bd-cover-image");
  cover.src = post.image;
  cover.alt = post.title;

  // Body
  document.getElementById("bd-body").innerHTML = post.sections
    .map(
      (section) =>
        `<h2>${section.heading}</h2>` +
        section.body
          .split("\n\n")
          .map((para) => `<p>${para}</p>`)
          .join("")
    )
    .join("");

  // CTA — link to the related product if this post has one
  const ctaLink = document.getElementById("bd-cta-link");
  if (post.relatedProductSlug && getProduct(post.relatedProductSlug)) {
    const product = getProduct(post.relatedProductSlug);
    ctaLink.href = `product-details/${product.slug}`;
    ctaLink.textContent = `View ${product.name}`;
  } else {
    ctaLink.href = "index.html#contact";
    ctaLink.textContent = "Make an Enquiry";
  }

  // Related articles
  const related = getRelatedBlogPosts(post, 3);
  document.getElementById("bd-related-grid").innerHTML = related.map(blogCardHTML).join("");

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
