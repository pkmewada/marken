// checkout.html — guest checkout. The selection arrives in the URL from the Buy page
// (?product=&variant=&color=&qty=); api/order.php validates and prices it again, then
// the order (pending payment) is handed to the payment page.

const params = new URLSearchParams(window.location.search);
const product = getProduct(params.get("product") || "");
const formatINR = (n) => "₹" + Number(n).toLocaleString("en-IN", { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });

const variant = product && (product.variants || []).find((v) => v.name === params.get("variant"));
const color = product && (product.colors || []).find((c) => c.name === params.get("color"));
const qty = parseInt(params.get("qty"), 10);

if (!product || !product.onSale) {
  window.location.replace("products.html");
} else if (((product.variants || []).length && !variant) || ((product.colors || []).length && !color) || !(qty >= 1 && qty <= 99)) {
  // Incomplete or stale selection (e.g. a variant renamed in the admin): choose again.
  window.location.replace(`buy/${encodeURIComponent(product.slug)}`);
} else {
  const unitPrice = variant ? variant.price : product.price;
  const selection = new URLSearchParams({ qty });
  if (variant) selection.set("variant", variant.name);
  if (color) selection.set("color", color.name);

  document.getElementById("co-image").src = (color && color.image) || product.image;
  document.getElementById("co-image").alt = product.name;
  document.getElementById("co-product").textContent = product.name;
  document.getElementById("co-selection").textContent = [variant?.name, color?.name].filter(Boolean).join(" · ");
  document.getElementById("co-edit").href = `buy/${encodeURIComponent(product.slug)}?${selection}`;
  document.getElementById("co-unit").textContent = formatINR(unitPrice);
  document.getElementById("co-qty").textContent = qty;
  document.getElementById("co-total").textContent = formatINR(unitPrice * qty);
  document.getElementById("co-delivery").textContent = product.deliveryInfo || "";

  const form = document.getElementById("checkout-form");
  const msg = document.getElementById("checkout-msg");
  const submit = document.getElementById("checkout-submit");

  const markInvalid = (names) => {
    form.querySelectorAll("input, select").forEach((el) => el.toggleAttribute("aria-invalid", names.includes(el.name)));
    const first = form.querySelector("[aria-invalid]");
    if (first) first.focus();
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const pin = form.elements.pincode;
    pin.setCustomValidity(/^[1-9]\d{5}$/.test(pin.value.trim()) ? "" : "Please enter a valid 6-digit PIN code.");
    const invalid = [...form.querySelectorAll("input:not([hidden]), select")].filter((el) => !el.checkValidity());
    if (invalid.length) {
      markInvalid(invalid.map((el) => el.name));
      msg.textContent = invalid[0].validationMessage || "Please complete the highlighted fields.";
      return;
    }

    submit.disabled = true;
    msg.textContent = "";
    const body = withBotCheck(new FormData(form));
    body.set("product", product.slug);
    body.set("quantity", qty);
    if (variant) body.set("variant", variant.name);
    if (color) body.set("color", color.name);
    try {
      const res = await fetch("api/order.php", { method: "POST", body });
      const data = await res.json();
      if (!data.ok) {
        markInvalid(Object.keys(data.errors || {}));
        throw new Error(data.message);
      }
      if (data.orderNumber) {
        sessionStorage.setItem("markenOrder", JSON.stringify({
          orderNumber: data.orderNumber, total: data.total, productName: data.productName,
        }));
      }
      window.location.href = "payment";
    } catch (err) {
      msg.textContent = err.message || "Something went wrong. Please try again.";
      submit.disabled = false;
    }
  });

  form.addEventListener("input", (e) => e.target.removeAttribute("aria-invalid"));
}
