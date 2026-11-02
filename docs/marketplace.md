# Moodle Marketplace listing

Text and checklist for the Course Assistant (aiplacement_assistant) listing. This folder is not part of the release ZIP.

## Name

Course Assistant

## Short description

An AI chat assistant inside Moodle that answers questions about deadlines, grades, submissions, activity and forums, using the AI provider your site already has configured.

## Full description

Course Assistant adds a chat panel to every Moodle page. Students, teachers and managers ask questions in plain language and get answers built from live Moodle data, with links to the relevant pages.

It is an AI placement: every request goes through the Moodle AI subsystem, so it uses whichever AI provider the site administrator has configured (for example OpenAI, Azure OpenAI or Ollama). No API keys or external accounts come with the plugin.

What it can answer:

- Students: what is due soon, my grade in a course, my unfinished activities.
- Teachers and managers: who has not submitted an assignment, who has not been active recently, why a user cannot see a course.
- Everyone: a summary of a forum they can read, and help with the current page.

Each answer is built from Moodle's own data and respects the user's capabilities. Lists of people, grades and dates are rendered by Moodle and are never sent to the AI; the AI only receives non-personal facts such as counts and course names. Forum summaries replace author names with placeholders before the posts are sent. Conversations are kept for a configurable number of days and are covered by the Privacy API.

The panel is hidden while a quiz is being attempted and on secure layouts.

Requirements:

- Moodle 4.5 or 5.2.
- An AI provider with the "Generate text" action enabled, and this placement enabled under Site administration > General > AI > AI placements.
- Users must accept the Moodle AI usage policy before first use.

Settings: hourly request limit per user (default 30) and conversation retention in days (default 30, 0 keeps no history).

Answer quality depends on the model. Small local models (3 to 4 billion parameters) sometimes pick the wrong kind of question; larger hosted models work better.

## Listing fields

| Field | Value |
|---|---|
| Plugin type | AI placement (aiplacement) |
| Component | aiplacement_assistant |
| Price | Free |
| Licence | GNU GPL v3 or later |
| Supported versions | 4.5, 5.2 |
| Source control URL | https://github.com/SaadRahman01/moodle-aiplacement_assistant |
| Bug tracker URL | https://github.com/SaadRahman01/moodle-aiplacement_assistant/issues |
| Documentation URL | https://github.com/SaadRahman01/moodle-aiplacement_assistant#readme |
| Demo credentials | Not needed: reviewers can use any AI provider supported by Moodle core |

## Screenshots

In `docs/screenshots/`:

1. `01-panel.png`: the panel on a course page.
2. `02-grade.png`: a student asking for their grade.
3. `03-forum-summary.png`: a forum summary.

These were taken on a development site, with the performance footer showing; retake them with debugging display off and a larger model before submitting. Add a teacher screenshot (for example "Who has not submitted Essay One?") once the answer text is right with the model you use.

## Before submitting

1. Done: public repository at https://github.com/SaadRahman01/moodle-aiplacement_assistant with Issues on.
2. Check that the GitHub Actions CI run is green.
3. Build the ZIP with `.github/scripts/build-zip.sh`.
4. Sign in at marketplace.moodle.com with your Moodle.org email, choose "Submit a plugin", pick Free, upload the ZIP, then fill in the fields and screenshots above.
5. Read the Marketplace "Plugin submission guidelines" (sign-in needed) for any rules not covered here.
