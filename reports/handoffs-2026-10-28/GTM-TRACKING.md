# Handoff: GTM server-side tracking (Asset Panda)

Written 2026-10-28. State as of 03:53 Manila, Oct 28 (the old chat's last turn), re-checked where noted.
The old chat is [GTM server-side tracking audit](claude://claude.ai/epitaxy/local_4392bf72-5c57-4182-931e-348dce6966db). It's idle and left as it is.

## The job
Full server-side tracking for assetpanda.com, with every conversion counted once and counted correctly.

The data path: web GTM → data.assetpanda.com (Stape) → server GTM → Google Ads, GA4, Microsoft, LinkedIn, Reddit, Meta and OpenAI.

Joseph's latest ask (Oct 27): "full audit on tracking… include hubspot and everything else where the data is being sent."

Keep this chat to tracking. Page builds belong to NEW SITE and Book a Demo; lead analysis belongs to the lead-drop chat.

## Our lane (hard rules)
- **We change only GTM, server GTM and Stape.** Google Ads, Microsoft Ads, Meta, LinkedIn, Reddit, GA4 admin and HubSpot settings belong to Jeph and Jon. Read them to diagnose, then hand over a paste-ready instruction.
- **Salesforce is hands-off.** No reports (not even unsaved ones), no list views, no settings. Write the check for Jon instead.
- **No sign-ins or secrets.** Never sign in, and never type passwords or tokens.
- **Plugin installs on www are blocked for Claude.** Stage them on the Add Plugins page for Joseph's two clicks.

## Working now
- All 10 Google Ads conversions go server-side.
- GA4 goes through data.assetpanda.com.
- Reddit is fully server-side.
- Pro Free Trial is fixed (web GTM v241, Oct 26). GA4 has logged 8 trial sign-ups since.
- The Get a Demo page build d26-202610261519 (Oct 26) keeps the email for Enhanced Conversions and carries Jon's copy.

## Staged, NOT published: check both workspaces first
Publishing ships everything in a workspace, so preview first, then Joseph publishes.

**Web GTM, workspace 1000243 (11 changes)**
- Ours (3):
  - "HubSpot Form Listener" (#167) now understands HubSpot's new v4 forms.
  - "EC – Form PII Capture" (#342) does too.
  - "Bing Ads – Pro Free Trial Form" (#308) is paused.
- The lead-drop chat's (8): GA4 tags for demo and pricing form submits, plus 4 login-click changes.
- ⚠ Workspace 1000242 is closed. Edits made through an old 1000242 link silently don't save.

**Server GTM, workspace 18 (6 changes)**
- Ours (1): "Microsoft UET – Pro Free Trial (Server)".
- The lead-drop chat's (5): moves **Demo Request – General** and **Pricing Page Conversion**, the Primary conversions bidding runs on, from thank-you page views to form submits.
- **Jeph must confirm before this is published.** The note is at `C:\Sample Code\reports\lead-drop-audit-2026-10\GTM-review-note.md`.

## Waiting on Joseph's clicks
- **Search Console file for Jeph's disavow.**
  - The plugin "AP Site Verification" is built: `C:\Users\josep\AppData\Local\Temp\claude\C--Sample-Code\b2624474-d585-4982-a232-fee1c673822c\scratchpad\ap-site-verification.zip`.
  - **It's not installed yet.** I checked on Oct 28: https://www.assetpanda.com/google77836f00c7690ebf.html still redirects to /404-not-found/.
  - Joseph clicks Install and Activate on www, then Jeph clicks Verify. Keep the plugin active.
- **Stape Custom Loader** (ad-blocker recovery is 0%).
  - The Stape side has been on since Oct 15.
  - The "AP Tag Loader" plugin that switches the site over was never installed, and **its files are gone**: the scratchpad folder `ap-tag-loader\` is empty.
  - Rebuild it from memory `ap-stape-custom-loader`, test it, and stage it for install.

## Open problems from the Oct 27 audit, worst first
1. **Salesforce → Google Ads "Opportunity" and "Closed Won" have sent nothing in 2026** ($1.1M flowed through in 2025).
   - The fields and mapping are fine, so the 2026 opportunities lack click IDs.
   - The check for Jon: opportunities created in 2026, with GCLID, Lead Source, and whether each came from a converted lead.
2. **HubSpot's Ads "Conversion Events" were never set up.** So there are no Enhanced Conversions for Leads and no LinkedIn offline conversions. Jeph's call.
3. **GA4 counts every phone click twice.** A create-event rule re-creates `click_to_call` from itself; a GA4 admin deletes that rule.
4. **GA4's demo and pricing "conversions" count thank-you page views,** not real bookings: 317 against 101 in 4 weeks. Report from the server actions instead.
5. **LinkedIn counts resource downloads twice** (web #250 plus server #67).
   - Don't pause #250 until #67 has been seen firing.
   - That needs one real white-paper form submit in server Preview, which creates a junk lead.
   - We have no LinkedIn access.
6. **Demo Booking (Server):** some bookings arrive without an email, and the lead-drop audit saw 0 recorded since Oct 2. The cause hasn't been traced.
7. **An unknown script calls `g792337341.co/geolocation` on every page,** probably the Tier A/B country check. Ask Jon who owns it.
8. **Older backlog:**
   - event_id deduplication was never built. Meta will double-count the day its server tags are unpaused, and 2 Meta pixels are live.
   - Meta is waiting on Jon's token.
   - The OpenAI server tags are paused.
   - The Advanced Consent Mode decision hasn't been made.
   - About 40 triggers are orphaned.
   - Legacy UTM tag 99 throws an error.
   - GA4 requests from the login subdomain haven't been looked into.

**Scoped, not built:** per-plan CTA tracking on the pricing page (Oct 19), and ICP/CTA parameters on demo links (Oct 22, for Jon).

**Google rep email (Oct 22):** Jeph's Primary/Secondary setup stands. Only ask her to check that Enhanced Conversions is on for Demo Request – General.

## Key IDs
| What | ID |
|---|---|
| Web GTM | GTM-WVPZ4X (account 324657457, container 2310132) |
| Server GTM | GTM-TDBRG75X (account 6372309797, container 261732628) |
| Stape | container laymtlkg at data.assetpanda.com |
| GA4 | 307361531 |
| Google Ads | 580-941-6585 |
| HubSpot | portal 4155061 (na2) |
| LinkedIn | 500098775 |
| Microsoft UET | 5565430 |

## GTM editing traps
- Custom HTML must be ES5.
- After pasting code, type a real keystroke so Save turns on, then run Validate.
- Check the workspace id in the URL.
- HubSpot's forms API replaces the whole form; it wiped 231 forms in August. Use the HubSpot UI.
- A 200 from Google's conversion endpoint means accepted, not counted.

## Where things are
- **Audit checklist and tests:** `C:\Users\josep\AppData\Local\Temp\claude\C--Sample-Code\b2624474-d585-4982-a232-fee1c673822c\scratchpad\audit-1028\`
- **Full old transcript:** `C:\Users\josep\.claude\projects\C--Sample-Code\b2624474-d585-4982-a232-fee1c673822c.jsonl`
- **Memory to read first:**
  - `feedback-working-rules-2026-10-27`
  - `ap-tracking-audit-2026-08` (the newest entries are at the bottom)
  - `gtm-only-no-ad-platform-changes`
  - `feedback-salesforce-hands-off`
  - `ap-linkedin-duplicate-conversions`
  - `ap-stape-custom-loader`
  - `ap-lead-drop-audit-2026-10-28`
  - `calcom-booking-tracking-gtm`

## First message for the new chat
> Read `C:\Sample Code\reports\handoffs-2026-10-28\GTM-TRACKING.md` and the memory files it lists. Open both GTM workspaces and confirm what's still unpublished before changing anything.
