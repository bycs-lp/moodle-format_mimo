@format @format_minimoodlewall @javascript
Feature: Activity card action controls in minimoodlewall format
  In order to quickly manage activities
  As a teacher
  I need settings and delete icons on activity cards

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         |
      | Test Course 1 | TC1       | minimoodlewall |
    And the following "activities" exist:
      | activity | name         | intro            | course | section |
      | page     | Page 1       | First page       | TC1    | 0       |
      | assign   | Assignment 1 | First assignment | TC1    | 0       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm           | course | tag      |
      | Page 1       | TC1    | Reading  |
      | Assignment 1 | TC1    | Practice |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student        |

  @javascript
  Scenario: Settings icon is visible on activity cards in editing mode
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".mmw-card-controls .mmw-icon-btn .fa-cog" "css_element" should exist

  @javascript
  Scenario: Delete icon is visible on activity cards in editing mode
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".mmw-card-controls [data-action='cmDelete'] .fa-trash" "css_element" should exist

  @javascript
  Scenario: Settings icon links to activity settings page
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Page 1" "activity"
    Then I should see "Edit settings"

  @javascript
  Scenario: Clicking delete icon triggers delete confirmation
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on "[data-action='cmDelete']" "css_element" in the "Page 1" "activity"
    Then I should see "Delete" in the ".modal" "css_element"

  @javascript
  Scenario: Card action icons are not visible for students
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".mmw-card-controls" "css_element" should not exist

  @javascript
  Scenario: Card action icons are not visible when editing mode is off
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".mmw-card-controls .mmw-icon-btn .fa-cog" "css_element" should not exist
    And ".mmw-card-controls [data-action='cmDelete']" "css_element" should not exist
