@format @format_minimoodlewall @javascript
Feature: Course creation with minimoodlewall format
  In order to use the minimal moodle wall format
  As a teacher
  I need to create courses and configure them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > profiles" exist:
      | name    | displayname |
      | classic | Classic     |
    And the following "format_minimoodlewall > tags" exist:
      | name      | activitytype1 | activitytype2 |
      | Reading   | page          | book          |
      | Practice  | assign        | quiz          |
      | Biology   | assign        | forum         |
      | Chemistry | quiz          | workshop      |

  @javascript
  Scenario: Create a course with minimoodlewall format and select tags
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I expand all fieldsets
    And I set the following fields to these values:
      | Course full name    | Test Course 1        |
      | Course short name   | TC1                  |
      | Format              | Minimal Moodle Wall  |
      | Show tag filter bar | 1                    |
      | Activity Profile    | Classic              |
    And I press "Save and display"
    Then I should see "Test Course 1"

  @javascript
  Scenario: Tag selection is required when creating a course
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I expand all fieldsets
    And I set the following fields to these values:
      | Course full name  | Test Course 2       |
      | Course short name | TC2                 |
      | Format            | Minimal Moodle Wall |
    When I press "Save and display"
    Then I should see "Test Course 2"

  @javascript
  Scenario: Tags can be changed after course creation
    Given the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname |
      | Test Course 1 | TC1       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And I log in as "admin"
    When I am on "Test Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then I should see "Activity Profile"

  @javascript
  Scenario: Course displays activities in wall format
    Given the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname |
      | Test Course 1 | TC1       |
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
