@core @core_course
Feature: Managers can copy courses
  In order to group users and contents
  As a manager
  I need to copy courses and set values on them

  @javascript
  Scenario: Copy a course from the management interface and return to it
    Given the following "courses" exist:
      | fullname | shortname | idnumber | startdate | enddate   |
      | Course 1 | Course 1  | C1       | 957139200 | 960163200 |
    And I log in as "admin"
    And I go to the courses management page
    And I should see the "Categories" management page
    And I click on category "Miscellaneous" in the management interface
    And I should see the "Course categories and courses" management page
    And I click on "copy" action for "Course 1" in management course listing
    When I set the following fields to these values:
      | Course full name | Course 2 |
      | Course short name | Course 2 |
      | Course category | Miscellaneous |
      | id_startdate_day | 24 |
      | id_startdate_month | October |
      | id_startdate_year | 2015 |
      | id_enddate_day | 24 |
      | id_enddate_month | October |
      | id_enddate_year | 2016 |
    And I press "Save and return"
    Then I should see the "Course categories and courses" management page
    And I click on "Sort courses" "link"
    And I click on "Sort by Course full name ascending" "link" in the ".course-listing-actions" "css_element"
    And I should see course listing "Course 1" before "Course 2"
    And I click on "Course 2" "link" in the "region-main" "region"
    And I click on "Edit" "link" in the ".course-detail" "css_element"
    And the following fields match these values:
      | Course full name | Course 2 |
      | Course short name | Course 2 |
      | Course summary | Course 2 summary |
      | id_startdate_day | 24 |
      | id_startdate_month | October |
      | id_startdate_year | 2015 |
      | id_enddate_day | 24 |
      | id_enddate_month | October |
      | id_enddate_year | 2016 |
