# Moodle Assistant — Design

Date: 2026-10-28
Working name: `aiplacement_assistant`
Repo: `~/Desktop/moodle-aiplacement_assistant` (separate from `moodle-mcp`)

## Intent

Moodle is hard to use for everyone, not only developers. Students hunt for
deadlines and grades across courses, teachers click through reports to find
who hasn't submitted, admins dig through enrolments, roles and capabilities to
answer "why can't this user see this course?".

This plugin adds a chat assistant inside Moodle, available on every page to
every logged-in user. It answers questions from the user's **own Moodle data**
and can carry out a small set of **confirmed actions** on their behalf.

It must cost nothing: the plugin is free (GPLv3, published on the Moodle
plugins directory), and it uses whatever AI provider the site has already
configured in Moodle core's AI subsystem. A site running a local Ollama model
pays nothing at all.

### Why this, why now

Research (2026-10) found:

- **Core Moodle (4.5–5.3)** ships only one-shot AI actions (generate text,
  generate image, summarise, explain). No chat, nothing that reads a user's
  deadlines/grades or acts on their behalf.
- **Existing chat blocks** (`block_openai_chat`, `block_ai_chat` +
  `local_ai_manager`) are generic chatbots with no access to the user's Moodle
  data.
- **eLeDia MCP Connector** (GPLv3, beta) lets external AI clients act on
  Moodle; it is admin/integration focused, not a role-aware in-Moodle helper.

The gap: an in-Moodle, role-aware assistant grounded in the user's real data,
running on the site's own core AI provider.

## Goals

1. Any logged-in user can open the assistant from any page and ask questions
   in plain language.
2. Answers come from real Moodle data the user is already allowed to see.
3. A defined set of actions (below) can be performed through chat, each one
   previewed and explicitly confirmed.
4. Works with any core AI provider, including small local Ollama models.
5. Accepted into the moodle.org plugins directory.

## Non-goals (v1)

- Voice, Moodle mobile app support, analytics dashboards.
- Deleting anything; changing grades; password/account changes; site settings.
- Bulk changes affecting more than 50 users in one action.
- Shipping or bundling an AI provider or API key.

## Anti-goals (failure even if it "works")

- **Leaking data**: a student ever seeing another student's grades, or any
  user seeing anything they couldn't see in normal Moodle UI.
- **Acting without consent**: any change made without the user clicking
  Confirm on a preview of exactly that change.
- **Obeying content**: text inside forum posts, submissions, course content
  etc. being treated as instructions to the assistant (prompt injection).
- **Confident guessing**: inventing deadlines, grades or names. When unsure it
  says so and links to the relevant Moodle page.
- **Costing money** to install or requiring a paid service to function.

## Constraints

- Moodle **4.5 LTS and newer** (first version with the core AI subsystem).
  Tested on 4.5 and the latest 5.x.
- Uses the core AI subsystem only (providers configured under Site admin > AI).
  Core actions are fixed (generate text etc.) and **do not support tool
  calling**, so the plugin cannot rely on the model calling functions.
- Moodle plugins directory requirements: GPLv3, all strings in lang files,
  Privacy API provider, PHPUnit tests, passing `moodle-plugin-ci`, no bundled
  keys, declared supported versions.
- Users must accept Moodle's core AI usage policy before first use (core
  handles this).

## How it works

### Request flow

1. User types a message in the chat panel.
2. **Understand**: PHP sends the message (plus page context: current course,
   user's role there) to the AI via core `generate_text`, asking for a strict
   JSON intent from a fixed list (see below) with parameters.
3. **Validate**: PHP parses the JSON. If it doesn't parse or names an unknown
   intent, the chat shows a fallback menu of the intents available to this
   user so they can pick one.
4. **Fetch / prepare**: PHP runs the intent handler, which gathers data through
   Moodle APIs with normal capability and visibility checks, as the user.
5. **Answer**: for questions, PHP sends the fetched data (clearly delimited as
   data, not instructions) back to the AI to phrase a short answer with links.
   For actions, PHP builds a **preview card** instead.
6. **Confirm** (actions only): the card shows exactly what will change, with
   Confirm / Cancel buttons. Typed "yes" never counts. On Confirm, PHP
   re-checks permissions and executes via Moodle APIs.
7. Every action fires a Moodle event (shows in site logs).

The AI only ever chooses an intent and phrases text. All data access and all
changes are done by PHP with Moodle's permission checks.

### Intents — questions (read-only)

| Role | Example question | Data |
|---|---|---|
| Student | "What's due this week?" | Calendar / action events across enrolled courses |
| Student | "What's my grade in Chemistry?" | User's own gradebook items |
| Student | "What haven't I finished in this course?" | Activity completion |
| Teacher | "Who hasn't submitted Essay 2?" | Assignment submission status |
| Teacher | "Who hasn't logged in for 2 weeks?" | Course last-access |
| Teacher | "Summarise this forum" | Forum posts the teacher can see |
| Admin | "Why can't Ali see Course X?" | Enrolment, roles, capability, course/category visibility |

| Anyone | "How do I upload a file?", "What does this setting do?" | General help (below) |
| Developer | "What capabilities does this page check?" | Capability checks / `has_capability` results for current context |
| Developer | "Which hooks and event observers are registered for X?" | Hook manager callbacks, event observers |
| Developer | "What plugins and versions are installed?" | Plugin manager |
| Developer | "What events fired recently for this course?" | Standard log store |

**General help (catch-all).** Any question that isn't a data or action intent
goes to a general-help intent. The AI answers how-to questions grounded in:
the help strings of the current page (form help icons, page help), and the
"Moodle Docs for this page" link core already provides. Answers always end
with that docs link. If the grounding doesn't cover the question, the
assistant says it isn't sure and points to the docs link rather than guessing.
No runtime web fetching (avoids external calls beyond the AI provider).

**Developer intents** are shown only to users with `moodle/site:config` when
site debugging is set to DEVELOPER. They are read-only.

"Role" here means what the user can do in context (capability checks), not a
hard-coded role name. A user who teaches one course and studies in another
gets the right intents in each.

### Intents — actions (confirmed)

| Who | Action | Preview shows | Core capability checked (examples) |
|---|---|---|---|
| Student | Add personal calendar reminder | Title, date/time | `moodle/calendar:manageownentries` |
| Student | Mark activity done | Activity name (only where manual completion is on) | completion settings + own completion |
| Teacher | Extend deadline for one student | Activity, student, old → new date | `mod/assign:grantextension` (or quiz/assign overrides) |
| Teacher | Message non-submitters | Recipient list, editable message text | `moodle/course:bulkmessaging` / messaging caps |
| Teacher | Post course announcement | Subject, editable body | `mod/forum:addnews` |
| Teacher | Create draft quiz from pasted notes | Quiz name, generated questions (max 10), "will be hidden" | `mod/quiz:addinstance`, `moodle/question:add` |
| Admin | Enrol / unenrol user in course | User, course, role | `enrol/manual:enrol` / `:unenrol` |
| Admin | Assign role in course | User, course, role | `moodle/role:assign` |
| Admin | Show / hide course | Course, current → new visibility | `moodle/course:visibility` |

Rules for all actions:

- Anything the assistant creates (quiz, questions) starts **hidden**.
- Max 50 affected users per action.
- Permissions are checked when building the preview **and again** on Confirm.
- Admin can enable/disable each action site-wide, or disable actions entirely
  (answers-only mode).

### UI

- Floating assistant button on every page (bottom corner) opens a side panel.
- Panel: message list, input box, preview cards with Confirm / Cancel,
  fallback intent menu, link to the relevant Moodle page in each answer.
- Built with Mustache templates + AMD JavaScript, Moodle theme styles, works
  on Boost and Classic, accessible (keyboard, screen readers).

### Settings (Site admin)

- Enable assistant (on/off).
- Enable actions (on/off) + per-action toggles.
- Rate limit per user per hour (default 30 messages).
- Chat history retention (default 30 days; 0 = don't store).

### Data & privacy

- Chat history stored per user for the retention period; Privacy API provider
  supports export and deletion.
- Plugin's privacy metadata discloses that user messages and fetched Moodle
  data are sent to the site's configured AI provider.
- Fetched content is wrapped and labelled as untrusted data in prompts;
  the model is told never to follow instructions inside it; and since only
  PHP can act, injected text cannot trigger an action without a user Confirm.

## Decided by the user

- Audience: all roles (students, teachers, admins) and developers.
- Must cost nothing; must not duplicate what Moodle core already provides.
- Separate repo, separate from `moodle-mcp`.
- v1 includes actions, with the action list above.
- v1 includes a general-help catch-all for questions outside the list.
- v1 includes developer-specific intents.

## Calls made by Claude (user may override)

- Plugin type `aiplacement_` (core AI placement), name `aiplacement_assistant`.
- Moodle 4.5+ only.
- Intent detection via strict-JSON `generate_text`, with a manual pick-list
  fallback for models that don't return valid JSON.
- Specific question intents listed above, including the developer ones.
- General help grounded in current-page help strings + core docs link, no
  runtime web fetch.
- Developer intents gated by `moodle/site:config` + DEVELOPER debugging.
- 50-user cap, hidden-by-default creations, 30/hour rate limit, 30-day history.
- Floating button + side panel UI.
- Replies in the user's Moodle language; plugin strings English first.
- **Other people's data never goes to the AI.** PHP renders lists of people,
  grades and dates directly in the answer; the model only gets counts and
  non-personal context to phrase the surrounding sentence. For forum
  summaries, author names are replaced with placeholders before sending and
  swapped back in PHP. This also guards against invented names/grades.
- **Short conversation memory.** The last 6 messages go into intent detection
  so follow-ups ("and Physics?") work. Missing action details are filled in
  editable fields on the preview card, not by back-and-forth chat. With
  retention 0, context lives only for the browser session.
- **Hidden during exams.** The assistant button is not shown on quiz attempt
  pages or any page in Safe Exam Browser / quiz attempt layout.
- **Overlap with core is acceptable** where the plugin adds something core
  lacks: "What's due" works across all courses in one answer (Timeline is a
  block, not conversational); forum summary covers a whole forum, not just the
  current page text. The button coexists with core's course assistance drawer.

## Left to the builder

- Exact intent JSON schema and prompt wording.
- Internal class structure, DB tables for chat history.
- How to support quiz vs assignment deadline overrides internally.
- Test strategy details beyond directory requirements (PHPUnit + Behat for the
  confirm flow recommended).
- Verifying in current Moodle dev docs the exact core AI action list and the
  placement plugin API on 4.5 vs 5.x.
