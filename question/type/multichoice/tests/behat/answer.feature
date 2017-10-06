@qtype @qtype_multichoice
Feature: Test answering a Multiple choice question with a single correct option
  As a student
  In order to be able to answer my Multiple choice question
  I need to be able to delete my choice

  @javascript
  Scenario: Using the Multiple choice question with a single correct option  @javascript
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com |
      | student1 | S1        | Student1 | student1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name             | template    |
      | Test questions   | multichoice | Multi-choice-001 | one_of_four |
    And quiz "Quiz 1" contains the following questions:
      | question         | page | maxmark |
      | Multi-choice-001 | 1    | 1.0     |

    # Try the quiz
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Quiz 1"
    And I press "Attempt quiz now"
    And I click on "One" "radio"
    And I click on "//label[text()='Clear my choice']" "xpath_element"
    And I should not see "Clear my choice"
    And I click on "Two" "radio"
    Then I should see "Clear my choice"
