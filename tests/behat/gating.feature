@mod @mod_task @javascript
Feature: Task response gating
  In order to encourage students to commit to their own answer
  As a student
  I cannot see the teacher response or other students' responses until I post mine

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1       | topics |
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
      | activity | name    | course | idnumber | intro                | teacherresponse        | teacherresponseismodelanswer |
      | task     | My Task | C1     | task1    | What did you learn?  | This is the exemplar.  | 1                            |

  Scenario: A student cannot see the teacher response before responding
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "What did you learn?"
    And I should see "Post your response"
    And I should not see "This is the exemplar."

  Scenario: A student sees the teacher model answer and peers after responding
    Given the following "mod_task > responses" exist:
      | task  | user     | content                          |
      | task1 | student2 | A peer response from student two |
      | task1 | student1 | My own response                  |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "Model answer"
    And I should see "This is the exemplar."
    And I should see "A peer response from student two"

  Scenario: The response editor is shown directly, not behind a button
    When I am on the "My Task" "task activity" page logged in as student1
    Then "Post" "button" should exist
    And I should not see "Add your response"

  Scenario: A student cannot edit or delete their own response
    Given the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student1 | My own response |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "My own response"
    But "Edit" "button" should not exist in the "[data-region=\"your-response-posts\"]" "css_element"
    And "Delete" "button" should not exist in the "[data-region=\"your-response-posts\"]" "css_element"

  Scenario: A teacher can edit and delete responses
    Given the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student1 | My own response |
    When I am on the "My Task" "task activity" page logged in as teacher1
    Then I should see "My own response"
    And "Edit" "button" should exist in the "[data-region=\"posts\"]" "css_element"
    And "Delete" "button" should exist in the "[data-region=\"posts\"]" "css_element"

  Scenario: Anonymous responses hide the author name from peers
    Given the following "mod_task > responses" exist:
      | task  | user     | content                     | anonymous |
      | task1 | student2 | Secretly from student two   | 1         |
      | task1 | student1 | My own response             | 0         |
    When I am on the "My Task" "task activity" page logged in as student1
    Then I should see "Secretly from student two"
    And I should not see "Student Two"

  Scenario: A teacher sees responses without posting, with author names
    Given the following "mod_task > responses" exist:
      | task  | user     | content                   | anonymous |
      | task1 | student2 | Secretly from student two | 1         |
    When I am on the "My Task" "task activity" page logged in as teacher1
    Then I should see "Secretly from student two"
    And I should see "Student Two"
    And I should see "Anonymous"
