<?php
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

// ---------- BOT GUARDS ----------
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}

if (!empty($_POST['website'] ?? '')) {
  http_response_code(403);
  exit('Forbidden');
}
$started = (int)($_POST['form_started'] ?? 0);
if ($started && time() - $started < 2) {
  http_response_code(403);
  exit('Forbidden');
}
if (($_POST['js_ok'] ?? '') !== 'yes') {
  http_response_code(403);
  exit('Forbidden');
}
if (
  empty($_POST['csrf_token']) || empty($_SESSION['csrf']) ||
  !hash_equals($_SESSION['csrf'], $_POST['csrf_token'])
) {
  http_response_code(403);
  exit('Invalid token');
}

// Rate limit
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
$rlDir = __DIR__ . '/logs/rl';
if (!is_dir($rlDir)) {
  @mkdir($rlDir, 0755, true);
}
$rlFile = $rlDir . '/' . preg_replace('~[^A-Za-z0-9_.:-]~', '_', $ip) . '.inv.log';
$now = time();
$window = RL_WINDOW_SECONDS;
$limit = RL_MAX_SUBMISSIONS;
$events = [];
if (is_file($rlFile)) {
  $lines = file($rlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $ts) {
    $t = (int)$ts;
    if ($t > $now - $window) $events[] = $t;
  }
}
$events[] = $now;
if (count($events) > $limit) {
  http_response_code(429);
  exit('Too many requests');
}
@file_put_contents($rlFile, implode("\n", $events) . "\n", LOCK_EX);

// ---------- INPUT ----------
$your_name    = trim((string)($_POST['your_name'] ?? ''));
$start_date   = trim((string)($_POST['start_date'] ?? ''));
$end_date     = trim((string)($_POST['end_date'] ?? ''));
$your_hst     = trim((string)($_POST['your_hst'] ?? ''));
$your_address = trim((string)($_POST['your_address'] ?? ''));
$your_phone   = trim((string)($_POST['your_phone'] ?? ''));
$your_email   = trim((string)($_POST['your_email'] ?? ''));

$desc  = $_POST['desc']  ?? [];
$daysA = $_POST['days']  ?? [];
$rate  = $_POST['rate']  ?? [];

if (!$your_name || !$start_date || !$end_date || !$your_hst || !$your_address || !$your_phone) {
  http_response_code(422);
  exit('Missing required fields.');
}
if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $start_date) || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $end_date)) {
  http_response_code(422);
  exit('Invalid dates.');
}
if ($start_date > $end_date) {
  http_response_code(422);
  exit('End date before start date.');
}
if (!is_array($desc) || !is_array($daysA) || !is_array($rate) || count($desc) === 0) {
  http_response_code(422);
  exit('Need at least one line item.');
}

// ---------- MATH ----------
$items = [];
$total_incl = 0.0;
for ($i = 0; $i < count($desc); $i++) {
  $dsc = trim((string)($desc[$i] ?? ''));
  $d   = (int)($daysA[$i] ?? 0);
  $r   = (float)($rate[$i] ?? 0);
  if ($dsc === '' || $d <= 0 || $r < 0) continue;

  $line_incl = round($d * $r, 2);
  $items[] = ['desc' => $dsc, 'days' => $d, 'rate_incl' => $r, 'line_incl' => $line_incl];
  $total_incl += $line_incl;
}
if (empty($items)) {
  http_response_code(422);
  exit('No valid line items.');
}

$subtotal = round($total_incl / (1 + HST_RATE), 2);
$hst_amt  = round($total_incl - $subtotal, 2);

// ---------- DB SAVE ----------
$mysqli = db();
$mysqli->begin_transaction();
try {

  // 1) Always have a non-null placeholder invoice number
  $tmpInvNo = 'PENDING';
  // If you might insert multiple at once and have a UNIQUE index on invoice_no,
  // use a unique placeholder instead:
  // $tmpInvNo = 'PENDING-'.time().'-'.mt_rand(1000,9999);

  // 2) Constants must be copied to variables (bind_param needs references)
  $bill_to_name    = BILL_TO_NAME;
  $bill_to_address = BILL_TO_ADDRESS;
  $bill_to_phone   = BILL_TO_PHONE;
  $bill_to_hst     = BILL_TO_HST;

  // 3) Ensure decimals are real floats
  $subtotal_f = (float)$subtotal;
  $hst_amt_f  = (float)$hst_amt;
  $total_f    = (float)$total_incl;

  // 4) Prepare + bind (types = 17 params: ssssssssssss ddd ss)
  $stmt = $mysqli->prepare("
  INSERT INTO invoices
    (invoice_no, start_date, end_date,
     from_name, from_address, from_phone, from_email, from_hst,
     bill_to_name, bill_to_address, bill_to_phone, bill_to_hst,
     subtotal_excl_hst, hst_amount, total_incl_hst,
     client_ip, user_agent)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

  $stmt->bind_param(
    'ssssssssssssdddss',
    $tmpInvNo,
    $start_date,
    $end_date,
    $your_name,
    $your_address,
    $your_phone,
    $your_email,
    $your_hst,
    $bill_to_name,
    $bill_to_address,
    $bill_to_phone,
    $bill_to_hst,
    $subtotal_f,
    $hst_amt_f,
    $total_f,
    $ip,
    $ua
  );

  $stmt->execute();
  $invoice_id = (int)$mysqli->insert_id;
  $stmt->close();

  // Then update it to the real number
  $invoice_no = sprintf('AM-%06d', $invoice_id);
  $stmt = $mysqli->prepare("UPDATE invoices SET invoice_no=? WHERE id=?");
  $stmt->bind_param('si', $invoice_no, $invoice_id);
  $stmt->execute();
  $stmt->close();


  $stmt = $mysqli->prepare("
  INSERT INTO invoice_items
    (invoice_id, line_no, description, days, rate_incl_hst, amount_incl)
  VALUES (?,?,?,?,?,?)
");
  $line_no = 0;
  foreach ($items as $it) {
    $line_no++;
    $descVar = $it['desc'];           // make sure it's a variable
    $daysVar = (int)$it['days'];
    $rateVar = (float)$it['rate_incl'];
    $amtVar  = (float)$it['line_incl'];

    $stmt->bind_param('iisidd', $invoice_id, $line_no, $descVar, $daysVar, $rateVar, $amtVar);
    $stmt->execute();
  }
  $stmt->close();



  $mysqli->commit();
} catch (Throwable $e) {
  $mysqli->rollback();
  @mkdir(__DIR__ . '/logs', 0755, true);
  @file_put_contents(
    __DIR__ . '/logs/db.log',
    date('c') . " | " . $e->getMessage() . "\n",
    FILE_APPEND
  );
  http_response_code(500);
  exit('Failed to save invoice.');
}

// ---------- EMAIL (no logo; “Self Employee” line) ----------
function htxt($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money2($n)
{
  return '$' . number_format((float)$n, 2);
}

$rows_html = '';
foreach ($items as $it) {
  $rows_html .= '<tr>' .
    '<td style="padding:6px;border-top:1px solid #eee;">' . htxt($it['desc']) . '</td>' .
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">' . (int)$it['days'] . '</td>' .
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">' . money2($it['rate_incl']) . '</td>' .
    '<td style="padding:6px;border-top:1px solid #eee;text-align:right;">' . money2($it['line_incl']) . '</td>' .
    '</tr>';
}

$bill_to_html = '<strong>' . htxt(BILL_TO_NAME) . '</strong><br>' .
  nl2br(htxt(BILL_TO_ADDRESS)) . '<br>' .
  'Phone: ' . htxt(BILL_TO_PHONE);

$from_html = '<strong>' . htxt($your_name) . '</strong><br>' .
  nl2br(htxt($your_address)) . '<br>' .
  'Phone: ' . htxt($your_phone) . '<br>' .
  '<em>Self Employee</em><br>' .
  'HST: ' . htxt($your_hst) . ($your_email ? '<br>Email: ' . htxt($your_email) : '');

$message_html = '
<!doctype html><html><body style="background:#f7f7f7;padding:16px;margin:0;">
  <div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111827;">
    <div style="text-align:center;font-size:22px;font-weight:800;margin-bottom:8px">Invoice ' . $invoice_no . '</div>
    <div style="text-align:center;color:#6b7280;margin-bottom:10px">' . htxt($start_date) . ' &rarr; ' . htxt($end_date) . '</div>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse">
      <tr>
        <td style="vertical-align:top;width:50%;padding-right:10px">
          <h3 style="margin:0 0 6px;font-size:16px;">From</h3>' . $from_html . '
        </td>
        <td style="vertical-align:top;width:50%;padding-left:10px">
          <h3 style="margin:0 0 6px;font-size:16px;">Bill To</h3>' . $bill_to_html . '
        </td>
      </tr>
    </table>

    <h3 style="margin:16px 0 6px;">Items</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">
      <thead>
        <tr>
          <th style="text-align:left;border-bottom:2px solid #111;padding:6px">Description</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Days</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Rate (incl HST)</th>
          <th style="text-align:right;border-bottom:2px solid #111;padding:6px">Amount</th>
        </tr>
      </thead>
      <tbody>' . $rows_html . '</tbody>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;font-size:14px">
      <tr><td style="text-align:right;color:#6b7280">Subtotal (excl. HST)</td><td style="text-align:right;width:160px">' . money2($subtotal) . '</td></tr>
      <tr><td style="text-align:right;color:#6b7280">HST (13%)</td><td style="text-align:right">' . money2($hst_amt) . '</td></tr>
      <tr><td style="text-align:right;font-weight:700;border-top:1px solid #e5e7eb;padding-top:6px">Total</td><td style="text-align:right;font-weight:700;border-top:1px solid #e5e7eb;padding-top:6px">' . money2($total_incl) . '</td></tr>
    </table>
  </div>
</body></html>';

$message_text =
  "Invoice $invoice_no\n" .
  "Period: $start_date -> $end_date\n\n" .
  "From: $your_name\nAddress: $your_address\nPhone: $your_phone\nSelf Employee\nHST: $your_hst\n" .
  ($your_email ? "Email: $your_email\n" : "") .
  "\nBill To: " . BILL_TO_NAME . "\nAddress: " . BILL_TO_ADDRESS . "\nPhone: " . BILL_TO_PHONE . "\n" .
  "Items:\n";
foreach ($items as $it) {
  $message_text .= "- {$it['desc']} | Days: " . (int)$it['days'] . " | Rate(incl): " . money2($it['rate_incl']) . " | Amount: " . money2($it['line_incl']) . "\n";
}
$message_text .= "\nSubtotal: " . money2($subtotal) . "\nHST(13%): " . money2($hst_amt) . "\nTotal: " . money2($total_incl) . "\n";

// ---------- PDF (no logo; centered big INVOICE) ----------
$pdfPath = null;
$autoloads = [__DIR__ . '/vendor/autoload.php', __DIR__ . '/dompdf/autoload.inc.php'];
foreach ($autoloads as $a) {
  if (file_exists($a)) {
    require_once $a;
    break;
  }
}

if (class_exists('\\Dompdf\\Dompdf')) {
  $pdf_html = '
    <!doctype html><html><head><meta charset="utf-8">
    <style>
      body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;color:#111}
      .wrap{padding:12px 16px}
      h1{font-size:26px;text-align:center;margin:0 0 4px}
      .period{color:#555;text-align:center;margin-bottom:10px}
      table{width:100%;border-collapse:collapse;font-size:12px}
      th,td{padding:6px;border-bottom:1px solid #ddd}
      th{text-align:left;border-bottom:2px solid #000}
      .cols{width:100%}
      .col{width:50%;vertical-align:top}
      .totals td{border:0;padding:4px 6px}
      .totals .top{border-top:1px solid #ddd}
    </style></head><body><div class="wrap">
      <h1>INVOICE ' . $invoice_no . '</h1>
      <div class="period">Invoice Dates: ' . htxt($start_date) . ' → ' . htxt($end_date) . '</div>

      <table class="cols">
        <tr>
          <td class="col">
            <strong>From</strong><br>' .
    htxt($your_name) . '<br>' .
    nl2br(htxt($your_address)) . '<br>' .
    'Phone: ' . htxt($your_phone) . '<br>' .
    '<em>Self Employee</em><br>' .
    'HST: ' . htxt($your_hst) . ($your_email ? '<br>Email: ' . htxt($your_email) : '') . '
          </td>
          <td class="col">
            <strong>Bill To</strong><br>' .
    htxt(BILL_TO_NAME) . '<br>' .
    nl2br(htxt(BILL_TO_ADDRESS)) . '<br>' .
    'Phone: ' . htxt(BILL_TO_PHONE) . '
    
          </td>
        </tr>
      </table>

      <h3>Items</h3>
      <table>
        <thead><tr>
          <th>Description</th><th style="text-align:right">Days</th><th style="text-align:right">Rate (incl HST)</th><th style="text-align:right">Amount</th>
        </tr></thead><tbody>';

  foreach ($items as $it) {
    $pdf_html .= '<tr>' .
      '<td>' . htxt($it['desc']) . '</td>' .
      '<td style="text-align:right">' . (int)$it['days'] . '</td>' .
      '<td style="text-align:right">' . money2($it['rate_incl']) . '</td>' .
      '<td style="text-align:right">' . money2($it['line_incl']) . '</td>' .
      '</tr>';
  }

  $pdf_html .= '</tbody></table>
      <table class="totals" style="margin-top:8px">
        <tr><td style="text-align:right;width:80%"><span class="muted">Subtotal (excl. HST)</span></td><td style="text-align:right">' . money2($subtotal) . '</td></tr>
        <tr><td style="text-align:right"><span class="muted">HST (13%)</span></td><td style="text-align:right">' . money2($hst_amt) . '</td></tr>
        <tr><td class="top" style="text-align:right;font-weight:bold">Total</td><td class="top" style="text-align:right;font-weight:bold">' . money2($total_incl) . '</td></tr>
      </table>
    </div></body></html>';

  $options = new \Dompdf\Options();
  $options->set('isRemoteEnabled', true);
  $dompdf = new \Dompdf\Dompdf($options);
  $dompdf->loadHtml($pdf_html, 'UTF-8');
  $dompdf->setPaper('letter', 'portrait');
  $dompdf->render();

  $outDir = __DIR__ . '/pdf/invoices';
  if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
  }
  $pdfPath = $outDir . '/' . $invoice_no . '.pdf';
  @file_put_contents($pdfPath, $dompdf->output());
}

// ---------- SEND MAIL (no logo embeds) ----------
$to     = getenv('MAIL_TO')   ?: 'talis.qualis@gmail.com';
$from   = getenv('MAIL_FROM') ?: 'talis.qualis@gmail.com';
$subject   = 'Invoice ' . $invoice_no . ' from ' . $your_name . ' — ' . $start_date . ' to ' . $end_date;
$attachArr = ($pdfPath && is_file($pdfPath)) ? [$pdfPath] : [];

send_mail($to, $subject, $message_html, $from , 'Invoices Bot', [], [], $attachArr, true, $message_text);

if ($your_email && filter_var($your_email, FILTER_VALIDATE_EMAIL)) {
  send_mail($your_email, 'Copy: ' . $subject, $message_html, $from , 'Invoices Bot', [], [], $attachArr, true, $message_text);
}

// ---------- Thank you ----------
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>Sent</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="container">
    <div class="card" style="text-align:center">
      <h2>Invoice <?= h($invoice_no) ?> sent.</h2>
      <p><a class="btn" href="index.php">Create another</a></p>
    </div>
  </div>
</body>

</html>