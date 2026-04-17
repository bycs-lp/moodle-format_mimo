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
    Then "#page-report-progress-index" "css_element" should exist

  Scenario: Teacher sees completion percentage on overview section cards
    Given the following "format_mimo > courses" exist:
      | fullname               | shortname | enablecompletion | enablemultisection | numsections |
      | Multi Section Course 1 | MSC1      | 1                | 1                  | 2           |
    And the following "format_mimo > activities" exist:
      | activity | name       | intro        | course | section | tag     | completion |
      | page     | MS Page 1  | First page   | MSC1   | 1       | Reading | 1          |
      | page     | MS Page 2  | Second page  | MSC1   | 1       | Reading | 1          |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | MSC1   | editingteacher |
      | student1 | MSC1   | student        |
      | student2 | MSC1   | student        |
    When I log in as "teacher1"
    And I am on "Multi Section Course 1" course homepage
    Then ".mimo-overview-grid" "css_element" should exist
    And ".mimo-overview-card__progress--teacher" "css_element" should exist
    And I should see "0%" in the ".mimo-overview-card__progress-text" "css_element"

  Scenario: Student sees personal progress on overview not percentage
    Given the following "format_mimo > courses" exist:
      | fullname               | shortname | enablecompletion | enablemultisection | numsections |
      | Multi Section Course 2 | MSC2      | 1                | 1                  | 2           |
    And the following "format_mimo > activities" exist:
      | activity | name       | intro        | course | section | tag     | completion |
      | page     | MS Page 3  | Third page   | MSC2   | 1       | Reading | 1          |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | MSC2   | student |
    When I log in as "student1"
    And I am on "Multi Section Course 2" course homepage
    Then ".mimo-overview-grid" "css_element" should exist
    And ".mimo-overview-card__progress--teacher" "css_element" should not exist
    And I should see "0 / 1" in the ".mimo-overview-card__progress-text" "css_element"
