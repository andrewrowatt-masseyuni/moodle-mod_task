@mod @mod_task @javascript
Feature: Task reply and reaction activity settings
  In order to control how students interact with each other's work
  As a teacher
  I can disable peer replies and emoji reactions for an activity

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |

  Scenario: With replies disabled a student can reply to their own response but not to a peer
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | enablereplies |
      | task     | My Task | C1     | task1    | What did you learn? | 0             |
    And the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student2 | A peer response |
      | task1 | student1 | My own response |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "A peer response" in the "[data-region=\"posts\"]" "css_element"
    And "Reply" "button" should exist in the "[data-region=\"your-response-posts\"]" "css_element"
    But "Reply" "button" should not exist in the "[data-region=\"posts\"]" "css_element"

  Scenario: With reactions disabled the reaction button is not shown
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | enablereactions |
      | task     | My Task | C1     | task1    | What did you learn? | 0               |
    And the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student2 | A peer response |
      | task1 | student1 | My own response |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "A peer response" in the "[data-region=\"posts\"]" "css_element"
    And "React to this post" "button" should not exist
