@mod @mod_task @javascript
Feature: Task notification preferences
  In order to control how I am told about activity in a Task
  As a participant
  I can choose a notification preference that defaults to my role

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber | intro               |
      | task     | My Task | C1     | task1    | What did you learn? |

  Scenario: The notification settings panel offers the four preferences
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "Notification preferences"
    And "Mute notifications (except for Teacher replies)" "button" should exist
    And "All new responses and replies" "button" should exist
    And "All new replies to my response only" "button" should exist

  Scenario: A teacher defaults to all new responses and replies
    When I am on the "My Task" "task activity" page logged in as teacher1
    Then "//*[@data-region='task-notify-settings']//button[@aria-pressed='true'][contains(., 'All new responses and replies')]" "xpath_element" should exist

  Scenario: A student defaults to replies to their own response only
    When I am on the "My Task" "task activity" page logged in as student1
    Then "//*[@data-region='task-notify-settings']//button[@aria-pressed='true'][contains(., 'All new replies to my response only')]" "xpath_element" should exist

  Scenario: Selecting a preference marks it as the active choice
    Given I am on the "My Task" "task activity" page logged in as student1
    When I click on "[data-region='task-notify-settings'] .dropdown-toggle" "css_element"
    And I click on "Mute notifications (except for Teacher replies)" "button"
    Then "//*[@data-region='task-notify-settings']//button[@aria-pressed='true'][contains(., 'Mute notifications (except for Teacher replies)')]" "xpath_element" should exist
