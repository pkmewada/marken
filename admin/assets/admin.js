const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* Mobile sidebar */
document.querySelectorAll('[data-sidebar-toggle]').forEach((el) =>
  el.addEventListener('click', () => document.body.classList.toggle('sidebar-open'))
);

/* SweetAlert2 replacements for the browser's alert()/confirm() */
function showError(message) {
  Swal.fire({ icon: 'error', title: 'Something went wrong', text: message, confirmButtonColor: '#0a2342' });
}

/* Confirm-before-submit for delete forms: <form data-confirm="Delete X?"> */
document.querySelectorAll('form[data-confirm]').forEach((form) =>
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    Swal.fire({
      icon: 'warning',
      title: 'Are you sure?',
      text: form.dataset.confirm,
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545',
      reverseButtons: true,
      focusCancel: true,
    }).then((result) => { if (result.isConfirmed) form.submit(); });   // form.submit() skips this handler
  })
);

/* Name → slug auto-fill until the slug is edited by hand */
document.querySelectorAll('[data-slug-source]').forEach((source) => {
  const target = document.getElementById(source.dataset.slugSource);
  if (!target) return;
  let auto = target.value === '';
  target.addEventListener('input', () => { auto = target.value === ''; });
  source.addEventListener('input', () => {
    if (!auto) return;
    target.value = source.value.toLowerCase().trim()
      .replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-|-$/g, '');
  });
});

/* Upload a file to upload.php; resolves to { url, filename, size, mime } */
async function uploadFile(file) {
  const fd = new FormData();
  fd.append('file', file);
  const res = await fetch('upload', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd });
  const data = await res.json().catch(() => ({ error: `Server error (${res.status})` }));
  if (!res.ok || data.error) throw new Error(data.error || `Upload failed (${res.status})`);
  return data;
}

/* Pick a file by click or drag-and-drop on an element. */
function bindFilePicker(zone, accept, onFile) {
  const input = Object.assign(document.createElement('input'), { type: 'file', accept, hidden: true });
  zone.after(input);
  zone.addEventListener('click', () => input.click());
  input.addEventListener('change', () => { if (input.files[0]) onFile(input.files[0]); input.value = ''; });
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('is-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('is-over');
    if (e.dataTransfer.files[0]) onFile(e.dataTransfer.files[0]);
  });
}

/* Single image upload: <div class="drop-zone" data-image-upload="hiddenInputId"> */
document.querySelectorAll('[data-image-upload]').forEach((zone) => {
  const hidden = document.getElementById(zone.dataset.imageUpload);
  bindFilePicker(zone, 'image/jpeg,image/png,image/webp,image/gif', async (file) => {
    zone.classList.add('is-busy');
    try {
      const { url } = await uploadFile(file);
      hidden.value = url;
      zone.innerHTML = `<img src="../${escHtml(url)}" alt=""><span class="small text-muted">Click or drag to replace</span>`;
    } catch (err) {
      showError(err.message);
    } finally {
      zone.classList.remove('is-busy');
    }
  });
});

/* Client-side search / sort / pagination for <table data-table> */
document.querySelectorAll('table[data-table]').forEach((table) => {
  const wrap = table.closest('.card');
  const search = wrap.querySelector('[data-table-search]');
  const filter = wrap.querySelector('[data-table-filter]');
  const info = wrap.querySelector('[data-table-info]');
  const pager = wrap.querySelector('[data-table-pager]');
  const tbody = table.tBodies[0];
  const rows = Array.from(tbody.rows).filter((r) => !r.hasAttribute('data-empty'));
  const perPage = 15;
  let page = 1;
  let sortIdx = -1;
  let sortDir = 1;

  function render() {
    const q = (search?.value || '').toLowerCase();
    const f = filter?.value || '';
    const visible = rows.filter((r) =>
      (!q || r.textContent.toLowerCase().includes(q)) && (!f || r.dataset.filter === f)
    );
    const pages = Math.max(1, Math.ceil(visible.length / perPage));
    page = Math.min(page, pages);
    const start = (page - 1) * perPage;
    rows.forEach((r) => (r.hidden = true));
    visible.slice(start, start + perPage).forEach((r) => (r.hidden = false));

    if (info) info.textContent = visible.length
      ? `Showing ${start + 1}–${Math.min(start + perPage, visible.length)} of ${visible.length}`
      : 'No matching records';
    if (pager) {
      pager.innerHTML = '';
      if (pages > 1) for (let i = 1; i <= pages; i++) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `btn btn-sm ${i === page ? 'btn-dark' : 'btn-outline-secondary'}`;
        b.textContent = i;
        b.onclick = () => { page = i; render(); };
        pager.appendChild(b);
      }
    }
  }

  table.querySelectorAll('th[data-sort]').forEach((th) => {
    th.addEventListener('click', () => {
      const idx = th.cellIndex;
      sortDir = sortIdx === idx ? -sortDir : 1;
      sortIdx = idx;
      const key = (r) => r.cells[idx]?.dataset.sortValue ?? r.cells[idx]?.textContent.trim() ?? '';
      rows.sort((a, b) => key(a).localeCompare(key(b), undefined, { numeric: true }) * sortDir);
      rows.forEach((r) => tbody.appendChild(r));
      render();
    });
  });

  search?.addEventListener('input', () => { page = 1; render(); });
  filter?.addEventListener('change', () => { page = 1; render(); });
  render();
});
