<?php
/**
 * VILLA MOREA — contact form handler (Papaki / PHP 7.4+)
 * ---------------------------------------------------------------
 * 1. Put the address that should receive enquiries in $TO below.
 * 2. $FROM must be an address on YOUR domain (e.g. noreply@villamorea.gr),
 *    otherwise many mail servers will mark the message as spam.
 *    Create it as a mailbox/alias in the Papaki panel if needed.
 * ---------------------------------------------------------------
 */
$TO   = '';                       // e.g. 'info@villamorea.gr'  (REQUIRED)
$FROM = '';                       // e.g. 'noreply@villamorea.gr' (empty = noreply@<your domain>)
$SUBJECT_PREFIX = 'Villa Morea – Enquiry';

header('X-Content-Type-Options: nosniff');
$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function vm_finish($ok, $wantsJson, $code = '') {
    if ($wantsJson) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $code]);
        exit;
    }
    $lang = isset($_POST['lang']) && in_array($_POST['lang'], ['el', 'de', 'it'], true) ? '/' . $_POST['lang'] . '/' : '/';
    header('Location: ' . $lang . '?form=' . ($ok ? 'sent' : 'error') . '#contact', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /', true, 303); exit; }

// Spam protection: honeypot + minimum time on page (3 s)
if (!empty($_POST['website'])) vm_finish(true, $wantsJson);        // pretend success to bots
$ts = isset($_POST['ts']) ? (int)$_POST['ts'] : 0;
if ($ts > 0 && (time() * 1000 - $ts) < 3000) vm_finish(true, $wantsJson);

function f($k, $max = 200) {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}
$name = f('name', 120);  $phone = f('phone', 40);  $email = f('email', 160);
$guests = (int)f('guests', 3);  $arrival = f('arrival', 10);  $departure = f('departure', 10);
$message = f('message', 3000);  $consent = f('consent', 2);  $lang = f('lang', 2);

$err = '';
if ($name === '' || $message === '' || $consent !== '1') $err = 'missing';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'email';
elseif (strlen(preg_replace('/\D/', '', $phone)) < 6) $err = 'phone';
elseif ($guests < 1 || $guests > 10) $err = 'guests';
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure) || $departure <= $arrival) $err = 'dates';
if ($err) vm_finish(false, $wantsJson, $err);

if ($TO === '') vm_finish(false, $wantsJson, 'not_configured');

$host = preg_replace('/^www\./', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
$host = preg_replace('/:\d+$/', '', $host);
$host = preg_replace('/[^a-z0-9.\-]/i', '', $host);
$from = $FROM !== '' ? $FROM : 'noreply@' . $host;
$nameHeader = preg_replace('/[^\p{L}\p{N} .\'\-]/u', '', $name);

$body  = "New enquiry from the Villa Morea website\n";
$body .= "==========================================\n\n";
$body .= "Name:       $name\n";
$body .= "Phone:      $phone\n";
$body .= "Email:      $email\n";
$body .= "Guests:     $guests\n";
$body .= "Arrival:    $arrival\n";
$body .= "Departure:  $departure\n";
$body .= "Language:   " . strtoupper($lang) . "\n";
$body .= "Consent:    Privacy Policy & Terms and Cancellations accepted\n\n";
$body .= "Message:\n$message\n\n";
$body .= "--\nSent " . gmdate('Y-m-d H:i') . " UTC from " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . "\n";

$subject = '=?UTF-8?B?' . base64_encode("$SUBJECT_PREFIX: $nameHeader ($arrival → $departure, $guests)") . '?=';
$headers  = "From: Villa Morea Website <$from>\r\n";
$headers .= "Reply-To: =?UTF-8?B?" . base64_encode($nameHeader) . "?= <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";

$sent = @mail($TO, $subject, $body, $headers, '-f' . $from);
vm_finish($sent, $wantsJson, $sent ? '' : 'mail');
