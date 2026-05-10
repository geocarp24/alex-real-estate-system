# Bug Report — Claude Code on Web (claude.ai/code)
**Date:** 2026-05-10
**Reporter (GitHub):** `geocarp24`
**Environment:** Claude Code on the Web (claude.ai/code)
**Model:** Claude Opus 4.7 (1M context)
**Working repo (whitelisted):** `geocarp24/alex-real-estate-system`
**Working branch:** `claude/check-session-context-7Ea4B`
**Target repo (blocked):** `geocarp24/investoros-web`

---

## TL;DR

Two compounding bugs in Claude Code on the web are fully blocking multi-repo work:

1. **The session sandbox whitelist is hard-locked to a single repository at session start.** There is no UI or in-session command to add a second repo. Both the local git proxy and the GitHub MCP server enforce the whitelist server-side.
2. **Opening a second Claude Code session in a new window/tab freezes** (the only workaround for #1 is unusable).
3. **The `/feedback` slash command is not available in this environment**, so this report cannot be submitted through the in-app channel and is being filed manually.

Net effect: an entire built-and-committed website (InvestorOS mockups, 4 SVG avatars, team preview page, README, 6 rendered PNGs) is sitting in a local git clone at `/home/user/investoros-web` and cannot be pushed to its GitHub remote.

---

## 1. Environment & repro context

- **Surface:** Claude Code on the web (claude.ai/code), not the desktop app, not the CLI.
- **Sandbox:** Anthropic-managed cloud sandbox. Linux 6.18.5.
- **Git access:** routed through a local proxy on `127.0.0.1:<random-high-port>` injected into the remote URL automatically. The port changes between sessions (observed: `35547`, `36691`, `37151`, `35723`).
- **GitHub access:** via the `mcp__github__*` MCP server (Anthropic-hosted, routes through Anthropic's CCR infrastructure based on prior tool error surfaces).
- **Auth identity:** GitHub user `geocarp24` (id 262872506) — owner of both `alex-real-estate-system` AND `investoros-web`.

**Both repos are owned by the same authenticated GitHub user.** The block is not a permissions issue at the GitHub layer — it is enforced by the Claude Code sandbox.

---

## 2. Issue #1 — Whitelist locked to one repository per session

### What I expect

If a user is authenticated with a GitHub account, the sandbox should allow access to any of that user's owned repos within the session, OR provide a mechanism (UI, slash command, or settings panel) to add a second whitelisted repo without restarting the session.

### What actually happens

The "Allowed repositories" list is injected into the system prompt at session launch and **cannot be modified mid-session**, even by the model itself. The whitelist for the current session is exactly one entry:

```
Allowed repositories: geocarp24/alex-real-estate-system
```

Any access to a different repo owned by the same user is blocked at two layers:

#### Layer A — Local git proxy (HTTP 502)

```bash
$ curl -s -o /dev/null -w "%{http_code}\n" \
    http://local_proxy@127.0.0.1:35723/git/geocarp24/investoros-web/info/refs?service=git-upload-pack
502

$ curl -s -o /dev/null -w "%{http_code}\n" \
    http://local_proxy@127.0.0.1:35723/git/geocarp24/alex-real-estate-system/info/refs?service=git-upload-pack
200
```

The proxy itself is reachable (it returns 200 for the whitelisted repo), but returns 502 for any non-whitelisted repo, including repos owned by the same user. Prior sessions observed the proxy also returning the explicit message `"repository not authorized"`.

#### Layer B — GitHub MCP tools

```
mcp__github__list_branches(owner="geocarp24", repo="investoros-web")
→ "Access denied: repository 'geocarp24/investoros-web' is not configured
   for this session. Allowed repositories: geocarp24/alex-real-estate-system"
```

`mcp__github__search_repositories` *can* list the repo (it appears in the user's public repo list), but any *read or write* operation against it is denied.

### Repro

1. Start a Claude Code web session in repo A.
2. Ask the agent to clone, push, list branches, or read a file in repo B (owned by the same authenticated GitHub user, not in the session whitelist).
3. Observe HTTP 502 from the local git proxy and "Access denied" from the GitHub MCP server.

### Important nuance — `create_repository` works but is then unusable

`mcp__github__create_repository` **does** successfully create a new repo (the agent created `geocarp24/investoros-web` at 2026-05-10 03:53:12 UTC). However, the newly created repo is **not automatically added to the session whitelist**, so no further operations against it can be performed in the same session. This is a confusing partial-success state: the repo exists, has a clone URL, but is unusable.

### What was tried (and failed)

| # | Attempted workaround | Result |
|---|---|---|
| 1 | `mcp__github__list_branches` on investoros-web | "Access denied: not configured for this session" |
| 2 | `mcp__github__get_file_contents` on investoros-web | Same denial |
| 3 | `git clone http://local_proxy@127.0.0.1:.../investoros-web` | Connection refused / HTTP 502 |
| 4 | Manually edit `.git/config` to point a clone at the proxy URL | Proxy returns "repository not authorized" |
| 5 | Try plain `https://github.com/geocarp24/investoros-web.git` (bypass proxy) | Fails — no GitHub HTTPS credentials available in the sandbox; the sandbox blocks egress to api.github.com / github.com except via the proxy |
| 6 | `mcp__github__push_files` directly to investoros-web | Same access-denied error |
| 7 | `mcp__github__create_or_update_file` to write directly via the MCP path | Same access-denied error |
| 8 | Use `gh` CLI | Not installed and not allowed in the sandbox |
| 9 | Edit / regenerate the system prompt mid-session to add the repo | Not possible — the system prompt is read-only to the model |

### Why this is high impact

For multi-repo workflows (porting designs from one repo to another, deploying mockups to a separate web repo, copying CI configs between projects, etc.), the user is forced to:

- Either end the current session and start a new one with only repo B (losing the in-flight work context in repo A's session), or
- Open a parallel window with repo B (see Issue #2 below — this freezes).

Neither option is usable.

---

## 3. Issue #2 — Opening a second Claude Code web session freezes the app

### What happens

When the user attempts to open a second Claude Code session in a parallel browser window/tab (the natural workaround for Issue #1, so they can authorize a *different* repo in that window), the second session **never finishes loading**. The UI hangs on the loading state. Re-attempting does not recover; only closing both windows and starting fresh restores any usable state.

### Why this matters

This bug, on its own, would be a minor inconvenience. Combined with Issue #1, it is a hard block: there is **no path to working with two repos in one calendar day** without abandoning all in-flight session state.

### What was tried

- Closing and reopening the new tab → still freezes.
- Hard refresh (Cmd/Ctrl+Shift+R) → still freezes.
- Different browsers (the user did not explicitly verify all browsers, but the freeze was observed consistently in the primary one).

The user's exact words: *"no puedo abrir otra ventana con otra sesión porque se queda pegado"* — "I can't open another window with another session because it freezes."

---

## 4. Issue #3 — `/feedback` slash command unavailable

When the user typed `/feedback` (with the full pre-written bug report as the argument) to submit this report through the official channel, Claude Code responded:

```
/feedback isn't available in this environment.
```

`/doctor` is also unavailable (same message). This means there is no in-app channel to submit this bug report. The user is filing it manually — please consider enabling at minimum `/feedback` on the web surface so users in this exact situation can report problems without external workarounds.

---

## 5. Additional rough edges observed during the session (lower priority but related)

These came up while trying to work around the main blockers, and may be useful signal:

1. **Initial git commits in the sandbox were blocked by the code-sign server**
   First `git commit` attempts returned HTTP 400 from the code-sign service (`"missing source"`). A retry succeeded but produced a commit with the placeholder message `"test"`, which then had to be amended. The signing setup appears to be a `/tmp/code-sign` binary used via SSH GPG format — this is opaque to the user and the first failure mode was not actionable.

2. **`auto-save` hooks race with explicit commits**
   The sandbox has hooks that auto-commit `memoria_*.md` and `agents/shared_conversation.json` with messages like `auto: save 2026-05-10 04:45:30`. When the user / agent runs an explicit `git commit` immediately after, it often reports `nothing to commit` because the hook already grabbed the change. Then a subsequent `git push` is rejected because the local HEAD is at `SHA-X` but remote expects `SHA-Y`. The documented workaround is `git pull --rebase origin <branch>`, but this is non-obvious and surprises every new session.

3. **The git proxy port changes between sessions**
   The remote URL has the form `http://local_proxy@127.0.0.1:<port>/...` where `<port>` is randomized per session (observed `35547`, `36691`, `37151`, `35723`). A `SessionStart` hook updates the remote URL automatically, but if anything hardcodes the old port (a script, a config), it silently breaks until manually re-pointed.

4. **Stop hook enforces "clean git state" before session end**
   The session refuses to cleanly end if `git status` shows uncommitted changes. This is mostly a good behavior, but combined with the auto-save race (item 2) it occasionally creates a "you must pull and rebase before you can stop the session" trap that's hard to escape without manually running git commands.

---

## 6. Concrete impact on this user (geocarp24 / Jorge)

- The user spent a session building the InvestorOS mockup site:
  - 4 robot SVG avatars (Alex, Scout, Max, Vera)
  - 5 HTML mockup pages (landing, dashboard, agent profile, team preview, etc.) with shared CSS
  - 6 PNG renders via Playwright/Chromium
  - A README documenting the 11-agent roster
- All committed locally to `/home/user/investoros-web` with an amended commit message.
- **Cannot push** because of Issue #1.
- **Cannot open a second window** to authorize the target repo because of Issue #2.
- **Cannot file `/feedback`** in-app because of Issue #3.

The work is at risk of being lost when the sandbox is reaped.

---

## 7. Suggested fixes (in priority order)

1. **Multi-repo whitelist at session start.** When the user launches a session, let them multi-select repos (or default to "all repos owned by the authenticated user" with a per-repo allow/deny). This is the single most impactful fix.
2. **An `/add-repo` slash command (or settings panel)** that updates the whitelist live, without requiring a session restart. The proxy and MCP server should re-read the whitelist on change.
3. **Fix the second-session freeze on web** so it's at least usable as a manual workaround for #1 until #1 and #2 are addressed.
4. **Make `create_repository` auto-add the new repo to the session whitelist.** It is bizarre that the agent can create a repo it then cannot use.
5. **Enable `/feedback` on the web surface** so users in this exact situation have a real reporting channel.
6. **Document the auto-save hook + commit race** in onboarding docs or a session start banner, with the recommended `git pull --rebase` workflow.
7. **Surface a clearer error from the code-sign server** on first signing failure — `"missing source"` is not actionable.

---

## 8. Diagnostic data captured

```
Session: 2026-05-10 ~22:28 UTC
User: geocarp24 (id 262872506)
Repos owned by user (from mcp__github__search_repositories):
  - geocarp24/alex-real-estate-system  (allowed)
  - geocarp24/investoros-web           (blocked)
  - geocarp24/pinnacle-agent-memory    (untested, would be blocked)
  - geocarp24/pinnacle-tools           (private, would be blocked)
  - geocarp24/geo-carpentry            (private, would be blocked)
  - geocarp24/geo-budget-pro           (private, would be blocked)

Active git remote:
  origin → http://local_proxy@127.0.0.1:35723/git/geocarp24/alex-real-estate-system

Proxy health:
  alex-real-estate-system → 200 OK
  investoros-web          → 502

MCP error verbatim:
  "Access denied: repository "geocarp24/investoros-web" is not configured
   for this session. Allowed repositories: geocarp24/alex-real-estate-system"

In-app commands attempted and unavailable:
  /feedback → "isn't available in this environment"
  /doctor   → "isn't available in this environment"
```

---

## 9. How to contact / clarify

The user (Jorge / `geocarp24`) is bilingual ES/EN and happy to provide:

- Browser, OS, and network specifics for the freeze repro
- A screen recording of the second-session freeze
- Session IDs from both the working session and the frozen second session
- Any logs the sandbox exposes (if instructions are provided on how to retrieve them — currently he doesn't see any "export logs" option)

Best path is to reply to whichever channel this bug report is filed through (in-app ticket, email to support@anthropic.com, or via Discord/community).

---

**End of report.**
