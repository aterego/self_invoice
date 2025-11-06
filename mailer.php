<?php
if (!function_exists('ensure_log_dir')) {
  function ensure_log_dir(){ $d = __DIR__ . '/logs'; if (!is_dir($d)) { @mkdir($d, 0755, true); } return $d; }
}

if (!function_exists('send_mail')) {
  /**
   * @param string|array $to
   * @param string $subject
   * @param string $body
   * @param string $from
   * @param string $from_name
   * @param array  $cc
   * @param array  $bcc
   * @param array  $attachments Absolute file paths
   * @param bool   $is_html     Set true to send HTML email
   * @param ?string $alt_body   Optional plain-text alternative
   */
  function send_mail($to, $subject, $body, $from, $from_name = 'Invoice', $cc = [], $bcc = [], $attachments = [], $is_html = false, $altText = '', $embeds = []){
    ensure_log_dir();
    $logFile = __DIR__ . '/logs/email.log';
    // accept array OR comma/semicolon-separated string
    $to_list_raw = is_array($to) ? $to : preg_split('/[;,]+/', (string)$to, -1, PREG_SPLIT_NO_EMPTY);
    $to_list = array_values(array_filter(array_map('trim', $to_list_raw)));


    $transport   = getenv('MAIL_TRANSPORT') ?: 'smtp';
    $smtp_host   = getenv('SMTP_HOST') ?: 'localhost';
    $smtp_port   = (int)(getenv('SMTP_PORT') ?: 587);
    $smtp_user   = getenv('SMTP_USER') ?: '';
    $smtp_pass   = getenv('SMTP_PASS') ?: '';
    $smtp_secure = getenv('SMTP_SECURE') ?: 'tls';
    $smtp_auth_v = getenv('SMTP_AUTH');
    $smtp_auth   = ($smtp_auth_v === false || $smtp_auth_v === '') ? ($smtp_user !== '' ? true : false)
                                                                   : in_array(strtolower((string)$smtp_auth_v), ['1','true','yes'], true);

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
      require_once $autoload;
      try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        if ($transport === 'smtp') {
          $mail->isSMTP();
          $mail->Host = $smtp_host;
          $mail->Port = $smtp_port;
          $mail->SMTPAuth = $smtp_auth;
          if ($mail->SMTPAuth) { $mail->Username = $smtp_user; $mail->Password = $smtp_pass; }
          if ($smtp_secure === 'ssl' || $smtp_secure === 'tls') { $mail->SMTPSecure = $smtp_secure; }

          // Optional debug to logs/smtp_debug.log
          if (getenv('SMTP_DEBUG') === '1') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str){
              @file_put_contents(__DIR__.'/logs/smtp_debug.log', $str.PHP_EOL, FILE_APPEND);
            };
          }
        } elseif ($transport === 'sendmail') {
          $mail->isSendmail();
        } else {
          $mail->isMail();
        }

        $mail->setFrom($from, $from_name);
        foreach ($to_list as $addr) { if ($addr) $mail->addAddress($addr); }
        foreach ((array)$cc as $addr)  { if ($addr) $mail->addCC($addr); }
        foreach ((array)$bcc as $addr) { if ($addr) $mail->addBCC($addr); }

        // Embeds (CID images)
        foreach ((array)$embeds as $e) {
            if (!empty($e['path']) && is_file($e['path'])) {
                $cid  = $e['cid']  ?? ('cid_' . md5($e['path']));
                $name = $e['name'] ?? basename($e['path']);
                $mail->addEmbeddedImage($e['path'], $cid, $name);
            }
        }

        foreach ((array)$attachments as $path) { if ($path && is_file($path)) $mail->addAttachment($path); }

        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altText  ?? ($is_html
          ? strip_tags(str_replace(['<br>','<br/>','<br />','</p>'], ["\n","\n","\n","\n"], $body))
          : $body
        );

        $ok = $mail->send();
        if (!$ok) {
          @file_put_contents($logFile, date('c')." | PHPMailer send failed: ".$mail->ErrorInfo."\nTo: ".implode(',', $to_list)." | Subject: $subject\n\n", FILE_APPEND);
        }
        return $ok;
      } catch (\Throwable $e) {
        @file_put_contents($logFile, date('c')." | PHPMailer exception: ".$e->getMessage()."\nTo: ".implode(',', $to_list)." | Subject: $subject\n\n", FILE_APPEND);
        return false;
      }
    }

    // Fallback to native mail()
    $headers = "From: {$from}\r\n";
    if ($is_html) {
      $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    }
    $ok = @mail(implode(',', $to_list), $subject, $body, $headers);
    if (!$ok) {
      @file_put_contents($logFile, date('c')." | mail() fallback failed\nTo: ".implode(',', $to_list)." | Subject: $subject\n\n", FILE_APPEND);
    }
    return $ok;
  }
}
