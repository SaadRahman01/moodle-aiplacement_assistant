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
codebase, then visit Site administration > Notifications to complete the
install.

## Settings

- Rate limit (default 30): maximum assistant requests per user per hour.
- Conversation retention (default 30): days before conversations are deleted.

## Privacy

This early version stores no personal data. Prompts are sent to the AI provider
configured by your site administrator; review that provider's privacy policy.
The privacy provider will be updated as conversation storage is added.

## License

GNU GPL v3 or later.
