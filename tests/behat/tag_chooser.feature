@format @format_minimoodlewall @javascript
Feature: Tag-based activity chooser in minimoodlewall format
  In order to organize activities by tags
  As a teacher
  I need to select a tag when creating activities

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tagsets" exist:
      | name         | description     |
      | Default Tags | Default tag set |
    And the following "format_minimoodlewall > tags" exist:
      | tagset       | name     | description       | activitytype1 | activitytype2 |
      | Default Tags | Reading  | Reading materials | page          | book          |
      | Default Tags | Practice | Practice tasks    | assign        | quiz          |
      | Default Tags | Discuss  | Discussion topics | forum         | chat          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Default Tags | 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Teacher sees tag dropdown when adding activity
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    Then I should see "Reading" in the ".dropdown-menu" "css_element"
    And I should see "Practice" in the ".dropdown-menu" "css_element"
    And I should see "Discuss" in the ".dropdown-menu" "css_element"
    And I should see "Activity or resource" in the ".dropdown-menu" "css_element"

  @javascript
  Scenario: Teacher can create activity with pre-selected tag and activity type
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I click on "Reading" "link" in the ".dropdown-menu" "css_element"
    And I should see "Choose activity type" in the ".modal-title" "css_element"
    And I should see "Page" in the ".modal-body" "css_element"
    And I should see "Book" in the ".modal-body" "css_element"
    And I click on "Create Page" "button" in the ".modal-body" "css_element"
    And I wait until the page is ready
    And I set the following fields to these values:
      | Name         | Reading Material 1 |
      | Description  | First reading      |
    And I click on "Save and return to course" "button"
    Then I should see "Reading Material 1"
    And ".minimoodlewall-card" "css_element" should exist

  @javascript
  Scenario: Teacher can open standard activity chooser from dropdown
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I click on "Activity or resource" "link" in the ".dropdown-menu" "css_element"
    Then I should see "Add an activity or resource"
    And I should see "Assignment"
    And I should see "Quiz"

  @javascript
  Scenario: Activity is automatically tagged after creation
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I click on "Practice" "link" in the ".dropdown-menu" "css_element"
    And I click on "Create Assignment" "button" in the ".modal-body" "css_element"
    And I wait until the page is ready
    And I set the following fields to these values:
      | Assignment name | Practice Assignment 1 |
      | Description     | First practice task   |
    And I click on "Save and return to course" "button"
    And I wait until the page is ready
    Then I should see "Practice Assignment 1"
    And I should see "Practice" in the ".minimoodlewall-card .minimoodlewall-tag-label" "css_element"

  @javascript
  Scenario: Multiple activities can have different tags
    Given the following "activities" exist:
      | activity | name         | intro            | course | section |
      | assign   | Assignment 1 | First assignment | TC1    | 0       |
      | quiz     | Quiz 1       | First quiz       | TC1    | 0       |
      | page     | Page 1       | First page       | TC1    | 0       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm           | tag      |
      | Assignment 1 | Practice |
      | Quiz 1       | Practice |
      | Page 1       | Reading  |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    Then I should see "Practice" in the "Assignment 1" "activity"
    And I should see "Practice" in the "Quiz 1" "activity"
    And I should see "Reading" in the "Page 1" "activity"
