<?php require __DIR__.'/config.php';
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Self-Invoice</title>
<link rel="stylesheet" href="style.css">
<script defer src="script.js"></script>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="brand">
        <img src="logo.png" alt="Logo" class="logo" onerror="this.style.display='none'">
      </div>      
      <h1>Self-Invoice</h1>
      <p class="muted">Rates are <b>per day</b> and <b>tax-inclusive</b>. We’ll split out Ontario HST (13%).</p>

      <form id="invoiceForm" class="grid" method="post" action="process.php" autocomplete="off" novalidate>
        <!-- Bot guards -->
        <div class="sr-only" aria-hidden="true">
          <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <input type="hidden" name="form_started" value="<?= time() ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" id="js_ok" name="js_ok" value="">

        <div class="row">
          <label class="field">Your Name*
            <input required name="your_name" type="text" autocomplete="off" spellcheck="false">
            <small class="error-msg"></small>
          </label>

          <label class="field">Start Date*
            <input required name="start_date" type="date" autocomplete="off">
            <small class="error-msg"></small>
          </label>

          <label class="field">End Date*
            <input required name="end_date" type="date" autocomplete="off">
            <small class="error-msg"></small>
          </label>
        </div>

        <div class="row">
          <label class="field">Your HST #*
            <input required name="your_hst" type="text" autocomplete="off" spellcheck="false">
            <small class="error-msg"></small>
          </label>

          <label class="field">Your Address*
            <input required name="your_address" type="text" autocomplete="off" spellcheck="false">
            <small class="error-msg"></small>
          </label>

          <label class="field">Your Phone*
            <input required name="your_phone" type="tel" autocomplete="off" inputmode="tel">
            <small class="error-msg"></small>
          </label>
        </div>

        <div class="row">
          <label class="field">Your Email (copy to you)
            <input name="your_email" type="email" autocomplete="off">
            <small class="error-msg"></small>
          </label>
        </div>

        <h3>Line Items (per day, incl. HST)</h3>

        <table class="items" aria-describedby="items-help">
          <thead>
            <tr>
              <th style="width:45%">Description</th>
              <th style="width:15%">Days</th>
              <th style="width:20%">Rate (incl. HST)</th>
              <th style="width:20%">Amount</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <tr>
              <td>
                <div class="cell">
                  <input name="desc[]"  type="text" placeholder="e.g., Moving labour" required autocomplete="off" value="Moving Service">
                  <small class="error-msg"></small>
                </div>
              </td>
              <td>
                <div class="cell">
                  <input name="days[]" type="number" step="1" min="0" required autocomplete="off" inputmode="numeric">
                  <small class="error-msg"></small>
                </div>
              </td>
              <td>
                <div class="cell">
                  <input name="rate[]"  type="number" step="0.01" min="0" required autocomplete="off">
                  <small class="error-msg"></small>
                </div>
              </td>
              <td class="lineTotal">$0.00</td>
              <td><button type="button" class="rowDel" aria-label="Remove">&times;</button></td>
            </tr>
          </tbody>
        </table>

        <div class="items-actions">
          <button type="button" id="addRow" class="btn ghost">+ Add row</button>
        </div>

        <div class="totals">
          <div><span>Subtotal (excl. HST):</span><strong id="subtotal">$0.00</strong></div>
          <div><span>HST (13%):</span><strong id="hst">$0.00</strong></div>
          <div class="grand"><span>Total (incl. HST):</span><strong id="grand">$0.00</strong></div>
        </div>

        <div class="actions">
          <button type="submit" class="btn primary">Send Invoice</button>
        </div>

        <p id="items-help" class="muted small">All math is checked again on the server.</p>
      </form>
    </div>
  </div>
</body>
</html>
