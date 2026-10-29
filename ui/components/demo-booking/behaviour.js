/*
 * demo-booking — booking widget (live source @f2006d8), testimonial card, stats count-up and the split layout's fold.
 *
 * The widget is the live Get a Demo widget (live-pages/get-a-demo/src on the get-a-demo-calcom
 * branch), module for module, so it books exactly as live does: the email reveals the name fields
 * and starts loading open times from Cal.com's public API v2; the button captures the lead in
 * the background and shows the week strip (data-view="week") or the month grid (data-view="month");
 * a click on a time books it through the API and goes on to the thank-you page. With data-guard the
 * lead goes to the AP Demo Guard plugin's REST API (token on the first real input, then /lead with
 * the form it is for; the page falls back to a direct HubSpot submit when the guard does not answer);
 * a submission the browser marks as automated (navigator.webdriver, a headless user agent, no
 * trusted input) skips only that direct HubSpot capture, never the calendar. Step 2 (Joseph
 * 2026-10-16; live config.details, the kit's details prop): when render.php puts the
 * .ap-dw-details block in the calendar card, the company and phone (both required) are checked
 * when a time is clicked, go to HubSpot as a second submission of the same form (or the guard's
 * /update) and travel with the Cal.com booking (notes, metadata, attendee phone); without the
 * block (the pricing page) the time click books at once. With data-work-email="core" a visitor in
 * the Core region (US, CA, GB, NZ, AU, read once from the same-origin /cdn-cgi/trace) who gives a
 * consumer mailbox is still captured, but gets the "a specialist will be in touch" card instead of
 * the calendar until they use a work email ("all" applies it to everyone, "off" or "" turns it
 * off). All configuration comes from the widget root's data attributes (render.php).
 *
 * Kit edits to the live code (marked "Kit:"): the button label is read from the markup (live now
 * does the same), and the Cal.com fallback calendar mounts on this widget's own element instead
 * of a fixed id.
 *
 * Delegated and idempotent: everything keys off data-ap-* attributes ([data-ap-demo-widget],
 * [data-ap-cases], [data-ap-stats], [data-ap-fold]) and marks what it has set up, so several
 * widgets on a page, or a second run, are safe.
 */
(function () {
  'use strict';

  /* ===== lib/validate.js (live) ===== */
  /* Form checks and the light bot check (minimum time since the widget appeared). There is
     no hidden honeypot field: browser autofill and password managers fill it and block real people. */

  const EMAIL = /^[^\s@.]+(?:\.[^\s@.]+)*@(?:[^\s@.]+\.)+[A-Za-z]{2,}$/;

  function isValidEmail(value) {
    return EMAIL.test(String(value == null ? '' : value).trim());
  }

  function cleanName(value) {
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().slice(0, 80).trim();
  }

  /* Company (required) and phone (optional), added 2026-10-16 (Joseph). */
  function cleanCompany(value) {
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().slice(0, 100).trim();
  }

  function cleanPhone(value) {
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().slice(0, 30).trim();
  }

  /** Empty is fine (the phone is optional); otherwise 7 to 15 digits with the usual separators. */
  function isValidPhone(value) {
    const phone = cleanPhone(value);
    if (!phone) return true;
    if (!/^\+?[\d\s().\-]+((ext\.?|x|#)\s*\d{1,6})?$/i.test(phone)) return false;
    const digits = phone.replace(/(ext\.?|x|#)\s*\d{1,6}$/i, '').replace(/\D/g, '').length;
    return digits >= 7 && digits <= 15;
  }

  /* Consumer (free) mailbox providers. The work-email rule (Joseph 2026-10-16, option B) asks
     Core-country visitors on one of these for a work email before they can book; their lead is
     still captured. Country editions of the big four (yahoo.co.uk, hotmail.com.au, outlook.co.nz,
     live.ca) are matched by family. */
  const FREE_MAIL = [
    'gmail.com', 'googlemail.com', 'ymail.com', 'rocketmail.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com',
    'protonmail.com', 'proton.me', 'pm.me', 'gmx.com', 'gmx.net', 'gmx.de', 'yandex.com', 'yandex.ru', 'mail.com', 'email.com',
    'zoho.com', 'zohomail.com', 'fastmail.com', 'hey.com', 'tutanota.com', 'tuta.io', 'tuta.com', 'hushmail.com', 'duck.com',
    'mail.ru', 'qq.com', '163.com', '126.com', 'naver.com', 'daum.net', 'rediffmail.com',
    'comcast.net', 'verizon.net', 'att.net', 'sbcglobal.net', 'bellsouth.net', 'cox.net', 'charter.net', 'optonline.net', 'earthlink.net',
    'btinternet.com', 'sky.com', 'talktalk.net', 'virginmedia.com', 'shaw.ca', 'rogers.com', 'sympatico.ca', 'telus.net',
    'bigpond.com', 'optusnet.com.au', 'iinet.net.au', 'xtra.co.nz',
  ];
  const FREE_MAIL_FAMILIES = /^(yahoo|hotmail|outlook|live)\.[a-z.]+$/;

  function isFreeMail(email) {
    const domain = String(email == null ? '' : email).trim().toLowerCase().split('@')[1] || '';
    if (!domain) return false;
    if (FREE_MAIL_FAMILIES.test(domain)) return true;
    return FREE_MAIL.some((d) => domain === d || domain.endsWith('.' + d));
  }

  function looksHuman({ shownAt, now, minDelay }) {
    return now - shownAt >= minDelay;
  }

  /* Spam guard (2026-10-15, after the Oct 14 burst of fake Bing-ad leads). Only signs a person's
     browser never shows are counted, so a real visitor is not flagged:
     - automation: the browser says it is driven by a script (navigator.webdriver);
     - headless: a browser with no window (HeadlessChrome / PhantomJS in the user agent);
     - no-input: the form was sent with no real key press, tap or click inside it (filled and sent
       by script; autofill, screen readers and voice control all make real events). */
  const HEADLESS = /HeadlessChrome|PhantomJS/i;

  function botSignals({ webdriver, userAgent, interacted }) {
    const reasons = [];
    if (webdriver === true) reasons.push('automation');
    if (HEADLESS.test(String(userAgent == null ? '' : userAgent))) reasons.push('headless');
    if (!interacted) reasons.push('no-input');
    return reasons;
  }

  /* ===== lib/region.js (live) ===== */
  /* Which side of the site's two regions a visitor is on. The site treats US, CA, GB, NZ and AU as
     "Core" (the A-tier region) and everyone else as International. The country comes from
     Cloudflare's /cdn-cgi/trace, a same-origin text reply with a `loc=XX` line. */

  const CORE_COUNTRIES = ['US', 'CA', 'GB', 'NZ', 'AU'];

  function parseTraceLoc(text) {
    const match = /(?:^|\n)loc=([A-Z]{2})(?:\r?\n|$)/.exec(String(text == null ? '' : text));
    return match ? match[1] : '';
  }

  /** 'core', 'intl', or 'unknown' when the country could not be read. */
  function regionOf(loc, core = CORE_COUNTRIES) {
    if (!loc) return 'unknown';
    return core.indexOf(loc) >= 0 ? 'core' : 'intl';
  }

  /* ===== lib/attribution.js (live) ===== */
  /* Attribution fields for the HubSpot submission, read exactly the way GTM tag
     331 fills the old HubSpot form: the current URL parameter wins, otherwise the
     cookie of the same name. First-touch values come only from first_<name>
     cookies. Empty values are left out. */

  const ATTRIBUTION_KEYS = [
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'gclid', 'gbraid', 'wbraid', 'msclkid',
    'hsa_ad', 'hsa_cam', 'hsa_grp', 'hsa_mt', 'hsa_src', 'hsa_tgt',
  ];

  const FIRST_TOUCH = new Set([
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'hsa_ad', 'hsa_cam', 'hsa_grp', 'hsa_mt', 'hsa_src', 'hsa_tgt',
  ]);

  const FORM_FIELD = { gclid: 'gclid__c' };

  function readParam(search, name) {
    return (new URLSearchParams(search || '').get(name) || '').trim();
  }

  function readCookie(cookieString, name) {
    for (const part of String(cookieString || '').split(';')) {
      const eq = part.indexOf('=');
      if (eq < 0 || part.slice(0, eq).trim() !== name) continue;
      const raw = part.slice(eq + 1);
      try { return decodeURIComponent(raw).trim(); } catch (e) { return raw.trim(); }
    }
    return '';
  }

  function collectAttribution(search, cookieString) {
    const fields = [];
    for (const key of ATTRIBUTION_KEYS) {
      const value = readParam(search, key) || readCookie(cookieString, key);
      if (value) fields.push({ name: FORM_FIELD[key] || key, value });
      if (FIRST_TOUCH.has(key)) {
        const first = readCookie(cookieString, 'first_' + key);
        if (first) fields.push({ name: 'first_' + key, value: first });
      }
    }
    return fields;
  }

  /* ===== lib/hubspot.js (live) ===== */
  /* The background submission to the HubSpot demo form named in site.config.json (live:
     18622601, the form the old /get-a-demo/ page embedded, so Tier A scoring, lists and
     workflows keep working; the tests used its clone 3c08466f, which has the same fields). */

  const HS_SUBMIT = 'https://api.hsforms.com/submissions/v3/integration/submit/';
  const DEMO_FORM_VERSION = 'Demo Form - Asset Panda Pro';

  function buildHubspotSubmission({ portalId, formId, email, firstName, lastName, company, phone, attribution, hutk, pageUri, pageName }) {
    const fields = [
      { name: 'email', value: email },
      { name: 'firstname', value: firstName },
      { name: 'lastname', value: lastName },
    ];
    /* Both are (hidden) fields of the live demo form: company, and the phone property the old form
       wrote, phone_entered_by_prospect. Phone is optional, so it is left out when blank. */
    if (company) fields.push({ name: 'company', value: company });
    if (phone) fields.push({ name: 'phone_entered_by_prospect', value: phone });
    fields.push({ name: 'demo_form_version', value: DEMO_FORM_VERSION });
    fields.push(...(attribution || []));
    const context = { pageUri, pageName };
    if (hutk) context.hutk = hutk;
    return {
      url: HS_SUBMIT + encodeURIComponent(portalId) + '/' + encodeURIComponent(formId),
      body: { fields, context },
    };
  }

  /* ===== lib/calcom.js (live) ===== */
  /* Cal.com API v2 calls for either a team event (teamSlug) or a personal event
     (username). Both endpoints accept browser calls without a login
     (Access-Control-Allow-Origin: *), checked 2026-10-08. Booking through the API
     runs Cal.com's normal pipeline: host assignment, Zoom link, emails, HubSpot +
     Salesforce apps. */

  const CAL_API = 'https://api.cal.com/v2';
  const SLOTS_API_VERSION = '2024-09-04';
  const BOOKINGS_API_VERSION = '2024-08-13';

  /* The event's owner: a personal event is addressed by username, a team event by teamSlug. */
  function eventOwner({ teamSlug, username }) {
    return username ? { username } : { teamSlug };
  }

  function buildSlotsRequest({ teamSlug, username, eventSlug, startKey, endKey }) {
    const query = new URLSearchParams(Object.assign(eventOwner({ teamSlug, username }), {
      eventTypeSlug: eventSlug, start: startKey, end: endKey, timeZone: 'UTC',
    }));
    return { url: CAL_API + '/slots?' + query.toString(), headers: { 'cal-api-version': SLOTS_API_VERSION } };
  }

  function parseSlotsResponse(json) {
    const data = json && json.status === 'success' && json.data;
    if (!data || typeof data !== 'object') throw new Error('Unexpected Cal.com slots response');
    const isos = new Set();
    for (const day of Object.keys(data)) {
      for (const slot of data[day] || []) {
        if (slot && slot.start) isos.add(new Date(slot.start).toISOString());
      }
    }
    return Array.from(isos).sort();
  }

  /* The attendee phone for Cal.com (its booking form's attendeePhoneNumber field): international
     format with the separators removed, a North American number typed without its country code
     (10 digits, or 11 with a leading 1) given +1, anything else '' (Cal.com validates the attendee
     phone with libphonenumber and refuses the whole booking on a bad one). */
  function e164(phone) {
    const digits = String(phone || '').replace(/[ ().-]/g, '');
    if (/^[+][1-9][0-9]{6,14}$/.test(digits)) return digits;
    const nanp = digits.replace(/^1(?=[0-9]{10}$)/, '');
    if (/^[2-9][0-9]{2}[2-9][0-9]{6}$/.test(nanp)) return '+1' + nanp;
    return '';
  }

  /* Step 2 (Joseph 2026-10-16): the company and phone travel with the booking, so the calendar
     event and the confirmation emails carry them: as "Additional notes" (the notes booking field),
     in the booking's metadata, and as the attendee's phone when it is in international format.
     The page books again without them if Cal.com refuses the fuller request. */
  function buildBookingRequest({ teamSlug, username, eventSlug, startIso, name, email, timeZone, source, company = '', phone = '' }) {
    const attendee = { name, email, timeZone, language: 'en' };
    const metadata = { source };
    const body = Object.assign({ start: startIso }, eventOwner({ teamSlug, username }), { eventTypeSlug: eventSlug, attendee, metadata });
    const notes = [];
    if (company) {
      metadata.company = String(company).slice(0, 500);
      notes.push('Company: ' + company);
    }
    if (phone) {
      metadata.phone = String(phone).slice(0, 500);
      notes.push('Phone: ' + phone);
      const intl = e164(phone);
      if (intl) attendee.phoneNumber = intl;
    }
    if (notes.length) body.bookingFieldsResponses = { notes: notes.join('\n') };
    return {
      url: CAL_API + '/bookings',
      headers: { 'Content-Type': 'application/json', 'cal-api-version': BOOKINGS_API_VERSION },
      body,
    };
  }

  const TAKEN = /no_available_users|hosts_unavailable|already has booking|is not available|no longer available|booking_time_out_of_bounds/i;

  function classifyBookingResponse(status, json) {
    if (status >= 200 && status < 300 && json && json.status === 'success') return 'booked';
    if (status === 0) return 'unknown';
    if (status >= 500) return 'unavailable';
    const error = json && json.error;
    const message = error ? String(error.message || error.code || '') : '';
    if ((status === 400 || status === 409) && TAKEN.test(message)) return 'slot_taken';
    return 'rejected';
  }

  /* ===== lib/schedule.js (live) ===== */
  /* Calendar maths for the weekly picker. Day keys are 'YYYY-MM-DD' strings in
     the visitor's chosen time zone; slot times stay as UTC ISO strings, so a
     time-zone change only regroups them. */

  const keyFormatters = new Map();

  function keyFormatter(timeZone) {
    if (!keyFormatters.has(timeZone)) {
      keyFormatters.set(timeZone, new Intl.DateTimeFormat('en-US', {
        timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
      }));
    }
    return keyFormatters.get(timeZone);
  }

  function dayKey(date, timeZone) {
    const parts = {};
    for (const part of keyFormatter(timeZone).formatToParts(date)) parts[part.type] = part.value;
    return parts.year + '-' + parts.month + '-' + parts.day;
  }

  function keyToUtcDate(key) {
    const [y, m, d] = key.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d));
  }

  function addDays(key, n) {
    const date = keyToUtcDate(key);
    date.setUTCDate(date.getUTCDate() + n);
    return date.toISOString().slice(0, 10);
  }

  function weekStart(key) {
    return addDays(key, -keyToUtcDate(key).getUTCDay());
  }

  /* How many Sunday-start weeks, counted from the week that holds today, it takes
     to reach the day today + days. The picker's last page. */
  function weeksToCover(todayKey, days) {
    const first = keyToUtcDate(weekStart(todayKey));
    const last = keyToUtcDate(weekStart(addDays(todayKey, days)));
    return Math.round((last - first) / (7 * 86400000)) + 1;
  }

  function groupSlotsByDay(isoList, timeZone) {
    const byDay = {};
    for (const iso of isoList) {
      const key = dayKey(new Date(iso), timeZone);
      (byDay[key] = byDay[key] || []).push(iso);
    }
    return byDay;
  }

  function buildWeek(startKey, todayKey, byDay) {
    const days = [];
    for (let i = 0; i < 7; i++) {
      const key = addDays(startKey, i);
      const slots = key >= todayKey ? byDay[key] || [] : [];
      days.push({ key, isPast: key < todayKey, slots, available: slots.length > 0 });
    }
    return days;
  }

  function findFirstWeekWithSlots(todayKey, byDay, maxWeeks) {
    const first = weekStart(todayKey);
    for (let w = 0; w < maxWeeks; w++) {
      if (buildWeek(addDays(first, 7 * w), todayKey, byDay).some((d) => d.available)) return w;
    }
    return -1;
  }

  /* Month view (Page B): month keys are 'YYYY-MM'; a month page is that month's days on a
     Sunday-start grid, with null cells before the 1st so the 1st sits under its weekday. */
  function monthOf(key) {
    return key.slice(0, 7);
  }

  function addMonths(monthKey, n) {
    const [y, m] = monthKey.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1 + n, 1)).toISOString().slice(0, 7);
  }

  /* How many months, counted from the month that holds today, it takes to reach today + days. */
  function monthsToCover(todayKey, days) {
    const [y1, m1] = monthOf(todayKey).split('-').map(Number);
    const [y2, m2] = monthOf(addDays(todayKey, days)).split('-').map(Number);
    return (y2 - y1) * 12 + (m2 - m1) + 1;
  }

  function buildMonth(monthKey, todayKey, byDay) {
    const firstKey = monthKey + '-01';
    const cells = [];
    for (let i = keyToUtcDate(firstKey).getUTCDay(); i > 0; i--) cells.push(null);
    for (let key = firstKey; monthOf(key) === monthKey; key = addDays(key, 1)) {
      const slots = key >= todayKey ? byDay[key] || [] : [];
      cells.push({ key, isPast: key < todayKey, isToday: key === todayKey, slots, available: slots.length > 0 });
    }
    return cells;
  }

  function findFirstMonthWithSlots(todayKey, byDay, maxMonths) {
    const first = monthOf(todayKey);
    for (let m = 0; m < maxMonths; m++) {
      if (buildMonth(addMonths(first, m), todayKey, byDay).some((d) => d && d.available)) return m;
    }
    return -1;
  }

  function monthLabel(monthKey) {
    return new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', month: 'long', year: 'numeric' }).format(keyToUtcDate(monthKey + '-01'));
  }

  function dayLabel(key) {
    const date = keyToUtcDate(key);
    const part = (options) => new Intl.DateTimeFormat('en-US', Object.assign({ timeZone: 'UTC' }, options)).format(date);
    return { dow: part({ weekday: 'short' }), day: String(date.getUTCDate()), mon: part({ month: 'short' }) };
  }

  function formatTime(iso, timeZone) {
    return new Intl.DateTimeFormat('en-US', { timeZone, hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(iso));
  }

  function formatLongDate(iso, timeZone) {
    return new Intl.DateTimeFormat('en-US', { timeZone, weekday: 'long', month: 'long', day: 'numeric' }).format(new Date(iso));
  }

  const nameFormatters = new Map();

  function nameFormatter(timeZone) {
    if (!nameFormatters.has(timeZone)) {
      nameFormatters.set(timeZone, new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'long' }));
    }
    return nameFormatters.get(timeZone);
  }

  function timeZoneName(timeZone, date) {
    try {
      const part = nameFormatter(timeZone).formatToParts(date).find((p) => p.type === 'timeZoneName');
      return part ? part.value : timeZone;
    } catch (e) {
      return timeZone;
    }
  }

  function zoneLabel(zone, date) {
    return zone.split('/').pop().replace(/_/g, ' ') + ' (' + timeZoneName(zone, date) + ')';
  }

  const FALLBACK_ZONES = [
    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles',
    'America/Anchorage', 'Pacific/Honolulu', 'America/Toronto', 'Europe/London', 'Europe/Berlin',
    'Asia/Manila', 'Australia/Sydney',
  ];

  function listTimeZones(detected) {
    let zones = [];
    try { zones = Intl.supportedValuesOf('timeZone'); } catch (e) { zones = []; }
    if (!zones.length) zones = FALLBACK_ZONES.slice();
    if (detected && zones.indexOf(detected) === -1) zones = [detected].concat(zones);
    if (zones.indexOf('UTC') === -1) zones = zones.concat('UTC');
    return zones;
  }

  /* ===== lib/tracking.js (live) ===== */
  /* Hand-offs to GTM (GTM-WVPZ4X), matching what the old flow produced:
     - tag 167 pushed {event:'hubspot-form-success','hs-form-guid'} on HubSpot submit
       (tag 327, OpenAI lead_created, listens for it on get-a-demo paths);
     - tag 342 stored sessionStorage.ap_ec_ud for Enhanced Conversions;
     - tag 254 turned Calendly messages into {event:'calendly', calendly_event},
       which GA4 tag 257 sends as calendar_event_scheduled (Ads/Meta/OpenAI downstream).
     demo_form_submitted is the new lead signal; GTM maps it in Task 19. */

  const LEAD_EVENT = 'demo_form_submitted';

  /* A test visit: a WordPress preview link or a logged-in visitor, unless ?ap_live=1. */
  function isTestMode(search, bodyClassName) {
    const params = new URLSearchParams(search || '');
    if (params.get('ap_live') === '1') return false;
    return params.get('preview') === 'true' || /(^|\s)logged-in(\s|$)/.test(bodyClassName || '');
  }

  /* What the widget does with the real services:
       'live'    sends to HubSpot and Cal.com and pushes the conversion events;
       'notrack' sends for real but pushes no dataLayer events and stores/clears no ap_ec_ud,
                 so no conversion is recorded (a calendly event would count in Google Ads);
       'dry'     sends nothing, logs the payloads, and still runs the success path.
     Public visitors are live. A test visit (isTestMode) is 'notrack', or 'dry' with ?ap_dry=1.
     ?ap_notrack=1 makes any visitor 'notrack'. ?ap_live=1 ends the test visit, so ap_dry is then
     ignored. Conversions count only on trackPath (the launch address): on any other address, such
     as the public test pages, every visitor is 'notrack' unless ?ap_live=1 (Joseph 2026-10-13).
     The caller derives: send = mode !== 'dry', track = mode === 'live'. */
  function trafficMode(search, bodyClassName, pathname, trackPath) {
    const params = new URLSearchParams(search || '');
    const testVisit = isTestMode(search, bodyClassName);
    if (testVisit && params.get('ap_dry') === '1') return 'dry';
    if (testVisit || params.get('ap_notrack') === '1') return 'notrack';
    if (params.get('ap_live') !== '1' && !onTrackPath(pathname, trackPath)) return 'notrack';
    return 'live';
  }

  /* True on the address that counts conversions (trailing slash optional), or when either is unknown. */
  function onTrackPath(pathname, trackPath) {
    if (!trackPath || pathname == null) return true;
    const withSlash = (p) => (p.endsWith('/') ? p : p + '/');
    return withSlash(pathname) === withSlash(trackPath);
  }

  const RIBBONS = {
    notrack: 'TEST MODE: submissions and bookings are real, but no conversions are counted.',
    dry: 'TEST MODE (dry run): nothing is sent to HubSpot or Cal.com.',
  };

  function modeRibbon(mode) {
    return RIBBONS[mode] || '';
  }

  function hasMarketingConsent(win) {
    try {
      if (win && win.Cookiebot && win.Cookiebot.consent) return win.Cookiebot.consent.marketing === true;
    } catch (e) { /* an unreadable CMP counts as no CMP, as in tag 342 */ }
    return true;
  }

  function storeEcUserData(storage, win, person, now) {
    if (!storage || !hasMarketingConsent(win)) return false;
    try {
      storage.setItem('ap_ec_ud', JSON.stringify({
        email: person.email, first_name: person.firstName, last_name: person.lastName, ts: now,
      }));
      return true;
    } catch (e) {
      return false;
    }
  }

  function clearEcUserData(storage) {
    if (!storage) return;
    try { storage.removeItem('ap_ec_ud'); } catch (e) { /* storage unavailable */ }
  }

  function pushLeadEvents(dataLayer, formGuid, hubspotOk) {
    if (hubspotOk) dataLayer.push({ event: 'hubspot-form-success', 'hs-form-guid': formGuid });
    dataLayer.push({ event: LEAD_EVENT });
  }

  function pushBookingEvent(dataLayer, done) {
    let finished = false;
    const finish = () => {
      if (finished) return;
      finished = true;
      if (done) done();
    };
    const timer = setTimeout(finish, 1600);
    if (timer && typeof timer.unref === 'function') timer.unref();
    try {
      dataLayer.push({ event: 'calendly', calendly_event: 'event_scheduled', eventCallback: finish, eventTimeout: 1500 });
    } catch (e) {
      finish();
    }
  }

  /* ===== widget/fallback.js (live) ===== */
  /* Cal.com's standard inline calendar, used only when our own picker can't book
     (slots API down, or a booking rejected for an unknown reason). Same loader
     snippet the live thank-you pages use; name and email are pre-filled. */

  const NAMESPACE = 'ap-demo-fallback';

  function ensureLoader() {
    if (window.Cal) return;
    (function (C, A, L) {
      const p = function (a, ar) { a.q.push(ar); };
      const d = C.document;
      C.Cal = C.Cal || function () {
        const cal = C.Cal;
        const ar = arguments;
        if (!cal.loaded) {
          cal.ns = {};
          cal.q = cal.q || [];
          d.head.appendChild(d.createElement('script')).src = A;
          cal.loaded = true;
        }
        if (ar[0] === L) {
          const api = function () { p(api, arguments); };
          const namespace = ar[1];
          api.q = api.q || [];
          if (typeof namespace === 'string') {
            cal.ns[namespace] = cal.ns[namespace] || api;
            p(cal.ns[namespace], ar);
            p(cal, ['initNamespace', namespace]);
          } else {
            p(cal, ar);
          }
          return;
        }
        p(cal, ar);
      };
    })(window, 'https://app.cal.com/embed/embed.js', 'init');
  }

  function mountCalFallback({ selector, calLink, name, email, onBooked }) {
    ensureLoader();
    window.Cal('init', NAMESPACE, { origin: 'https://app.cal.com' });
    const cal = window.Cal.ns[NAMESPACE];
    cal('inline', { elementOrSelector: selector, calLink, config: { layout: 'month_view', theme: 'light', name, email } });
    cal('ui', {
      theme: 'light',
      layout: 'month_view',
      hideEventTypeDetails: false,
      cssVarsPerTheme: { light: { 'cal-brand': '#5044e1', 'cal-brand-emphasis': '#3e33c9', 'cal-brand-text': '#ffffff' } },
    });
    cal('on', { action: 'bookingSuccessfulV2', callback: onBooked });
  }

  /* ===== widget/controller.js (live) ===== */
  /* Screen logic for the Book a Demo widget (markup: src/blocks/hero.html).
     Pure helpers live in ../lib and are unit-tested; this file wires them to the DOM. */








  const DEFAULT_MAX_DAYS = 60;
  const DAY_MS = 86400000;
  const DEFAULT_MIN_DELAY_MS = 1500;
  /* HubSpot runs in the background (the calendar never waits for it), so it gets time to finish. */
  const HS_TIMEOUT_MS = 15000;
  const SLOTS_TIMEOUT_MS = 10000;
  /* Cal.com refuses a time fast (1.2 s measured 2026-10-13) but confirms only after its Zoom,
     calendar, HubSpot and Salesforce steps (12.3 s measured; Jon's first test passed 15 s). With no
     refusal after FAST_CONFIRM_MS the booking is under way, and Cal.com finishes it after the visitor
     leaves (proven the same day), so the visitor goes straight on to the thank-you page.
     BOOK_TIMEOUT_MS only bounds the request when nothing navigates away. */
  const BOOK_TIMEOUT_MS = 45000;
  const FAST_CONFIRM_MS = 3000;
  /* Open times fetched while the visitor types stay good for this long. */
  const SLOTS_FRESH_MS = 180000;
  /* The demo guard's page token is renewed when older than this (the guard accepts six hours). */
  const TOKEN_FRESH_MS = 1800000;
  const TOKEN_TIMEOUT_MS = 5000;
  const DEFAULT_SOURCE = 'assetpanda.com/get-a-demo';
  /* Cloudflare's same-origin country lookup, read once at start-up for the work-email rule. */
  const TRACE_URL = '/cdn-cgi/trace';
  const TRACE_TIMEOUT_MS = 4000;

  function fetchWithTimeout(url, options, ms) {
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    const timer = setTimeout(() => { if (controller) controller.abort(); }, ms);
    const opts = Object.assign({}, options, controller ? { signal: controller.signal } : {});
    return fetch(url, opts).finally(() => clearTimeout(timer));
  }

  async function readJson(res) {
    try { return await res.json(); } catch (e) { return null; }
  }

  function detectTimeZone() {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Chicago'; } catch (e) { return 'America/Chicago'; }
  }

  function sessionStore() {
    try { return window.sessionStorage; } catch (e) { return null; }
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function initDemoWidget(root) {
    const q = (selector) => root.querySelector(selector);
    const cfg = root.dataset;
    /* 'embed' skips our weekly picker: after the HubSpot capture it shows Cal.com's own inline calendar. */
    const embedMode = cfg.mode === 'embed';
    /* 'month' (Page B) draws the open days on a month grid, Cal.com's layout, in place of the week strip. */
    const monthView = cfg.view === 'month';
    /* testMode: a preview or logged-in visit (lands on the thank-you draft). mode: what is sent and counted. */
    const testMode = isTestMode(window.location.search, document.body.className);
    const mode = trafficMode(window.location.search, document.body.className, window.location.pathname, cfg.trackPath);
    const send = mode !== 'dry';
    const track = mode === 'live';
    const minDelay = cfg.minDelayMs ? Number(cfg.minDelayMs) : DEFAULT_MIN_DELAY_MS;
    const bookTimeout = cfg.bookTimeoutMs ? Number(cfg.bookTimeoutMs) : BOOK_TIMEOUT_MS;
    const fastConfirm = cfg.fastConfirmMs ? Number(cfg.fastConfirmMs) : FAST_CONFIRM_MS;
    /* Nobody books further out than this many days: later times are no-shows, not real leads. */
    const configuredDays = Math.floor(Number(cfg.maxDays));
    const maxDays = configuredDays >= 1 ? configuredDays : DEFAULT_MAX_DAYS;
    const dataLayer = (window.dataLayer = window.dataLayer || []);
    /* Work-email rule (Joseph 2026-10-16, option B). data-work-email="core": a visitor in the site's
       Core region (US, CA, GB, NZ, AU; the A-tier countries) on a consumer mailbox is still captured
       as a lead, but sees "a specialist will be in touch" instead of the calendar until they book
       with a work email. International visitors, and anyone whose country could not be read, get
       the calendar as before. "all" applies the rule to everyone; "off" (or anything else) switches
       it off, which is the live setting since 2026-10-16: personal emails get the calendar in both
       regions (Joseph, 2026-10-16). */
    const workEmailRule = cfg.workEmail === 'core' || cfg.workEmail === 'all' ? cfg.workEmail : '';
    /* The AP Demo Guard plugin's REST base (data-guard). When set, the lead goes to the guard, which
       checks it, keeps a copy and sends real ones to HubSpot itself. Never used in a dry run. */
    const guard = send ? String(cfg.guard || '').replace(/\/+$/, '') : '';
    /* The booking's source tag comes from the page (the pricing page differs). */
    const SOURCE = String(cfg.source || DEFAULT_SOURCE);

    const ui = {
      ribbon: q('.ap-dw-ribbon'),
      form: q('.ap-dw-form'),
      email: q('input[name="email"]'),
      first: q('input[name="firstname"]'),
      last: q('input[name="lastname"]'),
      company: q('input[name="company"]'),
      phone: q('input[name="phone"]'),
      names: q('.ap-dw-names'),
      emailErr: q('[data-err="email"]'),
      nameErr: q('[data-err="name"]'),
      details: q('.ap-dw-details'),
      detailsErr: q('[data-err="details"]'),
      submit: q('.ap-dw-form .ap-dw-btn'),
      cal: q('.ap-dw-cal'),
      who: q('[data-who]'),
      change: q('[data-action="change"]'),
      alert: q('.ap-dw-alert'),
      heading: q('.ap-dw-cal .ap-dw-h'),
      week: q('.ap-dw-week'),
      month: q('[data-month]'),
      days: q('.ap-dw-days'),
      prev: q('[data-action="prev"]'),
      next: q('[data-action="next"]'),
      tz: q('[data-tz]'),
      now: q('[data-now]'),
      meta: q('.ap-dw-meta'),
      timesHeading: q('.ap-dw-h2'),
      slots: q('.ap-dw-slots'),
      empty: q('.ap-dw-empty'),
      hint: q('.ap-dw-hint'),
      fallback: q('.ap-dw-fallback'),
      fallbackNote: q('[data-fallback-note]'),
      none: q('.ap-dw-none'),
      done: q('.ap-dw-done'),
      reach: q('.ap-dw-reach'),
      reachName: q('[data-reach-name]'),
      workEmail: q('[data-action="work-email"]'),
      status: q('.ap-dw-status'),
    };
    const cards = [ui.form, ui.cal, ui.fallback, ui.none, ui.done, ui.reach];
    /* Kit: the button keeps the label the editor gave it (live hard-codes 'Schedule Call'). */
    const submitLabel = ui.submit.textContent;

    const state = {
      email: '', first: '', last: '', company: '', phone: '', tz: detectTimeZone(),
      isos: [], byDay: {}, todayKey: '', pages: 1, pageIndex: 0, day: null, slot: null,
      slotsPromise: null, slotsAt: 0,
      capturedFor: '', shownAt: Date.now(), busy: false, leadTracked: false, finished: false,
      interacted: false, flags: [],
      token: '', tokenAt: 0, tokenPromise: null, tokenTried: false, entry: null,
      region: 'unknown', needsWorkEmail: false, detailsSentFor: '',
    };

    /* Only an explicit dry run (?ap_dry=1) shows a notice; a 'notrack' visit (logged in, preview,
       or a test address) looks exactly like the live page (Joseph 2026-10-14). */
    if (mode === 'dry') {
      ui.ribbon.textContent = modeRibbon(mode);
      ui.ribbon.hidden = false;
    }

    if (monthView) {
      ui.hint.hidden = false;
      ui.prev.setAttribute('aria-label', 'Previous month');
      ui.next.setAttribute('aria-label', 'Next month');
      ui.days.setAttribute('aria-label', 'Days this month');
      ui.month.hidden = false;
      /* The duration and time-zone line sits under the month, leaving the right column to the times. */
      ui.week.appendChild(ui.meta);
    }

    const say = (message) => { ui.status.textContent = message; };

    function probeRegion() {
      if (!workEmailRule) return;
      fetchWithTimeout(TRACE_URL, { cache: 'no-store' }, TRACE_TIMEOUT_MS)
        .then((res) => (res.ok ? res.text() : ''))
        .then((text) => { state.region = regionOf(parseTraceLoc(text)); })
        .catch(() => { state.region = 'unknown'; });
    }

    function workEmailNeeded(email) {
      if (!workEmailRule || !isFreeMail(email)) return false;
      return workEmailRule === 'all' || state.region === 'core';
    }

    /* The lead is captured; the calendar waits for a work email. */
    function showReach() {
      ui.reachName.textContent = state.first;
      show(ui.reach);
      say('Thanks. A specialist will be in touch shortly.');
      ui.reach.setAttribute('tabindex', '-1');
      ui.reach.focus();
    }
    const warn = (message) => { ui.alert.textContent = message; };
    const clearWarn = () => { ui.alert.textContent = ''; };
    const show = (card) => cards.forEach((c) => { c.hidden = c !== card; });

    function setWaiting(on) {
      ui.submit.disabled = on;
      ui.submit.classList.toggle('is-wait', on);
      ui.submit.textContent = on ? 'Please Wait...' : submitLabel;
    }

    function syncFilled() {
      root.querySelectorAll('.ap-dw-field').forEach((field) => {
        field.classList.toggle('has-value', field.querySelector('input').value !== '');
      });
      /* A row of two fields lifts both labels together, so the pair keeps one height. */
      root.querySelectorAll('.ap-dw-pair').forEach((pair) => {
        pair.classList.toggle('has-value', [...pair.querySelectorAll('input')].some((input) => input.value !== ''));
      });
      if (isValidEmail(ui.email.value)) {
        ui.names.hidden = false;
        /* Start on the open times while the visitor types their name, so Schedule Call shows them at once. */
        if (!embedMode && !workEmailNeeded(ui.email.value)) slotsReady().catch(() => {});
      }
    }

    function focusPressed(container) {
      const pressed = container.querySelector('[aria-pressed="true"]');
      if (pressed) pressed.focus();
    }

    /* The guard's page token: asked for on the first real touch of the form, so the guard can tell
       how long the form took to fill. '' when the guard does not answer. */
    function freshToken() {
      if (!guard) return Promise.resolve('');
      if (state.token && Date.now() - state.tokenAt < TOKEN_FRESH_MS) return Promise.resolve(state.token);
      if (!state.tokenPromise) {
        state.tokenTried = true;
        state.tokenPromise = fetchWithTimeout(guard + '/token', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }, TOKEN_TIMEOUT_MS)
          .then((res) => (res.ok ? readJson(res) : null))
          .then((json) => {
            const token = json && typeof json.t === 'string' ? json.t : '';
            if (token) { state.token = token; state.tokenAt = Date.now(); }
            return token;
          })
          .catch(() => '')
          .finally(() => { state.tokenPromise = null; });
      }
      return state.tokenPromise;
    }

    /* The lead through the guard. null when the guard did not answer, so the page sends to HubSpot
       itself as before. */
    async function sendToGuard(body) {
      const token = await freshToken();
      try {
        const res = await fetchWithTimeout(guard + '/lead', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true,
          body: JSON.stringify({ token, form: cfg.hsForm, flags: state.flags, fields: body.fields, context: body.context }),
        }, HS_TIMEOUT_MS);
        const json = res.ok ? await readJson(res) : null;
        if (!json || json.ok !== true) return null;
        state.entry = json.id ? { id: json.id, key: json.key } : null;
        return { hs: json.hs === true, lead: json.lead === true };
      } catch (e) {
        return null;
      }
    }

    /* Tells the guard the entry booked (shown on its Demo Entries screen). */
    function reportBooking(outcome, iso) {
      if (!guard || !state.entry) return;
      const body = JSON.stringify({ id: state.entry.id, key: state.entry.key, outcome, start: iso || '' });
      try {
        if (navigator.sendBeacon && navigator.sendBeacon(guard + '/booked', new Blob([body], { type: 'application/json' }))) return;
      } catch (e) { /* fall back to fetch */ }
      fetch(guard + '/booked', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {});
    }

    /* HubSpot: the existing demo form, submitted in the background (through the guard when set). */
    async function captureLead() {
      state.capturedFor = state.email;
      const request = buildHubspotSubmission({
        portalId: cfg.hsPortal,
        formId: cfg.hsForm,
        email: state.email,
        firstName: state.first,
        lastName: state.last,
        company: state.company,
        phone: state.phone,
        attribution: collectAttribution(window.location.search, document.cookie),
        hutk: readCookie(document.cookie, 'hubspotutk'),
        pageUri: window.location.href,
        pageName: document.title,
      });
      if (!send) { console.info('[ap-demo] DRY RUN: HubSpot submission not sent', request); return; }
      let ok = false;
      let counted = true;
      const guarded = guard ? await sendToGuard(request.body) : null;
      if (guarded) {
        ok = guarded.hs;
        counted = guarded.lead;
      } else if (state.flags.length) {
        skipLead();
        return;
      } else {
        try {
          const res = await fetchWithTimeout(request.url, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(request.body), keepalive: true,
          }, HS_TIMEOUT_MS);
          ok = res.ok;
        } catch (e) {
          ok = false;
        }
      }
      /* The guard blocked or held it: no conversion is counted. */
      if (!track || !counted) return;
      storeEcUserData(sessionStore(), window, { email: state.email, firstName: state.first, lastName: state.last }, Date.now());
      if (!state.leadTracked) {
        state.leadTracked = true;
        pushLeadEvents(dataLayer, cfg.hsForm, ok);
      }
    }

    /* With no guard to judge it, a flagged submission (botSignals) skips only the HubSpot capture
       and the lead events. The calendar still opens, so a person flagged by mistake can still book,
       and Cal.com's own HubSpot app records every booking. The Oct 14 spam bots never booked. */
    function skipLead() {
      console.info('[ap-demo] flagged as automated (' + state.flags.join(', ') + '): HubSpot capture skipped');
      if (track) dataLayer.push({ event: 'ap_demo_flagged', ap_demo_flags: state.flags.join(',') });
    }

    /* Step 2 (Joseph 2026-10-16): company and phone, both required, sit above the calendar and are
       checked when a time is clicked. The time is not booked until they pass. */
    function detailsReady() {
      /* No details block (the pricing page): the time click books at once. */
      if (!ui.details) return true;
      ui.detailsErr.hidden = true;
      const company = cleanCompany(ui.company.value);
      if (!company) {
        ui.detailsErr.textContent = 'Please enter your company name.';
        ui.detailsErr.hidden = false;
        ui.company.focus();
        return false;
      }
      const phone = cleanPhone(ui.phone.value);
      if (!phone) {
        ui.detailsErr.textContent = 'Please enter your phone number.';
        ui.detailsErr.hidden = false;
        ui.phone.focus();
        return false;
      }
      if (!isValidPhone(phone)) {
        ui.detailsErr.textContent = 'Please enter a valid phone number.';
        ui.detailsErr.hidden = false;
        ui.phone.focus();
        return false;
      }
      state.company = company;
      state.phone = phone;
      return true;
    }

    /* The company and phone reach HubSpot as a second submission of the same form (the contact's
       Company and Phone fill in; HubSpot's submission list shows one row per step). Through the
       guard it is an update to the stored entry, which the guard sends on itself; without one the
       page posts it. Sent in the background, once per set of values, before the booking request. */
    async function sendDetails() {
      if (!ui.details) return;
      const stamp = state.company + '\n' + state.phone;
      if (state.detailsSentFor === stamp) return;
      state.detailsSentFor = stamp;
      const request = buildHubspotSubmission({
        portalId: cfg.hsPortal,
        formId: cfg.hsForm,
        email: state.email,
        firstName: state.first,
        lastName: state.last,
        company: state.company,
        phone: state.phone,
        attribution: collectAttribution(window.location.search, document.cookie),
        hutk: readCookie(document.cookie, 'hubspotutk'),
        pageUri: window.location.href,
        pageName: document.title,
      });
      if (!send) { console.info('[ap-demo] DRY RUN: HubSpot details (step 2) not sent', request); return; }
      const post = (url, body) => fetchWithTimeout(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), keepalive: true,
      }, HS_TIMEOUT_MS).catch(() => {});
      if (guard && state.entry) {
        await post(guard + '/update', { id: state.entry.id, key: state.entry.key, fields: request.body.fields });
        return;
      }
      /* No guard to judge a flagged visitor: the step-1 capture was skipped as well. */
      if (state.flags.length) return;
      await post(request.url, request.body);
    }

    /* Cal.com: every open time from now to maxDays out, in one request. The window is
       asked for a little wide (yesterday UTC, maxDays + 2 days) so no time zone loses an
       edge day; the filter below then cuts it to exactly now .. now + maxDays. */
    async function loadSlots() {
      const startKey = addDays(dayKey(new Date(), 'UTC'), -1);
      const request = buildSlotsRequest({ teamSlug: cfg.team || undefined, username: cfg.user || undefined, eventSlug: cfg.event, startKey, endKey: addDays(startKey, maxDays + 2) });
      const res = await fetchWithTimeout(request.url, { headers: request.headers }, SLOTS_TIMEOUT_MS);
      if (!res.ok) throw new Error('Cal.com slots returned ' + res.status);
      const nowMs = Date.now();
      const lastMs = nowMs + maxDays * DAY_MS;
      state.isos = parseSlotsResponse(await readJson(res)).filter((iso) => Date.parse(iso) > nowMs && Date.parse(iso) <= lastMs);
      regroup();
    }

    /* One slots request at a time, reused while fresh; a failed one is forgotten so the next call retries. */
    function slotsReady(force) {
      if (force || !state.slotsPromise || Date.now() - state.slotsAt > SLOTS_FRESH_MS) {
        state.slotsAt = Date.now();
        const pending = loadSlots();
        state.slotsPromise = pending;
        pending.catch(() => { if (state.slotsPromise === pending) state.slotsPromise = null; });
      }
      return state.slotsPromise;
    }

    function regroup() {
      state.todayKey = dayKey(new Date(), state.tz);
      state.pages = monthView ? monthsToCover(state.todayKey, maxDays) : weeksToCover(state.todayKey, maxDays);
      state.byDay = groupSlotsByDay(state.isos, state.tz);
    }

    function firstPageWithSlots() {
      return monthView
        ? findFirstMonthWithSlots(state.todayKey, state.byDay, state.pages)
        : findFirstWeekWithSlots(state.todayKey, state.byDay, state.pages);
    }

    /* The days on the current page: a week, or a month grid with null cells before the 1st. */
    function currentCells() {
      if (monthView) return buildMonth(addMonths(monthOf(state.todayKey), state.pageIndex), state.todayKey, state.byDay);
      return buildWeek(addDays(weekStart(state.todayKey), 7 * state.pageIndex), state.todayKey, state.byDay);
    }

    function renderTimeZone() {
      const now = new Date();
      if (!ui.tz.options.length) {
        const fragment = document.createDocumentFragment();
        listTimeZones(state.tz).forEach((zone) => {
          const option = el('option', null, zoneLabel(zone, now));
          option.value = zone;
          fragment.appendChild(option);
        });
        ui.tz.appendChild(fragment);
      }
      ui.tz.value = state.tz;
      ui.now.textContent = '(' + formatTime(now.toISOString(), state.tz) + ')';
    }

    function dayButton(d) {
      const label = dayLabel(d.key);
      const button = el('button', 'ap-dw-day' + (d.isToday ? ' is-today' : ''));
      button.type = 'button';
      button.dataset.day = d.key;
      button.disabled = !d.available;
      button.setAttribute('aria-pressed', String(d.key === state.day));
      button.setAttribute('aria-label', formatLongDate(d.key + 'T12:00:00Z', 'UTC') + (d.available ? '' : ', no open times'));
      if (!monthView) button.appendChild(el('span', 'ap-dw-dow', label.dow));
      const date = el('span', 'ap-dw-date');
      date.appendChild(el('span', 'ap-dw-num', label.day));
      if (!monthView) date.appendChild(el('span', 'ap-dw-mon', label.mon));
      button.appendChild(date);
      return button;
    }

    function renderDays() {
      const cells = currentCells();
      const days = cells.filter(Boolean);
      if (!days.some((d) => d.key === state.day && d.available)) {
        const firstOpen = days.find((d) => d.available);
        state.day = firstOpen ? firstOpen.key : null;
        state.slot = null;
      }
      ui.days.textContent = '';
      if (monthView) {
        ui.month.textContent = monthLabel(addMonths(monthOf(state.todayKey), state.pageIndex));
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((dow) => {
          const head = el('span', 'ap-dw-dowh', dow);
          head.setAttribute('aria-hidden', 'true');
          ui.days.appendChild(head);
        });
      }
      cells.forEach((d) => ui.days.appendChild(d ? dayButton(d) : el('span', 'ap-dw-blank')));
      ui.prev.disabled = state.pageIndex === 0;
      ui.next.disabled = state.pageIndex >= state.pages - 1;
      renderSlots();
    }

    function renderSlots() {
      const list = state.day ? state.byDay[state.day] || [] : [];
      ui.slots.textContent = '';
      ui.empty.hidden = list.length > 0;
      if (monthView) {
        ui.timesHeading.textContent = state.day ? formatLongDate(state.day + 'T12:00:00Z', 'UTC') : 'What time works?';
        ui.empty.textContent = 'No open times this month. Try the next month.';
      }
      /* One click on a time books it (Jephonie's 3-click flow, 2026-10-13): while it books, the
         clicked time reads "Booking…" and the others wait. */
      list.forEach((iso) => {
        const time = formatTime(iso, state.tz);
        const booking = state.busy && iso === state.slot;
        const button = el('button', 'ap-dw-slot' + (booking ? ' is-booking' : ''), booking ? 'Booking…' : time);
        button.type = 'button';
        button.dataset.slot = iso;
        button.disabled = state.busy;
        button.setAttribute('aria-pressed', String(iso === state.slot));
        button.setAttribute('aria-label', 'Book ' + time + ', ' + formatLongDate(iso, state.tz));
        ui.slots.appendChild(button);
      });
      ui.cal.classList.toggle('is-busy', state.busy);
    }

    const chosenText = (iso) => formatLongDate(iso, state.tz) + ' at ' + formatTime(iso, state.tz);

    function finishBooking() {
      if (state.finished) return;
      state.finished = true;
      const target = testMode ? cfg.thanksPreviewUrl : cfg.thanksUrl;
      const go = () => {
        if (track) clearEcUserData(sessionStore());
        if (target) { window.location.assign(target); return; }
        show(ui.done);
        say('Your demo is booked.');
      };
      if (track) pushBookingEvent(dataLayer, go);
      else go();
    }

    function showFallback(note) {
      show(ui.fallback);
      ui.fallbackNote.textContent = note;
      ui.fallbackNote.hidden = !note;
      say(note || 'Pick a day and a time in the calendar below.');
      if (mode === 'dry') ui.ribbon.textContent = 'TEST MODE (dry run): the Cal.com calendar below is the real one. Do not confirm a booking in it.';
      ui.fallback.setAttribute('tabindex', '-1');
      ui.fallback.focus();
      if (ui.fallback.getAttribute('data-mounted') === '1') return;
      ui.fallback.setAttribute('data-mounted', '1');
      mountCalFallback({
        /* Kit: the mount point is this widget's own element (live used the #ap-dw-fallback-cal id). */
        selector: ui.fallback.querySelector('.ap-dw-fallback-cal'),
        calLink: cfg.user ? cfg.user + '/' + cfg.event : 'team/' + cfg.team + '/' + cfg.event,
        name: state.first + ' ' + state.last,
        email: state.email,
        onBooked: () => { reportBooking('booked', ''); finishBooking(); },
      });
    }

    async function start() {
      state.busy = true;
      setWaiting(true);
      clearWarn();
      say(embedMode ? 'Opening the calendar…' : 'Finding open times…');
      /* HubSpot runs in the background and never holds the calendar back. */
      if (state.capturedFor !== state.email) captureLead().catch(() => {});
      if (state.needsWorkEmail) {
        state.busy = false;
        setWaiting(false);
        showReach();
        return;
      }
      let slotsOk = true;
      try {
        if (!embedMode) {
          try { await slotsReady(); } catch (e) { slotsOk = false; }
        }
      } finally {
        state.busy = false;
        setWaiting(false);
      }
      if (embedMode) { showFallback(''); return; }
      if (!slotsOk) { showFallback('We could not load open times here, so here is our standard booking calendar.'); return; }
      const firstPage = firstPageWithSlots();
      if (firstPage < 0) {
        show(ui.none);
        say('No open times right now.');
        ui.none.setAttribute('tabindex', '-1');
        ui.none.focus();
        return;
      }
      state.pageIndex = firstPage;
      state.day = null;
      state.slot = null;
      ui.who.textContent = state.first + ' ' + state.last + ' · ' + state.email;
      renderTimeZone();
      renderDays();
      show(ui.cal);
      if (ui.company) {
        say('Open times loaded. Enter your company name, then pick a day and a time.');
        ui.company.focus();
      } else {
        say('Open times loaded. Pick a day and a time.');
        ui.heading.focus();
      }
    }

    /* No clear answer from Cal.com (we stopped waiting, or a server error that can come after the
       booking is made): if the time has left the open times, the booking went through. */
    async function bookingWentThrough(iso) {
      try { await slotsReady(true); } catch (e) { return false; }
      return state.isos.indexOf(iso) === -1;
    }

    async function confirmBooking() {
      if (!state.slot || state.busy || state.finished) return;
      clearWarn();
      const bookedIso = state.slot;
      const bookingRequest = (withDetails) => buildBookingRequest({
        teamSlug: cfg.team || undefined,
        username: cfg.user || undefined,
        eventSlug: cfg.event,
        startIso: bookedIso,
        name: state.first + ' ' + state.last,
        email: state.email,
        timeZone: state.tz,
        source: SOURCE,
        company: withDetails ? state.company : '',
        phone: withDetails ? state.phone : '',
      });
      const request = bookingRequest(true);
      if (!send) { console.info('[ap-demo] DRY RUN: booking not sent', request); finishBooking(); return; }
      state.busy = true;
      renderSlots();
      say('Booking ' + chosenText(bookedIso) + '…');
      const post = (req) => fetchWithTimeout(req.url, { method: 'POST', headers: req.headers, body: JSON.stringify(req.body) }, bookTimeout)
        .then((res) => readJson(res).then((json) => ({ status: res.status, json })))
        .catch(() => ({ status: 0, json: null }));
      const answer = post(request);
      const first = await Promise.race([answer, new Promise((resolve) => setTimeout(() => resolve(null), fastConfirm))]);
      if (!first) {
        /* No refusal in time: the booking is under way and Cal.com finishes it without us. */
        state.busy = false;
        say('Your demo is booked.');
        reportBooking('likely', bookedIso);
        finishBooking();
        return;
      }
      let outcome = classifyBookingResponse(first.status, first.json);
      /* Cal.com refused the request that carried the company and phone (a booking field this event
         does not take, a phone it will not accept): book again with the name and email only. */
      if (outcome === 'rejected' && request.body.bookingFieldsResponses) {
        console.info('[ap-demo] booking refused with the company and phone attached; booking again without them');
        const second = await post(bookingRequest(false));
        outcome = classifyBookingResponse(second.status, second.json);
      }
      if ((outcome === 'unknown' || outcome === 'unavailable') && await bookingWentThrough(bookedIso)) outcome = 'booked';
      state.busy = false;
      if (outcome === 'booked') { say('Your demo is booked.'); reportBooking('booked', bookedIso); finishBooking(); return; }
      renderSlots();
      if (outcome === 'slot_taken') {
        const taken = bookedIso;
        state.slot = null;
        try { await slotsReady(true); } catch (e) { /* keep the times we already have */ }
        state.isos = state.isos.filter((iso) => iso !== taken);
        regroup();
        renderDays();
        warn('That time was just booked. Please pick another.');
        ui.heading.focus();
        return;
      }
      if (outcome === 'unknown') {
        state.slot = null;
        renderSlots();
        warn('We could not confirm that booking. Check your email in a few minutes: if a confirmation arrives, you are all set. If not, pick a time again.');
        ui.heading.focus();
        return;
      }
      showFallback('We could not confirm that time here, so here is our standard booking calendar.');
    }

    /* ---- events ---- */
    root.addEventListener('input', syncFilled);

    /* Real input inside the form: the browser marks events from a person (keys, taps, clicks,
       autofill) as trusted; events a script makes up are not. */
    const noteInput = (event) => {
      if (!event.isTrusted) return;
      state.interacted = true;
      if (guard && !state.tokenTried) freshToken();
    };
    ['keydown', 'pointerdown', 'mousedown', 'touchstart', 'click', 'input', 'paste'].forEach((type) => {
      ui.form.addEventListener(type, noteInput, { capture: true, passive: true });
    });

    ui.form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (state.busy) return;
      ui.emailErr.hidden = true;
      ui.nameErr.hidden = true;
      if (!isValidEmail(ui.email.value)) {
        ui.emailErr.textContent = 'Please enter a valid work email.';
        ui.emailErr.hidden = false;
        ui.email.focus();
        return;
      }
      if (ui.names.hidden) { ui.names.hidden = false; ui.first.focus(); return; }
      const first = cleanName(ui.first.value);
      const last = cleanName(ui.last.value);
      if (!first || !last) {
        ui.nameErr.textContent = 'Please enter your first and last name.';
        ui.nameErr.hidden = false;
        (first ? ui.last : ui.first).focus();
        return;
      }
      if (!looksHuman({ shownAt: state.shownAt, now: Date.now(), minDelay })) {
        ui.nameErr.textContent = 'Please try again in a moment.';
        ui.nameErr.hidden = false;
        return;
      }
      state.email = ui.email.value.trim();
      state.first = first;
      state.last = last;
      state.needsWorkEmail = workEmailNeeded(state.email);
      state.flags = botSignals({ webdriver: navigator.webdriver, userAgent: navigator.userAgent, interacted: state.interacted });
      start();
    });

    ui.change.addEventListener('click', () => {
      if (state.busy || state.finished) return;
      show(ui.form);
      ui.email.focus();
    });

    ui.workEmail.addEventListener('click', () => {
      if (state.busy || state.finished) return;
      show(ui.form);
      ui.email.focus();
      ui.email.select();
    });

    ui.days.addEventListener('click', (event) => {
      if (state.busy || state.finished) return;
      const button = event.target.closest('.ap-dw-day');
      if (!button || button.disabled) return;
      clearWarn();
      state.day = button.dataset.day;
      state.slot = null;
      renderDays();
      focusPressed(ui.days);
    });

    ui.slots.addEventListener('click', (event) => {
      if (state.busy || state.finished) return;
      const button = event.target.closest('.ap-dw-slot');
      if (!button || button.disabled) return;
      clearWarn();
      if (!detailsReady()) return;
      state.slot = button.dataset.slot;
      sendDetails().catch(() => {});
      confirmBooking();
    });

    ui.prev.addEventListener('click', () => {
      if (state.busy || state.finished || state.pageIndex === 0) return;
      clearWarn();
      state.pageIndex -= 1;
      state.day = null;
      state.slot = null;
      renderDays();
    });

    ui.next.addEventListener('click', () => {
      if (state.busy || state.finished || state.pageIndex >= state.pages - 1) return;
      clearWarn();
      state.pageIndex += 1;
      state.day = null;
      state.slot = null;
      renderDays();
    });

    ui.tz.addEventListener('change', () => {
      if (state.busy || state.finished) { ui.tz.value = state.tz; return; }
      state.tz = ui.tz.value;
      state.day = null;
      state.slot = null;
      regroup();
      const firstPage = firstPageWithSlots();
      state.pageIndex = firstPage < 0 ? 0 : firstPage;
      renderTimeZone();
      renderDays();
      say('Times shown in ' + zoneLabel(state.tz, new Date()) + '.');
    });


    probeRegion();
    syncFilled();
  }

  /* ===== kit boot (live widget/main.js) ===== */
  function bootDemoWidgets() {
    document.querySelectorAll('[data-ap-demo-widget]').forEach((root) => {
      if (root.getAttribute('data-ready') === '1') return;
      root.setAttribute('data-ready', '1');
      initDemoWidget(root);
    });
  }

  /* ---------- Testimonial sliders (live page/cases.js) ----------
     One slide shows at a time; dots, arrows and the progress line pick it. Images of slides not
     yet shown wait in data-src and load when their slide or a neighbour comes up. Sliders advance
     on their own every CASES_AUTO_MS; a mouse over a slider or keyboard focus in it pauses it (taps
     do not, so phones keep advancing), a dot or arrow restarts the count, and visitors who ask for
     reduced motion get no auto-advance. Keyed off data-ap-cases; data-ready makes a second run (or
     a second component's copy of this code) a no-op. */
  const CASES_AUTO_MS = 3000;

  function casesLoadImages(slide) {
    if (!slide) return;
    slide.querySelectorAll('img[data-src]').forEach((img) => {
      img.setAttribute('src', img.getAttribute('data-src'));
      img.removeAttribute('data-src');
    });
  }

  function casesReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function casesKeyboardFocus(el) {
    try { return el.matches(':focus-visible'); } catch (e) { return false; }
  }

  function initCasesSlider(root) {
    if (root.getAttribute('data-ready') === '1') return;
    root.setAttribute('data-ready', '1');
    const slides = root.querySelectorAll('[data-case]');
    const dots = root.querySelectorAll('[data-case-dot]');
    const bar = root.querySelector('.ap-d26-case-bar');
    const count = slides.length;
    if (!count) return;
    const live = slides[0].parentNode;
    let current = 0;
    let timer = 0;
    let hovered = false;
    let focused = false;
    const autoplay = count > 1 && !casesReducedMotion();

    function schedule() {
      clearTimeout(timer);
      const running = autoplay && !hovered && !focused && !document.hidden;
      /* Screen readers announce a slide change only when a person made it. */
      live.setAttribute('aria-live', running ? 'off' : 'polite');
      if (running) timer = setTimeout(() => go(current + 1), CASES_AUTO_MS);
    }

    function go(index) {
      current = (index + count) % count;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
      dots.forEach((dot, i) => {
        dot.classList.toggle('is-active', i === current);
        if (i === current) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
      });
      if (bar) bar.style.transform = 'translateX(' + current * 100 + '%)';
      casesLoadImages(slides[current]);
      casesLoadImages(slides[(current + 1) % count]);
      casesLoadImages(slides[(current - 1 + count) % count]);
      schedule();
    }

    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
    const prev = root.querySelector('[data-case-prev]');
    const next = root.querySelector('[data-case-next]');
    if (prev) prev.addEventListener('click', () => go(current - 1));
    if (next) next.addEventListener('click', () => go(current + 1));
    root.addEventListener('pointerenter', (event) => { hovered = event.pointerType === 'mouse'; schedule(); });
    root.addEventListener('pointerleave', () => { hovered = false; schedule(); });
    root.addEventListener('focusin', (event) => { focused = casesKeyboardFocus(event.target); schedule(); });
    root.addEventListener('focusout', (event) => {
      if (event.relatedTarget && root.contains(event.relatedTarget)) return;
      focused = false;
      schedule();
    });
    document.addEventListener('visibilitychange', schedule);
    go(0);
  }

  function initCasesIn(scope) {
    document.querySelectorAll(scope + ' [data-ap-cases]').forEach(initCasesSlider);
  }

  /* ---------- Stats count-up (live page/stats.js) ----------
     Each [data-ap-count] number in a [data-ap-stats] list counts up from 0 to the value in its
     markup the first time the list scrolls into view. The markup keeps the real value, so without
     JS, without IntersectionObserver or with reduced motion the final number simply shows; a hidden
     copy of the final value (.ap-d26-stat-ghost) holds the width while it counts. */
  const STATS_DURATION_MS = 1600;

  /* "10,000+" -> { prefix: '', value: 10000, suffix: '+', grouped: true } */
  function parseStat(text) {
    const match = /^(\D*)(\d[\d,]*)(\D*)$/.exec(text.trim());
    if (!match) return null;
    return { prefix: match[1], value: parseInt(match[2].replace(/,/g, ''), 10), suffix: match[3], grouped: match[2].includes(',') };
  }

  function formatStat(stat, n) {
    const digits = stat.grouped ? String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : String(n);
    return stat.prefix + digits + stat.suffix;
  }

  const statsEase = (t) => 1 - Math.pow(1 - t, 3);

  function statsCountUp(items) {
    const start = performance.now();
    function frame(now) {
      const t = Math.min(1, Math.max(0, (now - start) / STATS_DURATION_MS));
      items.forEach(({ el, stat }) => { el.textContent = formatStat(stat, Math.round(stat.value * statsEase(t))); });
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function initStatsList(list) {
    if (list.getAttribute('data-count-ready') === '1') return;
    list.setAttribute('data-count-ready', '1');
    const items = [];
    list.querySelectorAll('[data-ap-count]').forEach((el) => {
      const stat = parseStat(el.textContent);
      if (stat) items.push({ el, stat });
    });
    if (!items.length) return;
    items.forEach(({ el, stat }) => { el.textContent = formatStat(stat, 0); });
    const observer = new IntersectionObserver((entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) return;
      observer.disconnect();
      statsCountUp(items);
    }, { threshold: 0.4 });
    observer.observe(list);
  }

  function initStatsIn(scope) {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll(scope + ' [data-ap-stats]').forEach(initStatsList);
  }

  /* ---------- Split layout above the fold, desktop (live page/fold.js) ----------
     The split hero fills one screen below the site header, down to whatever follows it (a stats
     band, the compact footer), so the page never scrolls (Joseph 2026-10-16), and never changes
     height with its content. The CSS sizes it from --ap-d26-vh (the window height), --ap-d26-fold-top
     (where the hero starts: site header, admin bar, anything above it) and --ap-d26-below
     (everything under the hero: the following sections, the footer and its wrappers). All three are
     measured here on load and on resize only, never from the hero's own content, so the fold stays
     put while the form grows and the calendar opens. */
  /* The element that ends the page: whichever footer sits lowest (its <footer> landmark when it has
     one, so the landmark's own padding counts). */
  function foldEnd() {
    let end = null;
    let endBottom = -Infinity;
    for (const el of document.querySelectorAll('.ap-site-footer, .ap-demo-footer')) {
      const box = (el.closest('footer') || el).getBoundingClientRect();
      if (box.bottom > endBottom) { end = el.closest('footer') || el; endBottom = box.bottom; }
    }
    return end;
  }

  const foldLast = {};
  function setFoldVar(name, value) {
    const v = (Math.round(value * 100) / 100) + 'px';
    if (foldLast[name] === v) return;
    foldLast[name] = v;
    document.documentElement.style.setProperty(name, v);
  }

  function measureFold() {
    const hero = document.querySelector('[data-ap-fold]');
    if (!hero) return;
    const box = hero.getBoundingClientRect();
    setFoldVar('--ap-d26-vh', window.innerHeight);
    setFoldVar('--ap-d26-fold-top', Math.max(0, box.top + window.scrollY));
    /* Distance from the hero's bottom to the bottom of the page-ending footer. Unlike scrollHeight
       (which never drops below the window height and counts anything that sits in the page for a
       moment: a password-manager frame, the chat widget settling, a scrollbar flicker), it does not
       depend on the hero's own height, so re-measuring is always safe and one bad read can't stick
       (fix ported from the www build, worktree commit f01d156, 2026-10-27). */
    const end = foldEnd();
    if (end) {
      setFoldVar('--ap-d26-below', Math.max(0, end.getBoundingClientRect().bottom - box.bottom));
    }
  }

  function initFold() {
    if (!document.querySelector('[data-ap-fold]')) return;
    if (document.documentElement.getAttribute('data-ap-fold-ready') === '1') return;
    document.documentElement.setAttribute('data-ap-fold-ready', '1');
    let queued = false;
    const later = () => {
      if (queued) return;
      queued = true;
      requestAnimationFrame(() => { queued = false; measureFold(); });
    };
    measureFold();
    window.addEventListener('resize', later);
    window.addEventListener('load', measureFold);
    if ('ResizeObserver' in window) new ResizeObserver(later).observe(document.body);
  }

  /* The count-up and the fold run at once (the markup is already parsed when this footer script
     runs), as live's inline scripts did; the widget and the card wait for DOMContentLoaded. */
  initStatsIn('.ap-demo-booking');
  initFold();
  function boot() {
    bootDemoWidgets();
    initCasesIn('.ap-demo-booking');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();

(() => {
  /* QA 2026-10-29: the one-screen hero squeezes the testimonial card on laptop screens. Each quote is
     clamped to the lines that fit its slide, so it ends on "…" and the author row always shows; a
     slide with no room for a single line shows its title and author only. Runs on load, on resize, when the
     fonts arrive and when the hero column changes size; one read pass, then one write pass. */
  function fitQuotes() {
    const sets = document.querySelectorAll('.ap-demo-booking .ap-d26-v1-side .ap-d26-qslides');
    if (!sets.length) return;
    const wide = window.matchMedia('(min-width:1024px)').matches;
    sets.forEach((set) => {
      const slides = [...set.querySelectorAll('.ap-d26-qslide')];
      slides.forEach((s) => { const t = s.querySelector('.ap-d26-qtext'); if (t) { t.style.removeProperty('-webkit-line-clamp'); t.style.removeProperty('display'); } });
      if (!wide) return;
      const plan = slides.map((s) => {
        const t = s.querySelector('.ap-d26-qtext');
        if (!t) return null;
        const gap = parseFloat(getComputedStyle(s).rowGap) || 0;
        let fixed = 0;
        for (const k of s.children) if (k !== t) fixed += k.getBoundingClientRect().height;
        fixed += gap * (s.children.length - 1);
        const cs = getComputedStyle(t), lh = parseFloat(cs.lineHeight) || 24, max = parseInt(cs.webkitLineClamp, 10) || 99;
        return [t, Math.min(max, Math.floor((s.clientHeight - fixed - 2) / lh))];
      });
      const apply = (t, n) => { if (n < 1) t.style.display = 'none'; else t.style.setProperty('-webkit-line-clamp', String(n)); };
      plan.forEach((x) => { if (x) apply(x[0], x[1]); });
      /* Check pass: a quote the card still squeezes below its clamped height loses a line. */
      plan.forEach((x) => {
        if (!x) return;
        const lh = parseFloat(getComputedStyle(x[0]).lineHeight) || 24;
        for (let n = x[1]; n > 0 && x[0].clientHeight + 1 < n * lh; ) apply(x[0], --n);
      });
    });
  }
  let queued = false;
  const later = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; fitQuotes(); }); };
  if (!document.querySelector('.ap-demo-booking .ap-d26-qslides') || document.documentElement.hasAttribute('data-ap-qfit')) return;
  document.documentElement.setAttribute('data-ap-qfit', '1');
  later();
  window.addEventListener('resize', later);
  window.addEventListener('load', later);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(later);
  /* The hero's height is set by the fold measure (after load, on resize); the side column's size
     follows the hero, never the card's content, so watching it cannot loop. */
  if ('ResizeObserver' in window) {
    const ro = new ResizeObserver(later);
    document.querySelectorAll('.ap-demo-booking .ap-d26-v1-side').forEach((el) => ro.observe(el));
  }
})();
