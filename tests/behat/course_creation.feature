@format @format_minimoodlewall @javascript
Feature: Course creation with minimoodlewall format
  In order to use the minimal moodle wall format
  As a teacher
  I need to select a tag set when creating a course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tagsets" exist:
      | name           | description              |
      | Default Tags   | Default tag set          |
      | Science Topics | Tags for science courses |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name      | description       | activitytype1 | activitytype2 |
      | Default Tags   | Reading   | Reading materials | page          | book          |
      | Default Tags   | Practice  | Practice tasks    | assign        | quiz          |
      | Science Topics | Biology   | Life science      | assign        | forum         |
      | Science Topics | Chemistry | Matter            | quiz          | workshop      |

  @javascript
  Scenario: Create a course with minimoodlewall format and select tag set
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I expand all fieldsets
    And I set the following fields to these values:
      | Course full name    | Test Course 1        |
      | Course short name   | TC1                  |
      | Format              | Minimal Moodle Wall  |
      | Tag set             | Default Tags         |
      | Enable tag filtering| 1                    |
      | Design              | classic              |
    And I press "Save and display"
    Then I should see "Test Course 1"

  @javascript
  Scenario: Tag set selection is required when creating a course
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I expand all fieldsets
    And I set the following fields to these values:
      | Course full name  | Test Course 2       |
      | Course short name | TC2                 |
      | Format            | Minimal Moodle Wall |
    When I press "Save and display"
    Then I should see "Required"

  @javascript
  Scenario: Tag set cannot be changed after course creation
    Given the following "courses" exist:
      | fullname      | shortname | format            |
      | Test Course 1 | TC1       | minimoodlewall    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And I log in as "admin"
    When I am on "Test Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the "Tag set" "select" should be disabled

  @javascript
  Scenario: Course displays activities in wall format
    Given the following "courses" exist:
      | fullname      | shortname | format         | tagsetid     |
      | Test Course 1 | TC1       | minimoodlewall | Default Tags |
    And the following "activities" exist:
      | activity | name          | intro                | course | section |
      | assign   | Assignment 1  | First assignment     | TC1    | 1       |
      | quiz     | Quiz 1        | First quiz           | TC1    | 1       |
      | page     | Page 1        | First page           | TC1    | 1       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | TC1    | student |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should see "Page 1"
    And ".minimoodlewall-activities" "css_element" should exist
