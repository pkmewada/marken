function blogCardHTML(post) {
  const category = getBlogCategory(post.category);
  return `
    <a href="blog-details.html?slug=${post.slug}" class="blog-card reveal">
      <div class="blog-card__frame">
        <img src="${post.image}" alt="${post.title}" loading="lazy" />
      </div>
      <div class="blog-card__info">
        <span class="blog-card__meta">
          <span class="blog-card__category">${category ? category.name : ""}</span>
          <span class="blog-card__date">${formatBlogDate(post.date)}</span>
        </span>
        <h3 class="blog-card__title">${post.title}</h3>
        <p class="blog-card__excerpt">${post.excerpt}</p>
        <span class="blog-card__link">Read Article <i>&rarr;</i></span>
      </div>
    </a>
  `;
}
