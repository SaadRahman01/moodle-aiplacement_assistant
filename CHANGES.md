# Changelog

## 0.2.0 (2026-11-02)

- Renamed the display name to Course Assistant (the component stays aiplacement_assistant), to avoid use of the Moodle trademark.
- Maturity raised to beta.
- Fix "What is due soon" failing with a generic error in real requests (the calendar library was not loaded).
- General help: remove data block markers echoed by the model, and treat NOT_SURE anywhere in the reply as not sure.
- My grade: show the fixed text instead of an AI sentence, which claimed the grade was missing because the AI never sees it.
- Phrasing prompt: the facts are complete and their numbers must be used exactly; echoed markers are removed.
- Chat panel: load history before showing the input, so a quickly sent message is no longer shown above the history.

## 0.1.0 (2026-10-28)

- First alpha release of the Moodle Assistant AI placement (Moodle 4.5 and 5.2).
- In-Moodle chat drawer that routes every request through the core AI subsystem (`generate_text`).
- Intent router with eight built-in intents: due this week, my grade, unfinished activities, not submitted, inactive users, forum summary, access check, general help.
- Menu fallback when the model reply cannot be parsed as a valid intent.
- Capability, context and AI policy checks; per-user hourly rate limit and conversation retention settings.
- Untrusted-content delimiting in prompts and a compatibility layer for core AI API differences (4.5 static vs 5.x instance manager).
- Privacy provider, PHPUnit coverage and Moodle coding style compliance.
