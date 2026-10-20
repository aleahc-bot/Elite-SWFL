<?php
/**
 * Elite Electrical Contractors — contact form handler
 *
 * The "Schedule service" form on contact.html posts here. This script emails the
 * submission itself, on the server. The visitor's computer is never involved:
 * no Outlook, no Apple Mail, no "choose a mail provider" dialog. They click
 * Send request, they see a thank-you, and the email lands in the inbox below.
 *
 * Upload this file into the same folder as contact.html.
 *
 * ================================================================== SETTINGS */

$TO        = 'jennifer@elite1314.com';    // where submissions go
$SUBJECT   = 'Website service request';
$FROM      = 'service@elite1314.com';     // the address the email appears to come from
$FROM_NAME = 'Elite Electrical website';

/* ---- OPTIONAL: nothing below needs changing -------------------------------
 *
 * Left as-is, this script sends through the web host's own mail system. Upload
 * it and the form works — no accounts, no keys, no setup.
 *
 * ONLY come back here if a test submission never reaches the inbox (check the
 * spam folder first). Filling this in signs the email cryptographically so
 * Gmail and Outlook trust it. Pick ONE:
 *
 *  GMAIL / GOOGLE WORKSPACE
 *    host   smtp.gmail.com      port 587      secure 'tls'
 *    user   the full Gmail address you are sending as
 *    pass   an App Password — NOT the normal password. Create one at
 *           myaccount.google.com > Security > 2-Step Verification > App passwords
 *           (2-Step Verification must be on first). It looks like: abcd efgh ijkl mnop
 *    Also set $FROM to that same Gmail address, or Google will rewrite it.
 *
 *  HOSTINGER EMAIL (a mailbox you created in hPanel)
 *    host   smtp.hostinger.com  port 465      secure 'ssl'
 *    user   the full mailbox address        pass   its password
 */

$SMTP = [
    'host'   => '',          // '' = use Hostinger's built-in mail instead
    'port'   => 587,
    'secure' => 'tls',       // 'tls' for port 587, 'ssl' for port 465
    'user'   => '',
    'pass'   => '',
];

/* ============================================================== END SETTINGS */

date_default_timezone_set('America/New_York');

// --------------------------------------------------------------- small helpers

/** Strip CR/LF/NUL so a visitor cannot inject extra mail headers. */
function clean(string $v, int $max = 200): string
{
    return trim(mb_substr(str_replace(["\r", "\n", "\0"], ' ', $v), 0, $max));
}

/** Quote a display name for a From/Reply-To header. */
function display_name(string $v): string
{
    return '"' . str_replace(['"', '\\', '<', '>'], '', $v) . '"';
}

/** RFC 2047 encode a subject so accents and dashes survive. */
function enc_subject(string $v): string
{
    return '=?UTF-8?B?' . base64_encode($v) . '?=';
}

/** Did the page's script ask for JSON, or is this a plain form post? */
function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Reply and stop. */
function finish(bool $ok, string $message, int $status = 200): void
{
    if (wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } elseif ($ok) {
        header('Location: contact.html?sent=1');
    } else {
        header('Location: contact.html?sent=0&why=' . rawurlencode($message));
    }
    exit;
}

// ------------------------------------------------------------------ SMTP client

/** Read one SMTP reply (handles multi-line 250-... continuations). */
function smtp_read($fp): string
{
    $out = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $out .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return $out;
}

/** Send one command and require an expected reply code. */
function smtp_cmd($fp, ?string $cmd, string $expect, array &$log): bool
{
    if ($cmd !== null) {
        fwrite($fp, $cmd . "\r\n");
        $log[] = '> ' . (stripos($cmd, 'AUTH') === 0 ? 'AUTH …' : $cmd);
    }
    $reply = smtp_read($fp);
    $log[] = '< ' . trim($reply);
    return str_starts_with($reply, $expect);
}

/**
 * Deliver one message over authenticated SMTP.
 * Returns [ok, log-lines].
 */
function smtp_send(array $cfg, string $from, string $to, string $subject,
                   string $body, array $headers): array
{
    $log  = [];
    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $ssl  = $cfg['secure'] === 'ssl';

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => !($cfg['insecure'] ?? false),
        'verify_peer_name'  => !($cfg['insecure'] ?? false),
        'allow_self_signed' => (bool) ($cfg['insecure'] ?? false),
    ]]);

    $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . "$host:$port",
        $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return [false, ["connect failed: $errstr ($errno)"]];
    }
    stream_set_timeout($fp, 15);

    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $ok = smtp_cmd($fp, null, '220', $log)
       && smtp_cmd($fp, $ehlo, '250', $log);

    if ($ok && !$ssl && $cfg['secure'] === 'tls') {
        $ok = smtp_cmd($fp, 'STARTTLS', '220', $log);
        if ($ok) {
            $ok = (bool) @stream_socket_enable_crypto($fp, true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $log[] = $ok ? '-- TLS established' : '-- TLS handshake failed';
        }
        if ($ok) {
            $ok = smtp_cmd($fp, $ehlo, '250', $log);
        }
    }

    if ($ok && $cfg['user'] !== '') {
        $ok = smtp_cmd($fp, 'AUTH LOGIN', '334', $log)
           && smtp_cmd($fp, base64_encode($cfg['user']), '334', $log)
           && smtp_cmd($fp, base64_encode($cfg['pass']), '235', $log);
    }

    if ($ok) {
        $ok = smtp_cmd($fp, "MAIL FROM:<$from>", '250', $log)
           && smtp_cmd($fp, "RCPT TO:<$to>", '250', $log)
           && smtp_cmd($fp, 'DATA', '354', $log);
    }

    if ($ok) {
        $msg = implode("\r\n", array_merge(
            ['To: ' . $to, 'Subject: ' . $subject],
            $headers,
            ['', $body]
        ));
        // dot-stuffing: a line that is just "." would end the message early
        $msg = preg_replace('/^\./m', '..', $msg);
        fwrite($fp, $msg . "\r\n.\r\n");
        $log[] = '> [message, ' . strlen($msg) . ' bytes]';
        $ok = smtp_cmd($fp, null, '250', $log);
    }

    @smtp_cmd($fp, 'QUIT', '221', $log);
    fclose($fp);
    return [$ok, $log];
}

// --------------------------------------------------------------- attachments

/** What a resume is allowed to be. Anything else is refused by name. */
const RESUME_MAX   = 3145728;            // 3 MB, matching the form's own check
const RESUME_TYPES = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'heic'];

/**
 * Read an uploaded resume, if there is one.
 * Returns [filename, mime, raw bytes] or null. Calls finish() and stops on a
 * file that is present but not acceptable, so the visitor hears about it.
 */
function resume_upload(): ?array
{
    $f = $_FILES['resume'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;                                     // nothing attached
    }
    /* The host has its own upload ceiling (upload_max_filesize), which can be
       lower than our 3 MB. Say what actually happened rather than quote a
       number that may not be the one that bit. */
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        finish(false, 'That file was too large for the server to accept — '
                    . 'please attach a smaller one, or email it to ' . $TO . '.', 413);
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        finish(false, 'That file could not be read — please try attaching it again.', 400);
    }
    if ((int) $f['size'] > RESUME_MAX) {
        finish(false, 'That file is too large — please attach one under 3 MB.', 413);
    }

    $name = preg_replace('/[^\w.\- ]+/u', '_', (string) $f['name']);
    $name = mb_substr(ltrim($name, '.'), 0, 120);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, RESUME_TYPES, true)) {
        finish(false, 'Please attach a PDF, a Word document or a photo.', 415);
    }

    $bytes = (string) file_get_contents($f['tmp_name']);
    if ($bytes === '') {
        finish(false, 'That file came through empty — please try again.', 400);
    }

    $mime = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
    ][$ext];

    return [$name, $mime, $bytes];
}

// ------------------------------------------------------------------ guard rails

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (wants_json()) {
        finish(false, 'Please use the form on our contact page.', 405);
    }
    header('Location: contact.html');       // someone opened this file directly
    exit;
}

// Hidden field no human can see. Bots fill in everything, so a value here means
// spam. Report success so the bot never learns it was caught.
if (clean((string) ($_POST['company'] ?? '')) !== '') {
    finish(true, 'Thank you.');
}

// -------------------------------------------------------------- the submission

$name  = clean((string) ($_POST['name']  ?? ''), 120);
$phone = clean((string) ($_POST['phone'] ?? ''), 60);
$email = clean((string) ($_POST['email'] ?? ''), 190);
$ptype = clean((string) ($_POST['property_type'] ?? ''), 60);

$services = [];
foreach ((array) ($_POST['services'] ?? []) as $s) {
    if (($s = clean((string) $s, 60)) !== '') {
        $services[] = $s;
    }
}
$services = array_slice(array_unique($services), 0, 12);

$problems = [];
if ($name === '') {
    $problems[] = 'your name';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $problems[] = 'a valid email address';
}
if ($problems) {
    finish(false, 'Please add ' . implode(' and ', $problems) . '.', 422);
}

// ------------------------------------------------------------------- compose

$body = implode("\r\n", [
    'New service request from the Elite Electrical website.',
    '',
    'Name:     ' . $name,
    'Phone:    ' . ($phone !== '' ? $phone : '(not given)'),
    'Email:    ' . $email,
    'Property: ' . ($ptype !== '' ? $ptype : '(not given)'),
    'Services: ' . ($services ? implode(', ', $services) : '(none selected)'),
    '',
    'Submitted: ' . date('D j M Y, g:ia T'),
    'Reply to this email to answer the customer directly.',
]) . "\r\n";

$subject = enc_subject($SUBJECT . ' - ' . $name);
$resume  = resume_upload();
if ($resume !== null) {
    $body .= "\r\nFile attached: " . $resume[0] . "\r\n";
}

$headers = [
    'From: ' . sprintf('%s <%s>', display_name($FROM_NAME), $FROM),
    'Reply-To: ' . sprintf('%s <%s>', display_name($name), $email),
    'MIME-Version: 1.0',
    'X-Mailer: elite-site-form',
];

/* With no attachment this stays a plain-text email, exactly as before. With
   one, the same text becomes the first part of a multipart message and the
   file follows it base64-encoded — which both mail() and the SMTP path above
   send without caring which it is. */
if ($resume === null) {
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
} else {
    [$rName, $rMime, $rBytes] = $resume;
    $b = 'elite_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $b . '"';
    $body = implode("\r\n", [
        'This message has an attachment and needs a MIME-capable reader.',
        '',
        '--' . $b,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $body,
        '--' . $b,
        'Content-Type: ' . $rMime . '; name="' . $rName . '"',
        'Content-Transfer-Encoding: base64',
        'Content-Disposition: attachment; filename="' . $rName . '"',
        '',
        trim(chunk_split(base64_encode($rBytes), 76, "\r\n")),
        '--' . $b . '--',
        '',
    ]);
}

// ---------------------------------------------------------------------- send

if ($SMTP['host'] !== '') {
    [$sent, $log] = smtp_send($SMTP, $FROM, $TO, $subject, $body, $headers);
    if (!$sent && (($_GET['debug'] ?? '') === '1')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "SMTP failed:\n\n" . implode("\n", $log) . "\n";
        exit;
    }
} else {
    // Attempt 1: send as $FROM, which is what shows in the inbox.
    $sent = @mail($TO, $subject, $body, implode("\r\n", $headers), '-f' . $FROM);

    // Attempt 2: some hosts refuse a From address they cannot vouch for. Retry
    // letting the server pick its own sender — it looks less tidy in the inbox
    // but it gets through. Reply-To still points at the customer either way.
    if (!$sent) {
        $fallback = array_values(array_filter(
            $headers,
            static fn($h) => !str_starts_with($h, 'From: ')
        ));
        $sent = @mail($TO, $subject, $body, implode("\r\n", $fallback));
    }
}

if (!$sent) {
    finish(false, 'We could not send that just now — please call 239.561.1314 '
                . 'or email ' . $TO . '.', 500);
}

finish(true, 'Thank you for submitting. Somebody will be in touch with you shortly.');
