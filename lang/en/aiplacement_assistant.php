<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for aiplacement_assistant.
 *
 * @package    aiplacement_assistant
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['assistant:use'] = 'Use the Moodle Assistant';
$string['cachedef_ratelimit'] = 'Assistant rate limit counters';
$string['pluginname'] = 'Moodle Assistant';
$string['privacy:metadata:aiplacement_assistant_msg'] = 'Conversation messages exchanged with the Moodle Assistant.';
$string['privacy:metadata:aiplacement_assistant_msg:contextid'] = 'The context in which the message was sent.';
$string['privacy:metadata:aiplacement_assistant_msg:intent'] = 'The kind of question the assistant identified.';
$string['privacy:metadata:aiplacement_assistant_msg:message'] = 'The text of the message.';
$string['privacy:metadata:aiplacement_assistant_msg:role'] = 'Whether the message is from the user or the assistant.';
$string['privacy:metadata:aiplacement_assistant_msg:timecreated'] = 'The time the message was created.';
$string['privacy:metadata:aiplacement_assistant_msg:userid'] = 'The ID of the user who had the conversation.';
$string['privacy:metadata:aiprovider'] = 'The user\'s question and a description of the current page are sent to the site\'s configured AI provider.';
$string['privacy:metadata:aiprovider:message'] = 'The text of the user\'s question.';
$string['privacy:metadata:aiprovider:pagehelp'] = 'Help text describing the page the user is on.';
$string['ratelimit'] = 'Rate limit';
$string['ratelimit_desc'] = 'Maximum number of assistant requests each user may make per hour.';
$string['retentiondays'] = 'Conversation retention (days)';
$string['retentiondays_desc'] = 'Number of days assistant conversations are kept before being deleted. 0 means keep them only for the current session; any stored history is deleted by the next cleanup run.';
$string['task_cleanup_history'] = 'Delete old assistant conversation history';
