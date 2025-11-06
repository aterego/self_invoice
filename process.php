<?php
require __DIR__.'/config.php';
require __DIR__.'/mailer.php'; // your existing PHPMailer wrapper

// ---------- BOT GUARDS ----------
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

if (!empty($_POST['website'] ?? '')) { http_response_code(403); exit('Forbidden'); }              // Honeypot
$started = (int)($_POST['form_started'] ?? 0);
if ($started && time() - $started < 2) { http_response_code(403); exit('Forbidden'); }           // Min time
if (($_POST['js_ok'] ?? '') !== 'yes') { http_response_code(403); exit('Forbidden'); }           // JS flag
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf']) ||
    !hash_equals($_SESSION['csrf'], $_POST['csrf_token'])) { http_response_code(403); exit('Invalid token'); } // CSRF

// Rate limit
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
$rlDir = __DIR__.'/logs/rl';
if (!is_dir($rlDir)) { @mkdir($rlDir, 0755, true); }
$rlFile = $rlDir.'/'.preg_replace('~[^A-Za-z0-9_.:-]~','_',$ip).'.inv.log';
$now = time(); $window = RL_WINDOW_SECONDS; $limit = RL_MAX_SUBMISSIONS;
$events = [];
if (is_file($rlFile)) {
  $lines = file($rlFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $ts) { $t=(int)$ts; if ($t > $now - $window) $events[]=$t; }
}
$events[] = $now;
if (count($events) > $limit) { http_response_code(429); exit('Too many requests'); }
@file_put_contents($rlFile, implode("\n",$events)."\n", LOCK_EX);

// ---------- INPUT ----------
$your_name    = trim((string)($_POST['your_name'] ?? ''));
$invoice_date = trim((string)($_POST['invoice_date'] ?? ''));
$your_hst     = trim((string)($_POST['your_hst'] ?? ''));
$your_address = trim((string)($_POST['your_address'] ?? ''));
$your_phone   = trim((string)($_POST['your_phone'] ?? ''));
$your_email   = trim((string)($_POST['your_email'] ?? ''));

$desc  = $_POST['desc']  ?? [];
$hours = $_POST['hours'] ?? [];
$rate  = $_POST['rate']  ?? [];

if (!$your_name || !$invoice_date || !$your_hst || !$your_address || !$your_phone) {
  http_response_code(422); exit('Missing required fields.');
}
if (!is_array($desc) || !is_array($hours) || !is_array($rate) || count($desc) === 0) {
  http_response_code(422); exit('Need at least one line item.');
}

// ---------- MATH (server-side, tax-inclusive) ----------
$items = [];
$total_incl = 0.0;

for ($i=0; $i<count($desc); $i++) {
  $d = trim((string)($desc[$i] ?? ''));
  $h = (float)($hours[$i] ?? 0);
  $r = (float)($rate[$i] ?? 0);
  if ($d === '' || $h <= 0 || $r < 0) continue;

  $line_incl = round($h * $r, 2);
  $items[] = ['desc'=>$d, 'hours'=>$h, 'rate_incl'=>$r, 'line_incl'=>$line_incl];
  $total_incl += $line_incl;
}
if (empty($items)) { http_response_code(422); exit('No valid line items.'); }

$subtotal = round($total_incl / (1 + HST_RATE), 2);
$hst_amt  = round($total_incl - $subtotal, 2);

// ---------- DB SAVE (transaction) ----------
$mysqli = db();
$mysqli->begin_transaction();

try {
  // Create invoice row
  $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
  $stmt = $mysqli->prepare("
    INSERT INTO invoices
      (invoice_no, invoice_date,
       from_name, from_address, from_phone, from_email, from_hst,
       bill_to_name, bill_to_address, bill_to_phone, bill_to_hst,
       subtotal_excl_hst, hst_amount, total_incl_hst,
       client_ip, user_agent)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  // TEMP invoice no placeholder; we'll update after we get ID
  $tmpInvNo = 'PENDING';

  $billToName    = BILL_TO_NAME;
  $billToAddress = BILL_TO_ADDRESS;
  $billToPhone   = BILL_TO_PHONE;
  $billToHst     = BILL_TO_HST;

  $stmt->bind_param(
    'sssssssssssssdss',
    $tmpInvNo, $invoice_date,
    $your_name, $your_address, $your_phone, $your_email, $your_hst,
    $billToName, $billToAddress, $billToPhone, $billToHst,
    $subtotal, $hst_amt, $total_incl,
    $ip, $ua
  );
  $stmt->execute();
  $invoice_id = (int)$mysqli->insert_id;
  $stmt->close();

  // Generate final invoice_no like AM-000001
  $invoice_no = sprintf('AM-%06d', $invoice_id);
  $stmt = $mysqli->prepare("UPDATE invoices SET invoice_no=? WHERE id=?");
  $stmt->bind_param('si', $invoice_no, $invoice_id);
  $stmt->execute();
  $stmt->close();

  // Insert items
  $stmt = $mysqli->prepare("
    INSERT INTO invoice_items
      (invoice_id, line_no, description, hours, rate_incl_hst, amount_incl)
    VALUES (?,?,?,?,?,?)
  ");
  $line_no = 0;
  foreach ($items as $it) {
    $line_no++;
    $desc_i = $it['desc'];
    $hours_i= $it['hours'];
    $rate_i = $it['rate_incl'];
    $amt_i  = $it['line_incl'];
    $stmt->bind_param('iissdd', $invoice_id, $line_no, $desc_i, $hours_i, $rate_i, $amt_i);
    $stmt->execute();
  }
  $stmt->close();

  $mysqli->commit();
} catch (Throwable $e) {
  $mysqli->rollback();
  http_response_code(500);
  exit('Failed to save invoice.');
}

// ---------- EMAIL (HTML + text alt) ----------
$to   = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', MAIL_TO_ADDRS))));
$from = MAIL_FROM_ADDR;
$subject = 'Invoice '.$invoice_no.' from '.$your_name.' — '.$invoice_date;

$rows_html = '';
foreach ($items as $it) {
  $rows_html .= '<tr>'.
    '<td style="padding:6px;border-top:1px solid #eee;">'.h($it['desc']).'</td>'.
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">'.number_format($it['hours'],2).'</td>'.
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">'.money($it['rate_incl']).'</td>'.
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">'.money($it['line_incl']).'</td>'.
  '</tr>';
}

$bill_to_html = '<strong>'.h(BILL_TO_NAME).'</strong><br>'.
                nl2br(h(BILL_TO_ADDRESS)).'<br>'.
                'Phone: '.h(BILL_TO_PHONE).'<br>'.
                'HST: '.h(BILL_TO_HST);

$from_html = '<strong>'.h($your_name).'</strong><br>'.
             nl2br(h($your_address)).'<br>'.
             'Phone: '.h($your_phone).'<br>'.
             'HST: '.h($your_hst).($your_email ? '<br>Email: '.h($your_email) : '');

$message_html = '
<!doctype html><html><body style="background:#f7f7f7;padding:16px;margin:0;">
  <div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111827;">
    <h2 style="margin:0 0 12px;">Invoice '.$invoice_no.'</h2>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse">
      <tr>
        <td style="vertical-align:top;width:50%;padding-right:10px">
          <h3 style="margin:0 0 6px;font-size:16px;">From</h3>
          '.$from_html.'
        </td>
        <td style="vertical-align:top;width:50%;padding-left:10px">
          <h3 style="margin:0 0 6px;font-size:16px;">Bill To</h3>
          '.$bill_to_html.'
        </td>
      </tr>
      <tr><td colspan="2" style="padding-top:10px;color:#6b7280">Invoice Date: '.h($invoice_date).'</td></tr>
    </table>

    <h3 style="margin:16px 0 6px;">Items</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">
      <thead>
        <tr>
          <th style="text-align:left;border-bottom:2px solid #111;padding:6px">Description</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Hours</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Rate (incl HST)</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Amount</th>
        </tr>
      </thead>
      <tbody>'.$rows_html.'</tbody>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;font-size:14px">
      <tr><td style="text-align:right;color:#6b7280">Subtotal (excl. HST)</td><td style="text-align:right;width:160px">'.money($subtotal).'</td></tr>
      <tr><td style="text-align:right;color:#6b7280">HST (13%)</td><td style="text-align:right">'.money($hst_amt).'</td></tr>
      <tr><td style="text-align:right;font-weight:700;border-top:1px solid #e5e7eb;padding-top:6px">Total</td><td style="text-align:right;font-weight:700;border-top:1px solid #e5e7eb;padding-top:6px">'.money($total_incl).'</td></tr>
    </table>
  </div>
</body></html>';

$message_text =
"Invoice $invoice_no\n\n".
"From: $your_name\nAddress: $your_address\nPhone: $your_phone\nHST: $your_hst\n\n".
"Bill To: ".BILL_TO_NAME."\nAddress: ".BILL_TO_ADDRESS."\nPhone: ".BILL_TO_PHONE."\nHST: ".BILL_TO_HST."\n".
"Date: $invoice_date\n\n".
"Items:\n";
foreach ($items as $it) {
  $message_text .= "- {$it['desc']} | Hours: ".number_format($it['hours'],2)." | Rate(incl): ".money($it['rate_incl'])." | Amount: ".money($it['line_incl'])."\n";
}
$message_text .= "\nSubtotal: ".money($subtotal)."\nHST(13%): ".money($hst_amt)."\nTotal: ".money($total_incl)."\n";

// Send to admins
send_mail($to, $subject, $message_html, MAIL_FROM_ADDR, 'Invoices Bot', [], [], [], true, $message_text);
// Optional copy to user
if ($your_email && filter_var($your_email, FILTER_VALIDATE_EMAIL)) {
  send_mail($your_email, 'Copy: '.$subject, $message_html, MAIL_FROM_ADDR, 'Invoices Bot', [], [], [], true, $message_text);
}

// ---------- Thank you ----------
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Sent</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<div class="container"><div class="card" style="text-align:center">
  <h2>Invoice <?= h($invoice_no) ?> sent.</h2>
  <p><a class="btn" href="index.php">Create another</a></p>
</div></div>
</body></html>
