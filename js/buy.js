// buy.html — Apple-style "Buy" configurator for products marked On Sale in the admin.
// Picks model (variant), colour and quantity, then hands them to checkout by URL;
// api/order.php re-prices everything server-side, so nothing here is trusted.

const _pathSlug = window.location.pathname.split("/buy/")[1]?.replace(/\/$/, "");
const params = new URLSearchParams(window.location.search);
const slug = decodeURIComponent(_pathSlug || params.get("slug") || "");
const product = slug ? getProduct(slug) : null;

// The page's <base> points at the site root, so in-page "#section" links are pinned to this URL.
document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((a) => {
  a.href = window.location.pathname + window.location.search + a.getAttribute("href");
});

const formatINR = (n) => "₹" + Number(n).toLocaleString("en-IN", { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });

if (!product || !product.onSale) {
  // Showcase-only products have no Buy page.
  window.location.replace(product ? `product-details/${encodeURIComponent(product.slug)}` : "products.html");
} else {
  const category = getCategory(product.category);
  const variants = product.variants || [];
  const colors = product.colors || [];
  const images = [product.image, ...(product.gallery || [])];

  // Selection: from the URL when coming back from checkout ("Edit"), else the first option.
  const state = {
    variant: variants.find((v) => v.name === params.get("variant")) || variants[0] || null,
    color: colors.find((c) => c.name === params.get("color")) || colors[0] || null,
    qty: Math.min(99, Math.max(1, parseInt(params.get("qty"), 10) || 1)),
    image: images[0],
  };
  const unitPrice = () => (state.variant ? state.variant.price : product.price);

  document.title = `Buy ${product.name} | MarkEn`;
  document.getElementById("buy-crumb-category").textContent = category.name;
  document.getElementById("buy-crumb-category").href = `products/${encodeURIComponent(category.slug)}`;
  document.getElementById("buy-crumb-product").textContent = product.name;
  document.getElementById("buy-crumb-product").href = `product-details/${encodeURIComponent(product.slug)}`;
  document.getElementById("buy-category").textContent = category.name;
  document.getElementById("buy-title").textContent = `Buy ${product.name}`;
  const fromPrice = variants.length ? Math.min(...variants.map((v) => v.price)) : product.price;
  document.getElementById("buy-from").textContent = `From ${formatINR(fromPrice)} (incl. of all taxes)`;
  if (product.deliveryInfo) document.getElementById("buy-delivery").textContent = product.deliveryInfo;
  document.getElementById("buy-bar-name").textContent = product.name;

  // Gallery — thumbnails for the product photos; a colour with its own image replaces the stage.
  const stageImg = document.getElementById("buy-image");
  const thumbsEl = document.getElementById("buy-thumbs");
  stageImg.alt = product.name;
  if (images.length > 1) {
    images.forEach((src, i) => {
      const b = document.createElement("button");
      b.type = "button";
      b.setAttribute("aria-label", `Show image ${i + 1}`);
      b.innerHTML = '<img alt="" />';
      b.querySelector("img").src = src;
      b.addEventListener("click", () => { state.image = src; render(); });
      thumbsEl.appendChild(b);
    });
  }

  // Option groups render as radio buttons so keyboard and screen readers get a real choice.
  function radio(group, label, onPick) {
    const b = document.createElement("button");
    b.type = "button";
    b.setAttribute("role", "radio");
    b.dataset.label = label;
    b.addEventListener("click", onPick);
    group.appendChild(b);
    return b;
  }

  const variantsEl = document.getElementById("buy-variants");
  if (variants.length) {
    variants.forEach((v) => {
      const b = radio(variantsEl, v.name, () => { state.variant = v; render(); });
      b.className = "shop-option";
      b.innerHTML = '<span class="shop-option__text"><strong></strong><small></small></span><span class="shop-option__price"></span>';
      b.querySelector("strong").textContent = v.name;
      b.querySelector("small").textContent = v.description || "";
      b.querySelector(".shop-option__price").textContent = formatINR(v.price);
    });
  } else {
    document.getElementById("buy-step-model").remove();
  }

  const colorsEl = document.getElementById("buy-colors");
  if (colors.length) {
    colors.forEach((c) => {
      const b = radio(colorsEl, c.name, () => {
        state.color = c;
        if (c.image) state.image = c.image;
        render();
      });
      b.className = "shop-swatch";
      b.style.setProperty("--swatch", c.hex);
      b.setAttribute("aria-label", c.name);
      b.title = c.name;
    });
    if (state.color && state.color.image) state.image = state.color.image;
  } else {
    document.getElementById("buy-step-color").remove();
  }

  const box = product.inTheBox || [];
  if (box.length) {
    document.getElementById("buy-box-list").innerHTML = box.map(() => "<li></li>").join("");
    document.querySelectorAll("#buy-box-list li").forEach((li, i) => (li.textContent = box[i]));
  } else {
    document.getElementById("buy-box").remove();
  }

  document.getElementById("buy-qty-minus").addEventListener("click", () => { state.qty = Math.max(1, state.qty - 1); render(); });
  document.getElementById("buy-qty-plus").addEventListener("click", () => { state.qty = Math.min(99, state.qty + 1); render(); });

  function render() {
    stageImg.src = state.image;
    thumbsEl.querySelectorAll("button").forEach((b, i) => b.classList.toggle("is-active", images[i] === state.image));
    variantsEl.querySelectorAll("[role=radio]").forEach((b) => b.setAttribute("aria-checked", b.dataset.label === state.variant?.name));
    colorsEl.querySelectorAll("[role=radio]").forEach((b) => b.setAttribute("aria-checked", b.dataset.label === state.color?.name));
    document.getElementById("buy-color-name").textContent = state.color ? state.color.name : "";
    document.getElementById("buy-qty").textContent = state.qty;
    document.getElementById("buy-qty-minus").disabled = state.qty <= 1;
    document.getElementById("buy-qty-plus").disabled = state.qty >= 99;

    document.getElementById("buy-bar-selection").textContent =
      [state.variant?.name, state.color?.name, `Qty ${state.qty}`].filter(Boolean).join(" · ");
    document.getElementById("buy-bar-total").textContent = formatINR(unitPrice() * state.qty);

    const query = new URLSearchParams({ product: product.slug, qty: state.qty });
    if (state.variant) query.set("variant", state.variant.name);
    if (state.color) query.set("color", state.color.name);
    document.getElementById("buy-checkout").href = `checkout?${query}`;
  }
  render();
}
