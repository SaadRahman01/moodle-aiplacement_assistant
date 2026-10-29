# Moodle Assistant (aiplacement_assistant)

An AI placement plugin for Moodle that provides an in-Moodle chat assistant.
It plugs into the Moodle AI subsystem, so all requests are routed through the
AI provider your site administrator has configured.

## Requirements

- Moodle 4.5 or later (supports 4.5 and 5.2).
- An AI provider configured in Moodle with the `generate_text` action enabled
  for this placement (Site administration > General > AI > AI placements).

## Installation

Copy (or clone) this repository to `ai/placement/assistant` in your Moodle
codebase (`public/ai/placement/assistant` on Moodle 5.1 and later), then visit Site administration > Notifications to complete the
install.

## Settings

- Rate limit (default 30): maximum assistant requests per user per hour.
- Conversation retention (default 30): days before conversations are deleted.

## Privacy

Conversation messages are stored per user in `aiplacement_assistant_msg` for
the retention period (0 = not stored; kept only for the browser session) and
are covered by the Privacy API (export and deletion).

The following is sent to the AI provider configured by your site
administrator; review that provider's privacy policy:

- the user's question and recent conversation history (user and assistant
  turns; real names restored into forum summaries are never stored or resent);
- help text for the current page;
- non-personal facts used to phrase answers (course, activity and forum names,
  counts);
- for forum summaries, the text of the forum posts the user can see, with
  author names and email addresses replaced by placeholders. The post content
  itself, which other people wrote, is sent.

Other people's names, grades and dates shown in result rows are rendered by
Moodle and are not sent to the AI. Core AI keeps the prompts it sends in its
own action log, covered by core's privacy provider.

## Known limitations

Smoke-tested on 2026-10-28 against local Ollama models (qwen2.5:3b-instruct, qwen3:4b) with a throwaway course, one question per intent, as student, teacher and admin. "Correct" means the intent that was expected was chosen by the model.

### Provider routes

- Moodle 4.5 has no Ollama provider. The free route is the core OpenAI provider pointed at Ollama's OpenAI-compatible endpoint: set the API key to any non-empty value, `Generate text` endpoint to `http://<host>:11434/v1/chat/completions` and the model to your Ollama model. This worked in testing.
- Moodle 5.x (tested on 5.2) has the `aiprovider_ollama` provider: create an instance with the Ollama base URL (for example `http://<host>:11434`) and your model.
- Moodle blocks private network addresses for outgoing requests by default. Under Site administration > General > Security > HTTP security, "cURL blocked hosts list" contains 127.0.0.0/8, 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12 and similar. A self-hosted Ollama on the school network is therefore blocked until you allow it there with the narrowest entry possible (for example by splitting the blocked range so that only the Ollama server's single address is excluded), and add its port (11434 by default) to "cURL allowed ports list". Do not clear the blocked hosts list.

### Small-model reliability

- The router asks the model for a JSON intent. Small models (3B to 4B) often pick `general_help` for questions that need a specific intent. If a reply cannot be parsed, the assistant shows a menu of the intents available to the user instead of guessing (this did not happen in the smoke tests; every miss fell back to `general_help`). Use a larger model for better routing.
- Answers written from facts by small models are sometimes clumsy or imprecise (for example "no assignments due this week" while the facts list one).

### Smoke results (expected intent routed / 8 questions per role)

| Moodle | Model | Student | Teacher | Admin | Misrouted or poor (all roles) |
|---|---|---|---|---|---|
| 4.5 (OpenAI provider to Ollama) | qwen2.5:3b-instruct | 2/8 | 4/8 | 4/8 | `forum_summary` and `access_check` always answered by `general_help` ("not sure"); student `my_grade` and `unfinished_activities` misrouted to `general_help` |
| 5.2 (`aiprovider_ollama`) | qwen2.5:3b-instruct | 4/8 | 4/8 | 5/8 | `forum_summary` always misrouted to `general_help`; `access_check` misrouted for student and teacher; `my_grade` and `unfinished_activities` routed for the student only |
| 5.2 | qwen3:4b | not completed | | | Thinking-mode model: one request took over 30 minutes through the 5.2 provider, run abandoned |

Notes: the counts treat any question where the expected intent was not chosen as a miss, including intents a role is not allowed to use (for example `not_submitted` and `inactive_users` for a student, `my_grade` for a teacher), which fall back to `general_help` by design. `due_this_week`, `not_submitted` (teacher/admin), `inactive_users` (teacher/admin) and `general_help` were routed correctly by qwen2.5:3b-instruct on both versions. Facts-to-text answers were sometimes inaccurate (for example "2 out of 30 days" for inactive users, or "no assignments due" with one listed). On 4.5 the student `my_grade` and `unfinished_activities` questions were misrouted while the same questions routed on 5.2; the cause was not investigated.

These results predate later fixes and have not been re-run: the router prompt was rewritten (classify only, commands such as "Summarise ..." are normal requests, one example per intent, `general_help` only if nothing else fits); names such as "this course" or "this forum" now mean the current course or activity (the 5.2 student `my_grade` and `unfinished_activities` questions were routed correctly but found nothing for that reason); and answers with nothing to phrase now show the fixed text instead of asking the model. The `access_check` smoke question ("Why can't I access the Essay One assignment?") was also outside that intent's scope, which covers another user and a course, for example "Why can't Sue Slacker see Smoke Test Course?".

## License

GNU GPL v3 or later.
