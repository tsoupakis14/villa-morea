<?php
/**
 * VILLA MOREA — contact form handler (Papaki / any PHP 7.4+ hosting)
 * =================================================================
 * SETTINGS — edit only this block.
 *
 * $TO    Address(es) that receive the enquiries. To add more, list them:
 *        ['tsoupakis14@gmail.com', 'bookings@example.com']
 * $FROM  Sender address. MUST be on the website's own domain
 *        (e.g. noreply@villa-morea.gr), otherwise Gmail/Outlook may
 *        send the message to spam. Empty = noreply@<current domain>.
 *
 * SMTP (recommended for best delivery): fill in the mailbox details
 * from the Papaki panel (Email → mailbox settings). Leave 'host'
 * empty to use PHP's built-in mail() instead.
 *   Papaki usually: host 'mail.<your-domain>', port 465, secure 'ssl'
 *   (or port 587, secure 'tls'), user = full email, pass = mailbox password.
 * =================================================================
 */
$TO   = ['tsoupakis14@gmail.com'];
$FROM = '';
$SUBJECT_PREFIX = 'Villa Morea – Enquiry';

$SMTP = [
  'host'   => '',        // e.g. 'mail.villa-morea.gr'  (empty = use mail())
  'port'   => 465,       // 465 (ssl) or 587 (tls)
  'secure' => 'ssl',     // 'ssl' or 'tls'
  'user'   => '',        // e.g. 'noreply@villa-morea.gr'
  'pass'   => '',        // mailbox password
];

$RATE_LIMIT_PER_HOUR = 5;  // max enquiries per visitor IP per hour

/* Google reCAPTCHA v3 — SECRET key from https://www.google.com/recaptcha/admin
   (the SITE key goes in assets/js/config.js → RECAPTCHA_SITE_KEY).
   Empty = reCAPTCHA check off. */
$RECAPTCHA_SECRET    = '';
$RECAPTCHA_MIN_SCORE = 0.5;   // 0.0 (bot) … 1.0 (human)
/* ================================================================ */

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function vm_finish($ok, $wantsJson, $code = '') {
    if ($wantsJson) {
        http_response_code($ok ? 200 : ($code === 'mail' || $code === 'not_configured' ? 500 : 400));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $code]);
        exit;
    }
    $lang = isset($_POST['lang']) && in_array($_POST['lang'], ['el', 'de', 'it'], true) ? '/' . $_POST['lang'] . '/' : '/';
    header('Location: ' . $lang . '?form=' . ($ok ? 'sent' : 'error') . '#contact', true, 303);
    exit;
}
function vm_cut($v, $max) { return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max); }
function f($k, $max = 200) {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return vm_cut($v, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /', true, 303); exit; }

/* ---- Spam protection: honeypot + minimum time on page + rate limit ---- */
if (!empty($_POST['website'])) vm_finish(true, $wantsJson);            // bots: pretend success
$ts = isset($_POST['ts']) ? (float)$_POST['ts'] : 0;
if ($ts > 0 && (microtime(true) * 1000 - $ts) < 3000) vm_finish(true, $wantsJson);

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0';
$rlFile = rtrim(sys_get_temp_dir(), '/\\') . '/vm_rl_' . md5($ip);
$hits = [];
if (is_readable($rlFile)) { $hits = array_filter(explode(',', (string)@file_get_contents($rlFile)), function ($t) { return $t > time() - 3600; }); }
if (count($hits) >= $RATE_LIMIT_PER_HOUR) vm_finish(false, $wantsJson, 'rate');

/* ---- Validation ---- */
$name = f('name', 120);  $phone = f('phone', 40);  $email = f('email', 160);
$guests = (int)f('guests', 3);  $arrival = f('arrival', 10);  $departure = f('departure', 10);
$message = f('message', 3000);  $consent = f('consent', 2);
$lang = in_array(f('lang', 2), ['en', 'el', 'de', 'it'], true) ? f('lang', 2) : 'en';

$err = '';
if ($name === '' || $message === '' || $consent !== '1') $err = 'missing';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'email';
elseif (strlen(preg_replace('/\D/', '', $phone)) < 6) $err = 'phone';
elseif ($guests < 1 || $guests > 9) $err = 'guests';
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure) || $departure <= $arrival) $err = 'dates';
if ($err) vm_finish(false, $wantsJson, $err);

/* ---- Google reCAPTCHA v3 check ---- */
if ($RECAPTCHA_SECRET !== '') {
    $token = isset($_POST['g-recaptcha-response']) ? (string)$_POST['g-recaptcha-response'] : '';
    if ($token === '') vm_finish(false, $wantsJson, 'captcha');
    $post = http_build_query(['secret' => $RECAPTCHA_SECRET, 'response' => $token, 'remoteip' => $ip]);
    $resp = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = curl_exec($ch);
        curl_close($ch);
    } else {
        $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $post, 'timeout' => 10]]));
    }
    $rc = $resp ? json_decode($resp, true) : null;
    $okCaptcha = is_array($rc) && !empty($rc['success'])
        && (!isset($rc['score']) || $rc['score'] >= $RECAPTCHA_MIN_SCORE)
        && (!isset($rc['action']) || $rc['action'] === 'contact');
    if (!$okCaptcha) vm_finish(false, $wantsJson, 'captcha');
}
$TO = array_values(array_filter(array_map('trim', (array)$TO), function ($a) { return filter_var($a, FILTER_VALIDATE_EMAIL); }));
if (!$TO) vm_finish(false, $wantsJson, 'not_configured');

/* ---- Build the message ---- */
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$host = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', $host));
$host = preg_replace('/[^a-z0-9.\-]/i', '', $host);
$from = $FROM !== '' ? $FROM : ($SMTP['user'] !== '' ? $SMTP['user'] : 'noreply@' . $host);
$nameHeader = trim(preg_replace('/[^\p{L}\p{N} .\'\-]/u', '', $name));
$nights = (int)round((strtotime($departure) - strtotime($arrival)) / 86400);

$body  = "New enquiry from the Villa Morea website\n";
$body .= "==========================================\n\n";
$body .= "Name:       $name\n";
$body .= "Phone:      $phone\n";
$body .= "Email:      $email\n";
$body .= "Guests:     $guests\n";
$body .= "Arrival:    $arrival\n";
$body .= "Departure:  $departure  ($nights nights)\n";
$body .= "Language:   " . strtoupper($lang) . "\n";
$body .= "Consent:    Privacy Policy & Terms and Cancellations accepted\n\n";
$body .= "Message:\n$message\n\n";
$body .= "--\nReply to this email to answer the guest directly.\n";
$body .= "Sent " . gmdate('Y-m-d H:i') . " UTC from $ip\n";

$subject = "$SUBJECT_PREFIX: $nameHeader ($arrival → $departure, $guests)";
$enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };

/* ---- Minimal SMTP client (SSL/TLS + AUTH LOGIN) ---- */
function vm_smtp($cfg, $from, $to, $replyTo, $replyName, $subject, $body, $enc, $host) {
    $remote = ($cfg['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . (int)$cfg['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $out = ''; while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $out; };
    $cmd = function ($c, $expect) use ($fp, $read) { if ($c !== null) fwrite($fp, $c . "\r\n"); $r = $read(); return strpos($r, (string)$expect) === 0; };
    if (!$cmd(null, 220)) return false;
    if (!$cmd('EHLO ' . $host, 250)) return false;
    if ($cfg['secure'] === 'tls') {
        if (!$cmd('STARTTLS', 220)) return false;
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return false;
        if (!$cmd('EHLO ' . $host, 250)) return false;
    }
    if ($cfg['user'] !== '') {
        if (!$cmd('AUTH LOGIN', 334)) return false;
        if (!$cmd(base64_encode($cfg['user']), 334)) return false;
        if (!$cmd(base64_encode($cfg['pass']), 235)) return false;
    }
    if (!$cmd("MAIL FROM:<$from>", 250)) return false;
    foreach ($to as $rcpt) { if (!$cmd("RCPT TO:<$rcpt>", 250)) return false; }
    if (!$cmd('DATA', 354)) return false;
    $msg  = "Date: " . date('r') . "\r\n";
    $msg .= "From: " . $enc('Villa Morea Website') . " <$from>\r\n";
    $msg .= "To: " . implode(', ', array_map(function ($a) { return "<$a>"; }, $to)) . "\r\n";
    $msg .= "Reply-To: " . $enc($replyName) . " <$replyTo>\r\n";
    $msg .= "Subject: " . $enc($subject) . "\r\n";
    $msg .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@$host>\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($body));
    if (!$cmd($msg . "\r\n.", 250)) return false;
    $cmd('QUIT', 221);
    fclose($fp);
    return true;
}

/* ---- Send ---- */
if ($SMTP['host'] !== '') {
    $sent = vm_smtp($SMTP, $from, $TO, $email, $nameHeader, $subject, $body, $enc, $host);
} else {
    $headers  = "From: " . $enc('Villa Morea Website') . " <$from>\r\n";
    $headers .= "Reply-To: " . $enc($nameHeader) . " <$email>\r\n";
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    $sent = @mail(implode(', ', $TO), $enc($subject), chunk_split(base64_encode($body)), $headers, '-f' . $from);
}

if ($sent) { $hits[] = time(); @file_put_contents($rlFile, implode(',', $hits)); }
vm_finish($sent, $wantsJson, $sent ? '' : 'mail');
