<?php
/*  Elite Electrical Contractors — job application handler.
 *
 *  Deliberately credential-free: it uses PHP's mail(), which works on
 *  Hostinger's shared hosting, so there is no SMTP password sitting in a file
 *  that the web server might one day serve as plain text. If this ever moves
 *  to a host that does not run PHP, this file does nothing and the form must
 *  be re-pointed at a hosted form service instead.
 *
 *  No applicant data is written to disk — it goes straight out by email.
 */

declare(strict_types=1);

const TO_ADDRESS = 'service@elite1314.com';
const SITE_NAME  = 'Elite Electrical Contractors';

/* ---------- only accept a POST ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: apply.html', true, 303);
    exit;
}

/* ---------- spam traps ----------
 * 1. honeypot: a field hidden off-screen that only a bot fills in
 * 2. time trap: a human cannot read and complete this form in 3 seconds
 * Both fail silently to the thank-you page, so a bot learns nothing. */
$trapped = false;
if (trim((string)($_POST['company_website'] ?? '')) !== '') {
    $trapped = true;
}
$loadedAt = (int)($_POST['loaded_at'] ?? 0);
if ($loadedAt > 0 && (microtime(true) * 1000 - $loadedAt) < 3000) {
    $trapped = true;
}

/* ---------- the attached resume ----------
 * Optional. A resume that is too big or the wrong type must never cost
 * somebody their application, so a rejected file is noted and the rest is
 * sent anyway — the applicant is told on the thank-you page. */
const RESUME_MAX   = 3145728;                      // 3 MB
const RESUME_TYPES = ['pdf', 'doc', 'docx', 'txt'];

function resume_upload(?string &$why = null): ?array {
    $why = null;
    $f = $_FILES['resume'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || (int)$f['size'] > RESUME_MAX) {
        $why = 'it was over 3 MB';
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $why = 'it could not be read';
        return null;
    }
    $name = preg_replace('/[^\w.\- ]+/u', '_', (string)$f['name']);
    $name = mb_substr(ltrim($name, '.'), 0, 120);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, RESUME_TYPES, true)) {
        $why = 'it was not a PDF or Word file';
        return null;
    }
    $bytes = (string)file_get_contents($f['tmp_name']);
    if ($bytes === '') { $why = 'it arrived empty'; return null; }
    $mime = ['pdf'=>'application/pdf','doc'=>'application/msword',
             'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
             'txt'=>'text/plain'][$ext];
    return [$name, $mime, $bytes];
}

/* ---------- collect ---------- */
function field(string $k, int $max = 2000): string {
    $v = (string)($_POST[$k] ?? '');
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
    return trim(mb_substr($v, 0, $max));
}

$role       = field('role', 120);
$first      = field('first_name', 80);
$last       = field('last_name', 80);
$email      = field('email', 160);
$phone      = field('phone', 40);
$address    = field('address', 160);
$city       = field('city', 80);
$state      = field('state', 40);
$postal     = field('postal', 20);
$years      = field('years', 60);
$employers  = field('employers', 3000);
$experience = field('experience', 6000);
$notes      = field('notes', 3000);

/* ---------- validate (the browser checks too; never trust that) ---------- */
$errors = [];
if ($role === '')                                     { $errors[] = 'position'; }
if ($first === '')                                    { $errors[] = 'first name'; }
if ($last === '')                                     { $errors[] = 'last name'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))       { $errors[] = 'email address'; }
if (preg_match_all('/\d/', $phone) < 7)               { $errors[] = 'phone number'; }
if ($years === '')                                    { $errors[] = 'years of experience'; }
if ($experience === '')                               { $errors[] = 'work experience'; }

/* header injection: a newline in a header field means someone is trying to
 * add their own recipients. Reject rather than sanitise. */
foreach ([$first, $last, $email, $phone, $role] as $headerish) {
    if (preg_match('/[\r\n]/', $headerish)) {
        $errors[] = 'invalid characters';
        break;
    }
}

if ($errors && !$trapped) {
    http_response_code(422);
    render_page(
        'Something was missing',
        'We could not send that application because of: ' . htmlspecialchars(implode(', ', $errors), ENT_QUOTES, 'UTF-8') . '.',
        'Please go back and complete those fields, or email us directly and we will take it from there.',
        false
    );
    exit;
}

/* ---------- build and send ---------- */
if (!$trapped) {
    $name = trim($first . ' ' . $last);
    $addressLine = trim(implode(', ', array_filter([$address, $city, trim($state . ' ' . $postal)])));

    $lines = [
        'NEW JOB APPLICATION',
        str_repeat('=', 52),
        '',
        'POSITION APPLIED FOR:  ' . $role,
        '',
        str_repeat('-', 52),
        'APPLICANT',
        str_repeat('-', 52),
        'Name:      ' . $name,
        'Email:     ' . $email,
        'Phone:     ' . $phone,
        'Address:   ' . ($addressLine !== '' ? $addressLine : '(not given)'),
        'Experience:' . ' ' . $years,
        '',
        str_repeat('-', 52),
        'PREVIOUS EMPLOYERS',
        str_repeat('-', 52),
        $employers !== '' ? $employers : '(not given)',
        '',
        str_repeat('-', 52),
        'WORK EXPERIENCE',
        str_repeat('-', 52),
        $experience,
    ];
    if ($notes !== '') {
        $lines[] = '';
        $lines[] = str_repeat('-', 52);
        $lines[] = 'ANYTHING ELSE';
        $lines[] = str_repeat('-', 52);
        $lines[] = $notes;
    }
    $lines[] = '';
    $lines[] = str_repeat('=', 52);
    $lines[] = 'Submitted ' . date('D j M Y, g:ia T');
    $lines[] = 'Sent by the application form at ' . ($_SERVER['HTTP_HOST'] ?? 'the website');

    $resumeWhy = null;
    $resume    = resume_upload($resumeWhy);
    if ($resume !== null) {
        $lines[] = '';
        $lines[] = 'Resume attached: ' . $resume[0];
    } elseif ($resumeWhy !== null) {
        $lines[] = '';
        $lines[] = 'NOTE: the applicant attached a resume but ' . $resumeWhy
                 . ', so it is not on this email. Reply and ask them to send it.';
    }

    $body    = implode("\n", $lines);
    $subject = 'Application: ' . $role . ' — ' . $name;

    /* From: must be a mailbox on this domain or the host will refuse it.
     * The applicant goes in Reply-To so hitting reply answers them. */
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . SITE_NAME . ' Website <no-reply@elite1314.com>',
        'Reply-To: ' . $name . ' <' . $email . '>',
        'X-Mailer: PHP/' . phpversion(),
    ];

    if ($resume === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
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

    @mail(TO_ADDRESS, $subject, $body, implode("\r\n", $headers));
}

/* A trapped bot gets the same page a person does, so it cannot tell. */
render_page(
    'Thank you for applying',
    'Your application for <strong>' . htmlspecialchars($role !== '' ? $role : 'a role at Elite', ENT_QUOTES, 'UTF-8') . '</strong> has been sent to our team.',
    'Somebody will be in touch with you shortly. If you would like to follow up in the meantime, call 239.561.1314.',
    true
);

/* ---------- output ---------- */
function render_page(string $title, string $lead, string $tail, bool $ok): void {
    $year = date('Y');
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title} · Elite Electrical Contractors</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap">
<link rel="stylesheet" href="css/site.css">
<link rel="stylesheet" href="css/apply.css">
</head>
<body>
<main class="ap-main">
  <section class="ap-body" style="padding-top:clamp(120px,16vh,170px)">
    <div class="wrap">
      <div class="ap-done">
        <div class="ap-tick">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1>{$title}</h1>
        <p>{$lead}</p>
        <p>{$tail}</p>
        <p style="margin-top:24px">
          <a class="btn btn-primary" href="careers.html">Back to careers</a>
        </p>
      </div>
    </div>
  </section>
</main>
<a class="fab-call" href="tel:+12395611314" aria-label="Call Elite Electrical at 239.561.1314"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.46.57 3.6a1 1 0 0 1-.25 1z" fill="currentColor"/></svg><span>239.561.1314</span></a>
</body>
</html>
HTML;
}
