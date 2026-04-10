@format @format_mimo @javascript
Feature: Teacher completion counts on activity cards
  In order to monitor student progress at a glance
  As a teacher
  I need to see how many students completed each activity on the wall

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | student3 | Student   | Three    | student3@example.com |
    And the following "format_mimo > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_mimo > courses" exist:
      | fullname      | shortname | enablecompletion |
      | Test Course 1 | TC1       | 1                |
    And the following "format_mimo > activities" exist:
      | activity | name         | intro            | course | section | tag      | completion |
      | page     | Page 1       | First page       | TC1    | 1       | Reading  | 1          |
      | assign   | Assignment 1 | First assignment | TC1    | 1       | Practice | 1          |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student        |
      | student2 | TC1    | student        |
      | student3 | TC1    | student        |

  Scenario: Teacher sees completion count badges on activity cards
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage
    Then ".badge-teacher-completion" "css_element" should exist
    And I should see "0/3" in the ".mimo-completion-badge" "css_element"

  Scenario: Student sees personal completion badge instead of counts
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".badge-teacher-completion" "css_element" should not exist
    And ".badge-incomplete" "css_element" should exist

  Scenario: Teacher completion count links to progress report
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    When I click on ".badge-teacher-completion" "css_element"
    Then I should see "Activity completion"

  Scenario: Student marks activity complete and teacher count updates
    # First, student completes the activity.
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I click on "Page 1" "link"
    And I press "Mark as done"
    And I log out
    # Now teacher checks the wall.
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    Then I should see "1/3" in the ".mimo-completion-badge" "css_element"
