@format @format_minimoodlewall @javascript
Feature: Drag and drop activity reordering in minimoodlewall format
  In order to organize course content
  As a teacher
  I need to reorder activities using drag and drop

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
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
      | quiz     | Quiz 1       | First quiz       | TC1    | 0       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm           | course | tag      |
      | Page 1       | TC1    | Reading  |
      | Assignment 1 | TC1    | Practice |
      | Quiz 1       | TC1    | Practice |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Activities are draggable in editing mode
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".minimoodlewall-card[draggable='true']" "css_element" should exist

  @javascript
  Scenario: Activities are not draggable when not editing
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".minimoodlewall-card[draggable='true']" "css_element" should not exist

  @javascript
  Scenario: Drag styling is applied in editing mode
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    Then ".minimoodlewall-card[style*='cursor: move']" "css_element" should exist

  @javascript
  Scenario: Drop zone exists during editing
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    # Verify the activity cards container exists
    Then ".minimoodlewall-activities" "css_element" should exist
    And I wait until "[data-for='cmitem']" "css_element" exists
