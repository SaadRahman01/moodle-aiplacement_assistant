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

$string['all_done'] = 'You have no unfinished activities in {$a}.';
$string['assistant:use'] = 'Use the Moodle Assistant';
$string['cachedef_ratelimit'] = 'Assistant rate limit counters';
$string['check_category_visible'] = 'Course category visible';
$string['check_course_visible'] = 'Course visible';
$string['check_detail_expired'] = 'The enrolment has ended.';
$string['check_detail_instancedisabled'] = 'The enrolment method is disabled.';
$string['check_detail_notstarted'] = 'The enrolment has not started yet.';
$string['check_detail_suspended'] = 'The enrolment is suspended.';
$string['check_enrol_instance_enabled'] = 'Enrolment method enabled';
$string['check_enrolled'] = 'Enrolled in the course';
$string['check_enrolment_active'] = 'Enrolment active';
$string['check_has_role'] = 'Has a role in the course';
$string['check_history'] = 'I checked access to {$a->course} and found {$a->problems} possible problem(s).';
$string['check_ok'] = 'OK';
$string['check_problem'] = 'Problem';
$string['check_summary_ok'] = 'I found no problems with {$a->user} accessing {$a->course}.';
$string['check_summary_problems'] = 'I found {$a->problems} possible problem(s) with {$a->user} accessing {$a->course}.';
$string['check_user_suspended'] = 'User account active';
$string['closeassistant'] = 'Close Moodle Assistant';
$string['completion_off'] = 'Activity completion is not enabled in that course, so I cannot tell what is unfinished.';
$string['due_count'] = 'You have {$a->count} item(s) due in the next {$a->days} days.';
$string['due_none'] = 'Nothing is due in the next {$a->days} days.';
$string['error_aifailed'] = 'The assistant could not reach the AI service. Please try again.';
$string['error_generic'] = 'Something went wrong. Please try again.';
$string['error_messageempty'] = 'Please type a message.';
$string['error_messagetoolong'] = 'Your message is too long. Please shorten it to 2000 characters or fewer.';
$string['error_notavailable'] = 'The assistant is not available here.';
$string['error_policynotaccepted'] = 'Please accept the AI policy before using the assistant.';
$string['error_ratelimited'] = 'You have reached the hourly limit for assistant requests. Please try again later.';
$string['fallbackmenu'] = 'I was not sure what you meant. What would you like help with?';
$string['forum_empty'] = 'There are no posts you can see in {$a}.';
$string['grade_found'] = 'Here is your grade in {$a}.';
$string['grade_hidden'] = 'Your grade is not currently visible.';
$string['help_docs'] = 'Moodle documentation';
$string['help_notsure'] = 'I am not sure about that one, so I would rather not guess. The Moodle documentation should help.';
$string['inactive_count'] = '{$a->count} participant(s) have not accessed this course in the last {$a->days} days.';
$string['inactive_none'] = 'Everyone has accessed this course in the last {$a->days} days.';
$string['intent_access_check'] = 'Why a user cannot see a course';
$string['intent_due_this_week'] = 'What is due soon';
$string['intent_forum_summary'] = 'Summarise a forum';
$string['intent_general_help'] = 'Get help with this page';
$string['intent_inactive_users'] = 'Who has not been active recently';
$string['intent_my_grade'] = 'My grade in a course';
$string['intent_not_submitted'] = 'Who has not submitted an assignment';
$string['intent_unfinished_activities'] = 'My unfinished activities';
$string['lastaccess_hidden'] = 'Last access information is hidden on this site, so I cannot tell who has been inactive.';
$string['messagelabel'] = 'Your question';
$string['messageplaceholder'] = 'Ask a question about Moodle';
$string['messages'] = 'Conversation';
$string['not_submitted_count'] = '{$a->missing} of {$a->total} students have not submitted {$a->activity}.';
$string['not_submitted_none'] = 'Everyone you can see has submitted {$a}.';
$string['notfound_activity'] = 'I could not find an activity matching that which you can access.';
$string['notfound_course'] = 'I could not find a course of yours matching that.';
$string['notfound_user'] = 'I could not find a user matching that.';
$string['openassistant'] = 'Open Moodle Assistant';
$string['pick_activity'] = 'Which activity did you mean?';
$string['pick_course'] = 'Which course did you mean?';
$string['pick_user'] = 'Which user did you mean?';
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
$string['role_assistant'] = 'Moodle Assistant';
$string['role_user'] = 'You';
$string['send'] = 'Send';
$string['status_draft'] = 'Draft (not submitted)';
$string['status_none'] = 'No submission';
$string['summary_failed'] = 'Sorry, I could not summarise this forum right now. Please try again later.';
$string['task_cleanup_history'] = 'Delete old assistant conversation history';
$string['thinking'] = 'Thinking...';
$string['unfinished_count'] = 'You have {$a->count} unfinished activities in {$a->course}.';
