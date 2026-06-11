document.addEventListener('DOMContentLoaded', () => {
  // Bot flag
  setTimeout(() => {
    const j = document.getElementById('js_ok');
    if (j) j.value = 'yes';
  }, 600);

  const form   = document.getElementById('invoiceForm');
  if (!form) return;

  const tbody  = document.getElementById('itemsBody');
  const addBtn = document.getElementById('addRow');
  const subEl  = document.getElementById('subtotal');
  const hstEl  = document.getElementById('hst');
  const grandEl= document.getElementById('grand');

  function money(n){ return '$' + (Number(n||0).toFixed(2)); }
  function parseQty(raw) {
    const value = parseFloat(raw || '0');
    return Number.isFinite(value) ? value : 0;
  }
  function parseHours(raw) {
    const value = String(raw || '').trim();
    if (/^\d+$/.test(value)) {
      const hours = parseInt(value, 10);
      return hours > 0 ? { display: String(hours), qty: hours } : null;
    }
    const match = value.match(/^(\d+)[.:,](\d{2})$/);
    if (!match) return null;
    const hours = parseInt(match[1], 10);
    const minutes = parseInt(match[2], 10);
    if (minutes > 59 || (hours === 0 && minutes === 0)) return null;
    return {
      display: hours + ':' + ('0' + minutes).slice(-2),
      qty: hours + (minutes / 60)
    };
  }
  function isPositiveInteger(raw) {
    return /^\d+$/.test(raw) && parseInt(raw, 10) > 0;
  }
  function isPositiveHours(raw) {
    return parseHours(raw) !== null;
  }

  function recalc() {
    if (!tbody) return;
    let totalIncl = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const dayRaw = tr.querySelector('input[name="days[]"]')?.value || '';
      const hourRaw = tr.querySelector('input[name="hours[]"]')?.value || '';
      const hours = parseHours(hourRaw);
      const qty = dayRaw.trim() !== '' ? parseQty(dayRaw) : (hours ? hours.qty : 0);
      const r = parseFloat(tr.querySelector('input[name="rate[]"]')?.value || 0);
      const line = qty * (isNaN(r) ? 0 : r);
      const target = tr.querySelector('.lineTotal');
      if (target) target.textContent = money(line);
      totalIncl += line;
    });

    const subtotal = totalIncl / 1.13;
    const hst = totalIncl - subtotal;
    if (subEl)  subEl.textContent  = money(subtotal);
    if (hstEl)  hstEl.textContent  = money(hst);
    if (grandEl)grandEl.textContent= money(totalIncl);
  }
  recalc();

  // --- validation helpers (scoped classes) ---
  function clearErrors() {
    form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
    form.querySelectorAll('.err-msg, .error-msg').forEach(el => el.textContent = '');
  }
  function showErr(input, msg) {
    const wrap = input.closest('.field, .cell, label') || input.parentElement;
    if (wrap) {
      wrap.classList.add('has-error');
      const em = wrap.querySelector('.err-msg, .error-msg');
      if (em) em.textContent = msg || 'Invalid value.';
    }
  }

  if (tbody) {
    tbody.addEventListener('input', e => {
      if (e.target.matches('input')) {
        if (e.target.matches('input[name="days[]"], input[name="hours[]"]') && e.target.value.trim() !== '') {
          const otherName = e.target.name === 'days[]' ? 'hours[]' : 'days[]';
          const other = e.target.closest('tr')?.querySelector(`input[name="${otherName}"]`);
          if (other) other.value = '';
        }
        const wrap = e.target.closest('.field, .cell, label');
        if (wrap) {
          wrap.classList.remove('has-error');
          const em = wrap.querySelector('.err-msg, .error-msg');
          if (em) em.textContent='';
        }
        recalc();
      }
    });

    tbody.addEventListener('click', e => {
      if (e.target.classList.contains('rowDel')) {
        const rows = tbody.querySelectorAll('tr').length;
        if (rows > 1) { e.target.closest('tr').remove(); recalc(); }
      }
    });

    tbody.addEventListener('blur', e => {
      if (e.target.matches('input[name="hours[]"]')) {
        const parsed = parseHours(e.target.value);
        if (parsed) {
          e.target.value = parsed.display;
          recalc();
        }
      }
    }, true);
  }

  if (addBtn && tbody) {
    addBtn.addEventListener('click', () => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="cell">
            <input name="desc[]"  type="text" autocomplete="off" required>
            <small class="err-msg"></small>
          </div>
        </td>
        <td>
          <div class="cell">
            <input name="days[]" type="number" step="1" min="0" autocomplete="off" inputmode="numeric">
            <small class="err-msg"></small>
          </div>
        </td>
        <td>
          <div class="cell">
            <input name="hours[]" type="text" autocomplete="off" inputmode="decimal" placeholder="1:35">
            <small class="err-msg"></small>
          </div>
        </td>
        <td>
          <div class="cell">
            <input name="rate[]"  type="number" step="0.01" min="0" autocomplete="off" required>
            <small class="err-msg"></small>
          </div>
        </td>
        <td class="lineTotal">$0.00</td>
        <td><button type="button" class="rowDel" aria-label="Remove">&times;</button></td>`;
      tbody.appendChild(tr);
    });
  }

  // Submit button UX
  const submitBtn = form.querySelector('button[type="submit"]');
  function enableBtn(){ if(submitBtn){ submitBtn.disabled=false; submitBtn.removeAttribute('aria-busy'); } }
  function busyBtn(){ if(submitBtn){ submitBtn.disabled=true; submitBtn.setAttribute('aria-busy','true'); } }
  enableBtn();
  window.addEventListener('pageshow', e => { if (e.persisted) enableBtn(); });

  // Client-side validation
  form.noValidate = true;
  form.addEventListener('submit', e => {
    clearErrors();
    busyBtn();
    let bad = false;

    const name = form.elements['your_name'];
    if (!name.value.trim()) { showErr(name, 'Please enter your name.'); bad = true; }

    const sd = form.elements['start_date'];
    if (!sd.value) { showErr(sd, 'Pick a start date.'); bad = true; }

    const ed = form.elements['end_date'];
    if (!ed.value) { showErr(ed, 'Pick an end date.'); bad = true; }

    if (sd.value && ed.value && sd.value > ed.value) {
      showErr(ed, 'End date must be on/after start date.'); bad = true;
    }

    const hst = form.elements['your_hst'];
    if (!hst.value.trim()) { showErr(hst, 'Enter your HST number.'); bad = true; }

    const addr = form.elements['your_address'];
    if (!addr.value.trim()) { showErr(addr, 'Enter your address.'); bad = true; }

    const phone = form.elements['your_phone'];
    if (!phone.value.trim()) { showErr(phone, 'Enter your phone.'); bad = true; }

    const email = form.elements['your_email'];
    if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      showErr(email, 'Enter a valid email or leave blank.'); bad = true;
    }

    const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
    if (rows.length === 0) { bad = true; alert('Please add at least one line item.'); }

    rows.forEach(tr => {
      const dsc = tr.querySelector('input[name="desc[]"]');
      const daysInput = tr.querySelector('input[name="days[]"]');
      const hoursInput = tr.querySelector('input[name="hours[]"]');
      const dayRaw = daysInput.value.trim();
      const hourRaw = hoursInput.value.trim();
      const r   = parseFloat(tr.querySelector('input[name="rate[]"]').value || '0');
      if (!dsc.value.trim()) { showErr(dsc, 'Describe the work.'); bad = true; }
      if ((dayRaw === '' && hourRaw === '') || (dayRaw !== '' && hourRaw !== '')) {
        showErr(daysInput, 'Fill days or hours.');
        showErr(hoursInput, 'Only one value.');
        bad = true;
      } else if (dayRaw !== '' && !isPositiveInteger(dayRaw)) {
        showErr(daysInput, 'Use whole days.');
        bad = true;
      } else if (hourRaw !== '' && !isPositiveHours(hourRaw)) {
        showErr(hoursInput, 'Use H:MM, minutes 00-59.');
        bad = true;
      } else if (hourRaw !== '') {
        hoursInput.value = parseHours(hourRaw).display;
      }
      if (r < 0) { showErr(tr.querySelector('input[name="rate[]"]'), 'Rate cannot be negative.'); bad = true; }
    });

    if (bad) {
      e.preventDefault();
      enableBtn();
      const firstInvalid = form.querySelector('.has-error input, .has-error select, .has-error textarea');
      if (firstInvalid) firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});
    }
  });
});
