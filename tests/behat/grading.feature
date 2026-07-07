@mod @mod_task @core_grades
Feature: Task participation grading
  In order to reward participation in a Task
  As a teacher
  I can award marks for responding, replying and reacting

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  Scenario: An ungraded task creates no gradebook item
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded |
      | task     | My Task | C1     | task1    | What did you learn? | 0      |
    When I am on the "Course 1" "grades > gradebook setup" page logged in as "teacher1"
    Then I should not see "My Task"

  Scenario: The grade maximum is the sum of the enabled contributions
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    When I am on the "Course 1" "grades > gradebook setup" page logged in as "teacher1"
    Then I should see "110.00" in the "My Task" "table_row"

  Scenario: Disabled replies and reactions are excluded from the grade maximum
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereactpoints | enablereplies | enablereactions |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 10               | 0             | 0               |
    When I am on the "Course 1" "grades > gradebook setup" page logged in as "teacher1"
    Then I should see "80.00" in the "My Task" "table_row"

  Scenario: Posting a response earns the full response marks
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    And the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student1 | My own thoughts |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "80.00" in the "Student One" "table_row"
    And I should not see "80.00" in the "Student Two" "table_row"

  Scenario: Replies below the required number earn a proportional share
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    And the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    And the following "mod_task > replies" exist:
      | task  | user     | parent            | content     |
      | task1 | student1 | A peer's thoughts | Great point |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "90.00" in the "Student One" "table_row"

  Scenario: Replies beyond the required number are capped at the reply marks
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    And the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    And the following "mod_task > replies" exist:
      | task  | user     | parent            | content      |
      | task1 | student1 | A peer's thoughts | First reply  |
      | task1 | student1 | A peer's thoughts | Second reply |
      | task1 | student1 | A peer's thoughts | Third reply  |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "100.00" in the "Student One" "table_row"

  Scenario: Replies and reactions within your own thread earn nothing
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    And the following "mod_task > responses" exist:
      | task  | user     | content         |
      | task1 | student1 | My own thoughts |
    And the following "mod_task > replies" exist:
      | task  | user     | parent          | content          |
      | task1 | student1 | My own thoughts | Adding to myself |
    And the following "mod_task > reactions" exist:
      | user     | post            | emoji    |
      | student1 | My own thoughts | thumbsup |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "80.00" in the "Student One" "table_row"
    And I should not see "90.00" in the "Student One" "table_row"

  Scenario: Reacting to a peer's post earns the reaction marks
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 1               |
    And the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    And the following "mod_task > reactions" exist:
      | user     | post              | emoji    |
      | student1 | A peer's thoughts | thumbsup |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "90.00" in the "Student One" "table_row"

  Scenario: Multiple reactions on the same post count once towards the required number
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               | graded | graderesponsepoints | gradereplypoints | gradereplycount | gradereactpoints | gradereactcount |
      | task     | My Task | C1     | task1    | What did you learn? | 1      | 80                  | 20               | 2               | 10               | 2               |
    And the following "mod_task > responses" exist:
      | task  | user     | content           |
      | task1 | student2 | A peer's thoughts |
      | task1 | student1 | My own thoughts   |
    And the following "mod_task > reactions" exist:
      | user     | post              | emoji    |
      | student1 | A peer's thoughts | thumbsup |
      | student1 | A peer's thoughts | heart    |
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then I should see "85.00" in the "Student One" "table_row"

  @javascript
  Scenario: The marks settings only show while the task is graded
    Given the following "activities" exist:
      | activity | name    | course | idnumber | intro               |
      | task     | My Task | C1     | task1    | What did you learn? |
    When I am on the "My Task" "task activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then I should see "Graded task"
    And "Marks for posting a response" "field" should not be visible
    And "Marks for replies" "field" should not be visible
    And "Marks for reactions" "field" should not be visible
    And I set the field "Graded task" to "Yes"
    And "Marks for posting a response" "field" should be visible
    And "Marks for replies" "field" should be visible
    And "Replies needed for full marks" "field" should be visible
    And "Marks for reactions" "field" should be visible
    And "Reactions needed for full marks" "field" should be visible
