// About Us: the Industry Expertise cards follow the admin's product categories (same feed as the
// header menu). The cards in the HTML stay as a fallback if the request fails.
fetch("api/categories.php")
  .then((res) => (res.ok ? res.json() : Promise.reject()))
  .then((categories) => {
    if (!categories.length) return;
    const grid = document.getElementById("ab-expertise");
    grid.innerHTML = categories.map((c) => `
      <a class="ab-expertise__card reveal" href="products/${encodeURIComponent(c.slug)}">
        <h3>${esc(c.name)}</h3>
        <p>${esc(c.tagline || "")}</p>
        <span>Explore &rarr;</span>
      </a>`).join("");
    revealInjected(grid);
  })
  .catch(() => {});
