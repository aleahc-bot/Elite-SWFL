/* Elite Electrical — the one endpoint behind the Contact form and the Apply
   form. Vercel runs this natively; nothing to install, no package.json, no
   build step, so it survives being uploaded through GitHub's web UI.

   WHY IT TAKES JSON AND NOT A FILE UPLOAD
   The browser reads the resume, base64-encodes it and posts one JSON object.
   Vercel parses JSON for us, so there is no multipart parser here to get
   subtly wrong. Base64 inflates a file by about a third and Vercel caps a
   function body at 4.5 MB, which is why the front end refuses anything over
   3 MB before it ever gets here.

   CREDENTIALS
   RESEND_API_KEY is read from Vercel's Environment Variables. It is never
   written into this file. That matters: Vercel serves .php as a plain
   download rather than running it, so anything left in send.php was publicly
   readable. A key in here would be too. In Vercel: Settings > Environment
   Variables.

     RESEND_API_KEY   re_xxxxxxxx         (required)
     MAIL_FROM        website@elite1314.com   (must be on a domain verified
                                               with Resend)
     MAIL_TO_CONTACT  jennifer@elite1314.com   (contact form)
     MAIL_TO_JOBS     jennifer@elite1314.com   (applications / résumés)

   If RESEND_API_KEY is missing the endpoint says so plainly rather than
   failing silently, so a half-finished setup is obvious the first time
   somebody tests the form. */

const MAX_BYTES = 3 * 1024 * 1024;           // 3 MB before encoding
const ALLOWED = {
  'application/pdf': 'pdf',
  'application/msword': 'doc',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
  'text/plain': 'txt'
};

function esc (s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* The contact form has always gone to jennifer@ and still does — that is the
   address the form's own footnote promises. Only a job application is routed
   anywhere else, and today that is the same inbox. Both are overridable in
   Vercel without touching this file, for when Chipper sets up the new
   addresses. */
function pickRecipient (body) {
  const jobs    = process.env.MAIL_TO_JOBS    || 'jennifer@elite1314.com';
  const contact = process.env.MAIL_TO_CONTACT || 'jennifer@elite1314.com';
  return (body.kind === 'application' || body.resume) ? jobs : contact;
}

export default async function handler (req, res) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return res.status(405).json({ ok: false, message: 'Method not allowed.' });
  }

  const key = process.env.RESEND_API_KEY;
  if (!key) {
    return res.status(500).json({
      ok: false,
      message: 'The site is not finished connecting to its mail service yet. ' +
               'Please call 239.561.1314 or email service@elite1314.com.'
    });
  }

  let body = req.body;
  if (typeof body === 'string') {
    try { body = JSON.parse(body); }
    catch (e) { return res.status(400).json({ ok: false, message: 'Could not read that form.' }); }
  }
  if (!body || typeof body !== 'object') {
    return res.status(400).json({ ok: false, message: 'Could not read that form.' });
  }

  /* Honeypot: a hidden field only a bot fills in. Answer OK so the bot thinks
     it worked and does not come back for another go. */
  if (body.company_website) return res.status(200).json({ ok: true });

  const name  = String(body.name  || '').trim().slice(0, 120);
  const phone = String(body.phone || '').trim().slice(0, 60);
  const email = String(body.email || '').trim().slice(0, 160);
  if (!name || (!phone && !email)) {
    return res.status(400).json({ ok: false, message: 'Please give a name and a phone number or email.' });
  }

  const attachments = [];
  if (body.resume && body.resume.data) {
    const type = String(body.resume.type || '');
    if (!ALLOWED[type]) {
      return res.status(400).json({ ok: false, message: 'Please attach a PDF or Word document.' });
    }
    /* base64 length -> byte count, allowing for padding */
    const b64 = String(body.resume.data);
    const bytes = Math.floor(b64.length * 3 / 4);
    if (bytes > MAX_BYTES) {
      return res.status(400).json({ ok: false, message: 'That file is over 3 MB — please send a smaller one.' });
    }
    const safeName = String(body.resume.name || 'resume')
      .replace(/[^\w.\- ]+/g, '_').slice(0, 120);
    attachments.push({ filename: safeName, content: b64 });
  }

  const rows = [
    ['Name', name],
    ['Phone', phone],
    ['Email', email],
    ['Role', body.role],
    ['Property type', body.property_type],
    ['Services', Array.isArray(body.services) ? body.services.join(', ') : body.services],
    ['Message', body.message]
  ].filter(function (r) { return r[1]; });

  const html =
    '<div style="font:15px/1.6 Arial,Helvetica,sans-serif;color:#16325E">' +
    '<h2 style="margin:0 0 14px;font-size:18px">' +
      (body.kind === 'application' ? 'Job application' : 'Website enquiry') +
    '</h2><table cellpadding="0" cellspacing="0">' +
    rows.map(function (r) {
      return '<tr><td style="padding:4px 18px 4px 0;color:#6B7A99;white-space:nowrap">' +
             esc(r[0]) + '</td><td style="padding:4px 0"><b>' + esc(r[1]) + '</b></td></tr>';
    }).join('') +
    '</table>' +
    (attachments.length ? '<p style="margin:16px 0 0;color:#6B7A99">Résumé attached.</p>' : '') +
    '<p style="margin:22px 0 0;font-size:12px;color:#9AA7BF">Sent from the Elite Electrical website.</p>' +
    '</div>';

  const payload = {
    from: process.env.MAIL_FROM || 'website@elite1314.com',
    to: [pickRecipient(body)],
    subject: (body.kind === 'application'
      ? 'Application' + (body.role ? ' — ' + body.role : '')
      : 'Website enquiry') + ' — ' + name,
    html: html
  };
  if (email) payload.reply_to = email;
  if (attachments.length) payload.attachments = attachments;

  try {
    const r = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + key, 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (!r.ok) {
      const detail = await r.text().catch(function () { return ''; });
      console.error('resend failed', r.status, detail);
      return res.status(502).json({
        ok: false,
        message: 'We could not send that just now — please call 239.561.1314.'
      });
    }
    return res.status(200).json({ ok: true });
  } catch (e) {
    console.error('resend threw', e && e.message);
    return res.status(502).json({
      ok: false,
      message: 'We could not send that just now — please call 239.561.1314.'
    });
  }
}
