@mod @mod_task @core_completion @javascript
Feature: Task activity completion conditions
  In order to have Task participation count towards course completion
  As a teacher
  I can require students to respond, reply and react to complete the Task

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 1                | 1                        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activity" exists:
      | activity          | task                |
      | course            | C1                  |
      | name              | My Task             |
      | idnumber          | task1               |
      | intro             | What did you learn? |
      | completion        | 2                   |
      | completionrespond | 1                   |
      | completionreply   | 1                   |
      | completionreact   | 1                   |

  Scenario: A student who has not participated sees every condition as to-do
    When I am on the "My Task" "task activity" page logged in as student1
    Then "My Task" should have the "Add a response" completion condition
    And the "Add a response" completion condition of "My Task" is displayed as "todo"
    And the "Post a reply" completion condition of "My Task" is displayed as "todo"
    And the "Make a reaction" completion condition of "My Task" is displayed as "todo"

  Scenario: Responding, replying and reacting completes the conditions
    Given the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    And the following "mod_task > replies" exist:
      | task  | user     | parent            | content  |
      | task1 | student1 | A peer's thoughts | Nice one |
    And the following "mod_task > reactions" exist:
      | user     | post              | emoji    |
      | student1 | A peer's thoughts | thumbsup |
    When I am on the "My Task" "task activity" page logged in as student1
    Then the "Add a response" completion condition of "My Task" is displayed as "done"
    And the "Post a reply" completion condition of "My Task" is displayed as "done"
    And the "Make a reaction" completion condition of "My Task" is displayed as "done"

  Scenario: Replying or reacting to your own response does not count
    Given the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student1 | My own thoughts |
    And the following "mod_task > replies" exist:
      | task  | user     | parent          | content           |
      | task1 | student1 | My own thoughts | Talking to myself |
    And the following "mod_task > reactions" exist:
      | user     | post            | emoji    |
      | student1 | My own thoughts | thumbsup |
    When I am on the "My Task" "task activity" page logged in as student1
    Then the "Add a response" completion condition of "My Task" is displayed as "done"
    And the "Post a reply" completion condition of "My Task" is displayed as "todo"
    And the "Make a reaction" completion condition of "My Task" is displayed as "todo"
