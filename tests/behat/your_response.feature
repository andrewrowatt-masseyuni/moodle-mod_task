@mod @mod_task @javascript
Feature: Task "Your response" panel
  In order to find my own contribution quickly and keep peers' responses tidy
  As a student
  My response sits in a dedicated, collapsible panel above everyone else's

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber | intro               |
      | task     | My Task | C1     | task1    | What did you learn? |

  Scenario: Before responding the Your response panel offers the composer
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "Your response"
    And "Post" "button" should exist
    And I should not see "Hide your response"
    And I should not see "Show your response"

  Scenario: After responding the response is shown with a hide/show toggle
    Given the following "mod_task > responses" exist:
      | task  | user     | content             |
      | task1 | student1 | My committed answer |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "My committed answer" in the "[data-region=\"your-response-posts\"]" "css_element"
    And I should see "Hide your response"
    And I should not see "Show your response"
    And "Post" "button" should not exist
    When I click on "Hide your response" "button"
    Then I should see "Show your response"
    And I should not see "Hide your response"

  Scenario: My response sits in its own panel and peers' under Other responses
    Given the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "My own thoughts" in the "[data-region=\"your-response-posts\"]" "css_element"
    And I should see "Other responses"
    And I should see "A peer's thoughts" in the "[data-region=\"posts\"]" "css_element"
    And I should not see "A peer's thoughts" in the "[data-region=\"your-response-posts\"]" "css_element"
    And I should not see "My own thoughts" in the "[data-region=\"posts\"]" "css_element"

  Scenario: Each other response is its own collapsible panel
    Given the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "A peer's thoughts"
    And "Hide response" "button" should exist in the "[data-region=\"posts\"]" "css_element"
    When I click on "Hide response" "button" in the "[data-region=\"posts\"]" "css_element"
    Then I should not see "A peer's thoughts" in the "[data-region=\"posts\"]" "css_element"
