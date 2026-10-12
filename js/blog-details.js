const blogUrlParams = new URLSearchParams(window.location.search);
const blogSlug = blogUrlParams.get("slug") || "";

function revealInjected() {
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

function renderBlogPost(post, related) {
  const pageTitle = post.metaTitle || `${post.title} | MarkEn Blog`;
  const description = post.metaDescription || post.excerpt;
  document.title = post.isPreview ? `[Preview] ${pageTitle}` : pageTitle;
  document.querySelector('meta[name="description"]').setAttribute("content", description);

  // Structured data for search engines
  const isTeam = !post.authorName || /^marken\b/i.test(post.authorName);
  const jsonLd = document.createElement("script");
  jsonLd.type = "application/ld+json";
  jsonLd.textContent = JSON.stringify({
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    headline: post.title,
    description,
    image: post.image ? new URL(post.image, document.baseURI).href : undefined,
    datePublished: post.date,
    dateModified: post.updatedAt ? post.updatedAt.replace(" ", "T") : undefined,
    author: { "@type": isTeam ? "Organization" : "Person", name: post.authorName || "MarkEn" },
    publisher: { "@type": "Organization", name: "MarkEn" },
  });
  document.head.appendChild(jsonLd);

  // Breadcrumb — an inactive category has no filter button on the listing, so link to the full list.
  const crumb = document.getElementById("bd-breadcrumb-category");
  crumb.textContent = post.categoryName;
  crumb.href = post.categoryActive ? `blog.html?category=${encodeURIComponent(post.category)}` : "blog.html";
  document.getElementById("bd-breadcrumb-title").textContent = post.title;

  // Header
  document.getElementById("bd-category").textContent = post.isPreview ? `Preview (not published) · ${post.categoryName}` : post.categoryName;
  document.getElementById("bd-title").textContent = post.title;
  document.getElementById("bd-date").textContent = formatBlogDate(post.date) || "Not scheduled";
  document.getElementById("bd-author").textContent = post.authorName || "MarkEn Team";

  // Cover
  const cover = document.getElementById("bd-cover-image");
  if (post.image) {
    cover.src = post.image;
    cover.alt = post.title;
  } else {
    cover.parentElement.style.display = "none";
  }

  // Body — sanitized server-side (app/html-sanitizer.php) to an allowlist of tags/attributes.
  document.getElementById("bd-body").innerHTML = post.content;

  // CTA — link to the related product if this post has one
  const ctaLink = document.getElementById("bd-cta-link");
  if (post.relatedProduct) {
    ctaLink.href = `product-details/${encodeURIComponent(post.relatedProduct.slug)}`;
    ctaLink.textContent = `View ${post.relatedProduct.name}`;
  } else {
    ctaLink.href = "index.html#contact";
    ctaLink.textContent = "Make an Enquiry";
  }

  document.getElementById("bd-related-grid").innerHTML = related.map(blogCardHTML).join("");
}

function renderBlogNotFound() {
  document.title = "Article not found | MarkEn Blog";
  const robots = document.createElement("meta");
  robots.name = "robots";
  robots.content = "noindex";
  document.head.appendChild(robots);

  document.getElementById("bd-breadcrumb-category").remove();
  document.getElementById("bd-breadcrumb-title").textContent = "Not found";
  document.getElementById("bd-category").textContent = "Blog";
  document.getElementById("bd-title").textContent = "Article not found";
  document.querySelector(".bd-meta").style.display = "none";
  document.querySelector(".bd-cover").style.display = "none";
  document.querySelector(".bd-cta").style.display = "none";
  document.getElementById("bd-body").innerHTML =
    '<p>This article does not exist or is no longer available. <a href="blog.html">Browse all articles</a>.</p>';

  fetchBlog({ limit: 3 })
    .then(({ posts }) => {
      document.getElementById("bd-related-grid").innerHTML = posts.map(blogCardHTML).join("");
      revealInjected();
    })
    .catch(() => (document.querySelector(".bd-related").style.display = "none"));
}

function renderBlogError(message) {
  document.getElementById("bd-title").textContent = "Article unavailable";
  document.querySelector(".bd-meta").style.display = "none";
  document.querySelector(".bd-cover").style.display = "none";
  document.querySelector(".bd-related").style.display = "none";
  document.getElementById("bd-body").innerHTML = `<p>${escBlog(message)}</p>`;
}

const blogRequest = { slug: blogSlug };
if (blogUrlParams.get("preview") === "1") blogRequest.preview = "1";

fetchBlog(blogRequest)
  .then(({ post, related }) => renderBlogPost(post, related))
  .catch((err) => (err.status === 404 ? renderBlogNotFound() : renderBlogError(err.message)))
  .finally(revealInjected);
