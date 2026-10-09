<?php
/**
 * VILLA MOREA — contact form handler (Papaki / any PHP 7.4+ hosting)
 * =================================================================
 * SETTINGS — edit only this block.
 *
 * $TO    Address(es) that receive the enquiries. To add more, list them:
 *        ['villamorea@gmail.com', 'bookings@example.com']
 * $FROM  Sender address. MUST be on the website's own domain
 *        (e.g. noreply@villamorea.gr), otherwise Gmail/Outlook may
 *        send the message to spam. Empty = SMTP user, or noreply@<domain>.
 *
 * SMTP (recommended for best delivery): fill in the mailbox details
 * from Plesk (Mail → mailbox). Leave 'host' empty to use PHP mail().
 *   Usually: host 'mail.villamorea.gr', port 465, secure 'ssl'
 *   (or port 587, secure 'tls'), user = full email, pass = mailbox password.
 *
 * AUTO-REPLY: the guest receives a confirmation email ("we received
 * your enquiry") in the language of the page. Replies to it go to
 * $AUTOREPLY_REPLY_TO.
 * =================================================================
 */
$TO   = ['villamorea@gmail.com'];
$FROM = '';
$SUBJECT_PREFIX = 'Villa Morea – Enquiry';

$SMTP = [
  'host'   => '',        // e.g. 'mail.villamorea.gr'  (empty = use mail())
  'port'   => 465,       // 465 (ssl) or 587 (tls)
  'secure' => 'ssl',     // 'ssl' or 'tls'
  'user'   => '',        // e.g. 'noreply@villamorea.gr'
  'pass'   => '',        // mailbox password
];

$AUTOREPLY          = true;                     // false = no confirmation email to the guest
$AUTOREPLY_REPLY_TO = 'info@villamorea.gr';     // where guests' replies to the confirmation go
$SITE_PHONE         = '+30 210 300 2211';
$SITE_URL           = 'https://www.villamorea.gr';
$BOOKING_URL        = 'https://villamorea.hotelyzer.gr/en';   // online booking (mentioned in the confirmation)

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
    $lc = isset($_POST['lang']) && in_array($_POST['lang'], ['el', 'de', 'it'], true) ? $_POST['lang'] : '';
    $base = $lc ? '/' . $lc . '/' : '/';
    $thanks = $base . 'thank-you.html';
    if ($wantsJson) {
        http_response_code($ok ? 200 : ($code === 'mail' || $code === 'not_configured' ? 500 : 400));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $code, 'redirect' => $ok ? $thanks : '']);
        exit;
    }
    header('Location: ' . ($ok ? $thanks : $base . '?form=error#contact'), true, 303);
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
$guests = (int)f('guests', 3);
$message = f('message', 3000);  $consent = f('consent', 2);
$lang = in_array(f('lang', 2), ['en', 'el', 'de', 'it'], true) ? f('lang', 2) : 'en';

$err = '';
if ($name === '' || $message === '' || $consent !== '1') $err = 'missing';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'email';
elseif (strlen(preg_replace('/\D/', '', $phone)) < 6) $err = 'phone';
elseif ($guests < 1 || $guests > 9) $err = 'guests';
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

$body  = "New enquiry from the Villa Morea website\n";
$body .= "==========================================\n\n";
$body .= "Name:       $name\n";
$body .= "Phone:      $phone\n";
$body .= "Email:      $email\n";
$body .= "Guests:     $guests\n";
$body .= "Language:   " . strtoupper($lang) . "\n";
$body .= "Consent:    Privacy Policy & Terms and Cancellations accepted\n\n";
$body .= "Message:\n$message\n\n";
$body .= "--\nReply to this email to answer the guest directly.\n";
$body .= "Sent " . gmdate('Y-m-d H:i') . " UTC from $ip\n";

$subject = "$SUBJECT_PREFIX: $nameHeader ($guests guests)";
$enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };

/* ---- Minimal SMTP client (SSL/TLS + AUTH LOGIN) ---- */
function vm_smtp($cfg, $from, $to, $data, $host) {
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
    if (!$cmd($data . "\r\n.", 250)) return false;
    $cmd('QUIT', 221);
    fclose($fp);
    return true;
}

/* ---- Send one email (plain text, optional HTML version) via SMTP or mail() ---- */
function vm_send($o) {
    global $SMTP, $enc, $host;
    $b = 'vm' . bin2hex(random_bytes(8));
    $h  = "From: " . $enc($o['fromName']) . " <{$o['from']}>\r\n";
    $h .= "Reply-To: " . $enc($o['replyName']) . " <{$o['replyTo']}>\r\n";
    $h .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@$host>\r\n";
    if (!empty($o['auto'])) $h .= "Auto-Submitted: auto-replied\r\nX-Auto-Response-Suppress: All\r\n";
    $h .= "MIME-Version: 1.0\r\n";
    if (!empty($o['html'])) {
        $h .= "Content-Type: multipart/alternative; boundary=\"$b\"\r\n";
        $body  = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($o['text']));
        $body .= "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($o['html']));
        $body .= "--$b--\r\n";
    } else {
        $h .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
        $body = chunk_split(base64_encode($o['text']));
    }
    if ($SMTP['host'] !== '') {
        $data  = "Date: " . date('r') . "\r\n";
        $data .= "To: " . implode(', ', array_map(function ($a) { return "<$a>"; }, $o['to'])) . "\r\n";
        $data .= "Subject: " . $enc($o['subject']) . "\r\n" . $h . "\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data);   // SMTP dot-stuffing
        return vm_smtp($SMTP, $o['from'], $o['to'], $data, $host);
    }
    return @mail(implode(', ', $o['to']), $enc($o['subject']), $body, rtrim($h, "\r\n"), '-f' . $o['from']);
}

/* ---- 1. Enquiry to the villa ---- */
$sent = vm_send([
    'to' => $TO, 'from' => $from, 'fromName' => 'Villa Morea Website',
    'replyTo' => $email, 'replyName' => $nameHeader,
    'subject' => $subject, 'text' => $body,
]);

/* ---- 2. Confirmation to the guest (only if the enquiry went out) ---- */
if ($sent && $AUTOREPLY) {
    $T = [
      'en' => ['subj' => 'We received your message – Villa Morea', 'hi' => 'Dear %s,',
               'p1' => 'Thank you for contacting Villa Morea. We have received your message and will reply as soon as possible.',
               'nb' => 'Please note: this message is not a booking. To book the villa, check availability and reserve online at %s.',
               'p2' => 'If you need anything in the meantime, simply reply to this email or call us on %s.',
               'bye' => 'Kind regards,', 'tag' => 'Private pool villa in Mournies, Chania, Crete',
               'auto' => 'This is an automatic confirmation of the contact form on villamorea.gr.'],
      'el' => ['subj' => 'Λάβαμε το μήνυμά σας – Villa Morea', 'hi' => 'Γεια σας %s,',
               'p1' => 'Σας ευχαριστούμε που επικοινωνήσατε με τη Villa Morea. Λάβαμε το μήνυμά σας και θα σας απαντήσουμε το συντομότερο.',
               'nb' => 'Σημείωση: το μήνυμα αυτό δεν αποτελεί κράτηση. Για να κάνετε κράτηση, δείτε τη διαθεσιμότητα και κλείστε online στο %s.',
               'p2' => 'Αν χρειάζεστε κάτι στο μεταξύ, απαντήστε απλώς σε αυτό το email ή καλέστε μας στο %s.',
               'bye' => 'Με εκτίμηση,', 'tag' => 'Βίλα με ιδιωτική πισίνα στις Μουρνιές Χανίων, Κρήτη',
               'auto' => 'Αυτό είναι ένα αυτόματο email επιβεβαίωσης της φόρμας επικοινωνίας του villamorea.gr.'],
      'de' => ['subj' => 'Wir haben Ihre Nachricht erhalten – Villa Morea', 'hi' => 'Guten Tag %s,',
               'p1' => 'vielen Dank, dass Sie die Villa Morea kontaktiert haben. Wir haben Ihre Nachricht erhalten und antworten so schnell wie möglich.',
               'nb' => 'Bitte beachten Sie: Diese Nachricht ist keine Buchung. Um die Villa zu buchen, prüfen Sie die Verfügbarkeit und buchen Sie online unter %s.',
               'p2' => 'Wenn Sie in der Zwischenzeit etwas benötigen, antworten Sie einfach auf diese E-Mail oder rufen Sie uns an: %s.',
               'bye' => 'Mit freundlichen Grüßen', 'tag' => 'Villa mit privatem Pool in Mournies, Chania, Kreta',
               'auto' => 'Dies ist eine automatische Bestätigung des Kontaktformulars auf villamorea.gr.'],
      'it' => ['subj' => 'Abbiamo ricevuto il vostro messaggio – Villa Morea', 'hi' => 'Gentile %s,',
               'p1' => 'grazie per aver contattato Villa Morea. Abbiamo ricevuto il vostro messaggio e vi risponderemo il prima possibile.',
               'nb' => 'Nota: questo messaggio non è una prenotazione. Per prenotare la villa, verificate la disponibilità e prenotate online su %s.',
               'p2' => 'Se nel frattempo avete bisogno di qualcosa, rispondete semplicemente a questa email o chiamateci al %s.',
               'bye' => 'Cordiali saluti,', 'tag' => 'Villa con piscina privata a Mournies, Chania, Creta',
               'auto' => 'Questa è una conferma automatica del modulo di contatto su villamorea.gr.'],
    ][$lang];
    // Only the (filtered) name goes into the guest email — no free text from the form.
    $gName = trim(vm_cut(preg_replace('#\S*\.[a-z]{2,}\S*#i', '', $nameHeader), 60));
    $hi  = $gName !== '' ? sprintf($T['hi'], $gName) : rtrim(sprintf($T['hi'], ''), ' ,') . ',';
    $p2  = sprintf($T['p2'], $SITE_PHONE);
    $pnb = sprintf($T['nb'], $BOOKING_URL);
    $txt  = "$hi\n\n{$T['p1']}\n\n$pnb\n\n";
    $txt .= "$p2\n\n{$T['bye']}\nVilla Morea\n{$T['tag']}\n$SITE_URL\n\n--\n{$T['auto']}\n";
    $hs = function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
    $nbHtml = str_replace($hs($BOOKING_URL), '<a href="' . $hs($BOOKING_URL) . '" style="color:#113445">' . $hs(preg_replace('#^https?://#', '', $BOOKING_URL)) . '</a>', $hs($pnb));
    $html = '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . $hs($T['subj']) . '</title></head>'
      . '<body style="margin:0;padding:0;background:#faf7f0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1e231f">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f0"><tr><td align="center" style="padding:24px 12px">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e6e1d6">'
      . '<tr><td align="center" style="background:#113445;padding:22px 24px;border-bottom:2px solid #c9a877"><img src="' . $SITE_URL . '/assets/images/logo/villa-morea-logo-email.png" width="200" alt="Villa Morea" style="display:block;border:0;width:200px;height:auto;color:#ffffff;font-size:20px"></td></tr>'
      . '<tr><td style="padding:28px 28px 8px">'
      . '<p style="margin:0 0 14px">' . $hs($hi) . '</p>'
      . '<p style="margin:0 0 20px">' . $hs($T['p1']) . '</p>'
      . '<p style="margin:0 0 20px;padding:12px 14px;background:#faf7f0;border-left:3px solid #c9a877;font-size:14px">' . $nbHtml . '</p>'
      . '<p style="margin:0 0 20px">' . $hs($p2) . '</p>'
      . '<p style="margin:0">' . $hs($T['bye']) . '<br><strong>Villa Morea</strong><br><span style="color:#4a5049">' . $hs($T['tag']) . '</span><br><a href="' . $SITE_URL . '" style="color:#113445">www.villamorea.gr</a></p>'
      . '</td></tr><tr><td style="padding:20px 28px 24px;font-size:12px;color:#8a8f88">' . $hs($T['auto']) . '</td></tr>'
      . '</table></td></tr></table></body></html>';
    $replyTo = filter_var($AUTOREPLY_REPLY_TO, FILTER_VALIDATE_EMAIL) ? $AUTOREPLY_REPLY_TO : $TO[0];
    vm_send([
        'to' => [$email], 'from' => $from, 'fromName' => 'Villa Morea',
        'replyTo' => $replyTo, 'replyName' => 'Villa Morea',
        'subject' => $T['subj'], 'text' => $txt, 'html' => $html, 'auto' => true,
    ]);   // a failed confirmation never blocks the enquiry
}

if ($sent) { $hits[] = time(); @file_put_contents($rlFile, implode(',', $hits)); }
vm_finish($sent, $wantsJson, $sent ? '' : 'mail');
