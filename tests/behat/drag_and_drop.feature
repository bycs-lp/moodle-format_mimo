@format @format_mimo @javascript
Feature: Drag and drop activity reordering in mimo format
  In order to organize course content
  As a teacher
  I need to reorder activities using drag and drop

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_mimo > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_mimo > courses" exist:
      | fullname      | shortname | format         |
      | Test Course 1 | TC1       | mimo |
    And the following "format_mimo > activities" exist:
      | activity | name         | intro            | course | section | tag      |
      | page     | Page 1       | First page       | TC1    | 1       | Reading  |
      | assign   | Assignment 1 | First assignment | TC1    | 1       | Practice |
      | quiz     | Quiz 1       | First quiz       | TC1    | 1       | Practice |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Activities are draggable in editing mode
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".mimo-card" "css_element" exists
    Then "[data-for='cmitem'][draggable='true']" "css_element" should exist

  @javascript
  Scenario: Activities are not draggable when not editing
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage
    And I wait until ".mimo-card" "css_element" exists
    Then "[data-for='cmitem'][draggable='true']" "css_element" should not exist

  @javascript
  Scenario: Drag styling is applied in editing mode
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I wait until ".mimo-card" "css_element" exists
    Then "[data-for='cmitem'][draggable='true'] .mimo-card" "css_element" should exist

  @javascript
  Scenario: Drop zone exists during editing
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    # Verify the activity cards container exists
    Then ".mimo-activities" "css_element" should exist
    And I wait until "[data-for='cmitem']" "css_element" exists
