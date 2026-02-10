@format @format_minimoodlewall @javascript
Feature: Style variants in minimoodlewall format
  In order to customize course appearance
  As a teacher
  I need to select different style variants

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_minimoodlewall > styles" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |
      | dark    | Dark        |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 | activitytype2 |
      | Reading | page          | book          |

  @javascript
  Scenario: Admin can set style variant when creating course
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I set the following fields to these values:
      | Course full name  | Test Course 1       |
      | Course short name | TC1                 |
      | Format            | Minimal Moodle Wall |
    And I expand all fieldsets
    And I set the field "Tagset" to "Default Tagset"
    And I click on "Reading" "checkbox"
    And I set the field "Style" to "Classic"
    And I press "Save and display"
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 1       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC1    | student |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    Then ".minimoodlewall-activities.minimoodlewall-style-classic" "css_element" should exist

  @javascript
  Scenario: Course retains selected style variant
    Given the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | selectedtags | stylevariant |
      | Test Course 2 | TC2       | minimoodlewall | Reading      | light         |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC2    | 0       |
    And I log in as "admin"
    When I am on "Test Course 2" course homepage
    Then ".minimoodlewall-activities.minimoodlewall-style-light" "css_element" should exist
