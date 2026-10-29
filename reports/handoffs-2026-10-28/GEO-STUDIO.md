# Handoff: GEO Studio (live v3 app, Addendum D, V4 multi-tenant review)

Written 2026-10-28. State as of 01:10 Manila, Oct 28 (the old chat's last turn). GitHub was re-checked when this was written.
The old chat is [GEO Studio v3 AI performance reporting QA](claude://claude.ai/epitaxy/local_36b43e81-44d6-4fb0-8bf4-148922b2fe21). It's idle and left as it is.

## The job
GEO Studio lives in the repo hpi-int/AP-GEO-Studio.
- **Live app:** https://geostudio-beta.com (branch `main`)
- **Staging:** https://ap-geo-studio-dev-c3m2i.ondigitalocean.app (branch `do-staging`)
- **The two share one database.**

Our role is QA and review. Mairene builds V4 multi-tenant on her own branch.

Joseph's latest ask (Oct 27): "Please run QA on the new branch from Mairene. See if there should be any changes from the multi-tenant. Please also send me the link so I can review the frontend."

## Where the code is (checked on GitHub at handoff)
| Branch | Head | Notes |
|---|---|---|
| `main` | 1347e5a, Oct 26 | Live-resource fix (plugin 2.5.0). |
| `do-staging` | f17aa06, Oct 26 | `main` merged in. Still carries Mairene's Addendum D work (2c2feff); `main` doesn't. |
| `feature/geo-v4-multitenant` | 382e2c8, Oct 24 | Mairene's branch. |

**PR #1:** https://github.com/hpi-int/AP-GEO-Studio/pull/1 merges `merge/v4-multitenant-live-resources` into her branch. It's open, head df01f2d. The Oct 27 fix in it makes the "update live pages in place" switch and the resource path per-company again, with a regression test.

## V4 multi-tenant: status
- **Plan:** one database schema per company. 152 tasks in Jira, GEO-152 to GEO-303 (epics GEO-143 to GEO-151), assigned to Mairene.
- **Oct 27 QA:**
  - Since Oct 23 she made one small test commit, but on Oct 24 she moved 130 tickets to Done with no comments.
  - Of the 43 problems found on Oct 23: 1 is fixed, 41 are unchanged, and 1 got worse.
  - The one that got worse: V4's new user-agent "GEO-Studio/1.0" would now reach live www, and WP Engine blocks it.
- **Must fix before any V4 deploy:** staging's deploy file (`.do/app-dev.yaml`) arms the database move (`V4_RELEASE_MIGRATIONS=1`), while staging still points at production's database.
- **Must fix before a second company goes live:**
  - A company admin can read Asset Panda's transcripts (GEO-261, critical).
  - A company admin can get operator access by minting keys (GEO-170).
  - A pasted Google key makes the server call any address (SSRF).
  - DataForSEO spend has no account-wide ceiling.
  - Two prompts still say "Asset Panda".
  - The scheduler starts paid jobs (GEO-284).
- **No V4 deployment exists.** Frontend review is local only:
  1. In DigitalOcean, go to Databases → **geo-studio-v4-staging-db** → **Network Access** and add Joseph's IP.
  2. Copy the `geo_v4` connection string and save it as one line in `C:\Users\josep\v4-db-url.txt`.
  3. Run `bash "C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/6863c2c2-fd21-4755-9c65-68c92e7f432b/scratchpad/run-v4-local.sh"`.
  4. Open http://localhost:5173.

  Joseph hasn't done steps 1 and 2 yet. The local copy of her branch is `C:\geo-v4-local`.

## Addendum D (Jephonie's spec)
- Built on staging only (2c2feff, Oct 13–14).
- **Not done:**
  - T6: a trustworthy "Not Present", with weekly direct checks.
  - T7: page matching.
  - The competitor items, contrast, and the acceptance run.
- **Keep it off production until T6 lands.** Without it, "Brand visibility gap" fires on weak indexed-only data.
- The labels "High Priority" and "Data syncing" stay until T6 and T7 land.

## Other open items
- **Live app:** the draft-progress stream (`/api/generate/events`) sends draft text without a login.
- **Transcripts:** the database status rule may mark a transcript "failed" instead of "empty". That needs a check on production.
- **Leaked token:** a SEMrush token was posted in the Jephonie/Mairene Slack DM on Aug 10. Delete the message and rotate the key.
- **Multi-tenant plan page:** https://claude.ai/artifact/Bzhr5VZDRGaX8kMCLXFzYH is private, so share it before Robert opens it. 19 decisions with defaults are waiting on Joseph; approving schema-per-company comes first.
- **Daily import:** the GSC + Bing import runs from Joseph's PC at 00:00 UTC as a scheduled task.

## Rules
- **Production first.** After checks, fast-forward `main` and push the same SHA to `do-staging`, then verify on geostudio-beta.com/healthz.
- **Git:** no database migrations without Joseph's go, and never force-push.
- **Mairene's branch:** never push, rebase or reset it. Open a PR into it instead.
- **Database access:** never use the doadmin password or change database network settings; Joseph does that.
- **Content:** drafts link internally only, and never rename "Google AI Impressions".
- No AI attribution in commits.

## Where things are
- **Reports** in `C:\Users\josep\AppData\Local\Temp\claude\C--Sample-Code\6863c2c2-fd21-4755-9c65-68c92e7f432b\scratchpad\`:
  - `GEO-Studio-V4-QA-2026-10-28.md`
  - `GEO-Studio-multi-tenant-review-2026-10-23.md`
  - `GEO-Studio-multi-tenant-tasks-for-Mairene.md`
- **Checkouts:**
  - `C:\AP-GEO-Studio`: main repo
  - `C:\geo-merge-v4`: PR #1
  - `C:\geo-v4-local`: her branch. Its `frontend\node_modules` is a link, so remove it with `rmdir`, never a recursive delete.
- **Full old transcript:** `C:\Users\josep\.claude\projects\C--Sample-Code\6863c2c2-fd21-4755-9c65-68c92e7f432b.jsonl`
- **Memory to read first:**
  - `feedback-working-rules-2026-10-27`
  - `geo-studio-multi-tenant-plan`
  - `geo-studio-addendum-d`
  - `geo-studio-prod-first-2026-10-02`
  - `geo-studio-mairene-branch-2026-09-30`
  - `geo-live-resource-update-2026-10-26`
  - `ap-geo-studio-repo`

## First message for the new chat
> Read `C:\Sample Code\reports\handoffs-2026-10-28\GEO-STUDIO.md` and the memory files it lists. Fetch the repo and confirm the branch heads before changing anything.
