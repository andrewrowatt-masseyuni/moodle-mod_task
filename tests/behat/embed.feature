@mod @mod_task @javascript
Feature: Task name when embedded
  In order to know which Task I am looking at
  As a student
  I can see the Task name when the Task is embedded outside its own activity page

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: The Task name is shown as a heading when embedded on the course page
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | embedoncoursepage |
      | task     | My Task | C1     | task1    | What did you learn? | 1                 |
    When I am on the "Course 1" course page logged in as student1
    Then I should see "What did you learn?"
    And I should see "My Task" in the "h3.mod-task-name" "css_element"

  Scenario: The Task name is not duplicated on the activity page
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               |
      | task     | My Task | C1     | task1    | What did you learn? |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "Your response"
    And "h3.mod-task-name" "css_element" should not exist
