# Changelog

## 0.1.0 (2026-10-28)

- First alpha release of the Moodle Assistant AI placement (Moodle 4.5 and 5.2).
- In-Moodle chat drawer that routes every request through the core AI subsystem (`generate_text`).
- Intent router with eight built-in intents: due this week, my grade, unfinished activities, not submitted, inactive users, forum summary, access check, general help.
- Menu fallback when the model reply cannot be parsed as a valid intent.
- Capability, context and AI policy checks; per-user hourly rate limit and conversation retention settings.
- Untrusted-content delimiting in prompts and a compatibility layer for core AI API differences (4.5 static vs 5.x instance manager).
- Privacy provider, PHPUnit coverage and Moodle coding style compliance.
