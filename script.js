document.addEventListener('DOMContentLoaded', () => {
  // Bot flag (most bots won't run JS)
  setTimeout(() => {
    const j = document.getElementById('js_ok');
    if (j) j.value = 'yes';
  }, 600);

  const form   = document.getElementById('invoiceForm');
  const tbody  = document.getElementById('itemsBody');
  const addBtn = document.getElementById('addRow');
  const subEl  = document.getElementById('subtotal');
  const hstEl  = document.getElementById('hst');
  const grandEl= document.getElementById('grand');

  function money(n){ return '$' + (Number(n||0).toFixed(2)); }
  function recalc() {
    let totalIncl = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const h = parseFloat(tr.querySelector('input[name="hours[]"]').value || 0);
      const r = parseFloat(tr.querySelector('input[name="rate[]"]').value || 0);
      const line = h * r;
      tr.querySelector('.lineTotal').textContent = money(line);
      totalIncl += line;
    });
    const subtotal = totalIncl / 1.13;
    const hst = totalIncl - subtotal;
    subEl.textContent  = money(subtotal);
    hstEl.textContent  = money(hst);
    grandEl.textContent= money(totalIncl);
  }
  recalc();

  tbody.addEventListener('input', (e) => { if (e.target.matches('input')) recalc(); });

  tbody.addEventListener('click', (e) => {
    if (e.target.classList.contains('rowDel')) {
      const rows = tbody.querySelectorAll('tr').length;
      if (rows > 1) { e.target.closest('tr').remove(); recalc(); }
    }
  });

  addBtn.addEventListener('click', () => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input name="desc[]"  type="text" autocomplete="off" required></td>
      <td><input name="hours[]" type="number" step="0.01" min="0" autocomplete="off" required></td>
      <td><input name="rate[]"  type="number" step="0.01" min="0" autocomplete="off" required></td>
      <td class="lineTotal">$0.00</td>
      <td><button type="button" class="rowDel" aria-label="Remove">&times;</button></td>`;
    tbody.appendChild(tr);
  });

  // Disable submit after click; re-enable when user returns via back/forward cache
  const submitBtn = form.querySelector('button[type="submit"]');
  function enableBtn(){ if(submitBtn){ submitBtn.disabled=false; submitBtn.removeAttribute('aria-busy'); } }
  enableBtn();
  window.addEventListener('pageshow', e => { if (e.persisted) enableBtn(); });

  form.addEventListener('submit', () => {
    if (submitBtn){ submitBtn.disabled = true; submitBtn.setAttribute('aria-busy','true'); }
  });
});
