@aiplacement @aiplacement_assistant @javascript @accessibility
Feature: Moodle Assistant chat panel
  In order to get help while using Moodle
  As a student
  I need to open the assistant from any page, except while taking an exam

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And a test AI provider is configured for the Moodle Assistant

  Scenario: A student opens the assistant, accepts the AI policy and sees the input box
    When I am on the "C1" "Course" page logged in as "student1"
    Then "Open Moodle Assistant" "button" should exist
    And I press "Open Moodle Assistant"
    And I should see "AI usage policy"
    And "Your question" "field" should not be visible
    And I press "Accept and continue"
    And "Your question" "field" should be visible
    And "Send" "button" should be visible
    And the page should meet accessibility standards
    And I press "Send"
    And I should see "Please type a message."
    And I press "Close Moodle Assistant"
    And "Your question" "field" should not be visible

  Scenario: The assistant works with the Classic theme
    Given the following config values are set as admin:
      | theme | classic |
    When I am on the "C1" "Course" page logged in as "student1"
    And I press "Open Moodle Assistant"
    And I press "Accept and continue"
    Then "Your question" "field" should be visible

  Scenario: The assistant is not shown while attempting a quiz
    Given the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    Then "Open Moodle Assistant" "button" should exist
    And I press "Attempt quiz"
    And I should see "First question"
    And "Open Moodle Assistant" "button" should not exist

  Scenario: The assistant is not shown when the placement is disabled
    Given the following config values are set as admin:
      | enabled | 0 | aiplacement_assistant |
    When I am on the "C1" "Course" page logged in as "student1"
    Then "Open Moodle Assistant" "button" should not exist
