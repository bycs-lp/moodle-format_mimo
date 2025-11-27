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
      | name         | description        |
      | Chooser Tags | Tag chooser tests  |
    And the following "format_minimoodlewall > tags" exist:
      | tagset       | name     | description       | activitytype1 | activitytype2 |
      | Chooser Tags | Reading  | Reading materials | page          | book          |
      | Chooser Tags | Practice | Practice tasks    | assign        | quiz          |
      | Chooser Tags | Discuss  | Discussion topics | forum         | chat          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Chooser Tags | 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Teacher sees tag dropdown when adding activity
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    Then I should see "Reading" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I should see "Practice" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I should see "Discuss" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"

  @javascript
  Scenario: Teacher can create activity with pre-selected tag and activity type
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "Reading" "link" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then I should see "Reading" in the ".modal-title" "css_element"
    And I should see "Select activity type..." in the ".modal-body" "css_element"
    And I should see "Page" in the ".modal-body" "css_element"
    And I should see "Book" in the ".modal-body" "css_element"
    And I click on "Page" "link" in the ".modal-body" "css_element"
    And I wait until the page is ready
    And I set the following fields to these values:
      | Name         | Reading Material 1 |
      | Description  | First reading      |
    And I set the field "Page content" to "Reading content for learners."
    And I click on "Save and return to course" "button"
    And I wait until the page is ready
    And I wait until ".minimoodlewall-filterbar" "css_element" exists
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Reading') and not(@disabled)]" "xpath_element" exists
    And I wait "1" seconds
    When I click on "Reading" "button" in the ".minimoodlewall-filterbar" "css_element"
    And I wait "2" seconds
    Then ".minimoodlewall-card" "css_element" should be visible
    And I should see "First reading" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Teacher can open standard activity chooser from dropdown
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "Reading" "link" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    And I click on "Activity or resource" "link" in the ".modal-body" "css_element"
    Then I should see "Add an activity or resource"
    And I should see "Assignment"
    And I should see "Quiz"

  @javascript
  Scenario: Activity is automatically tagged after creation
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "Practice" "link" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    And I click on "Assignment" "link" in the ".modal-body" "css_element"
    And I wait until the page is ready
    And I set the following fields to these values:
      | Assignment name | Practice Assignment 1 |
      | Description     | First practice task   |
    And I click on "Save and return to course" "button"
    And I wait until the page is ready
    And I wait until ".minimoodlewall-filterbar" "css_element" exists
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and not(@disabled)]" "xpath_element" exists
    And I wait "1" seconds
    When I click on "Practice" "button" in the ".minimoodlewall-filterbar" "css_element"
    And I wait "2" seconds
    Then I should see "First practice task" in the ".minimoodlewall-activities" "css_element"
    And I turn editing mode off
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and not(@disabled)]" "xpath_element" exists
    And "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and @data-hasactivities='1']" "xpath_element" should exist

  @javascript
  Scenario: Multiple activities can have different tags
    Given the following "activities" exist:
      | activity | name         | intro            | course | section |
      | assign   | Assignment 1 | First assignment | TC1    | 1       |
      | quiz     | Quiz 1       | First quiz       | TC1    | 1       |
      | page     | Page 1       | First page       | TC1    | 1       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm           | tag      |
      | Assignment 1 | Practice |
      | Quiz 1       | Practice |
      | Page 1       | Reading  |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode off
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait "2" seconds
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and not(@disabled)]" "xpath_element" exists
    When I click on "Practice" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should not see "Page 1" in the ".minimoodlewall-activities" "css_element"
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Reading') and not(@disabled)]" "xpath_element" exists
    When I click on "Reading" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Page 1"
