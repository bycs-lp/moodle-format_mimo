@format @format_minimoodlewall @javascript
Feature: Tag-based activity chooser in minimoodlewall format
  In order to organize activities by tags
  As a teacher
  I need to select a tag when creating activities

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
      | Discuss  | forum         | chat          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | selectedtags              | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Reading, Practice, Discuss| 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Teacher sees tag dropdown when adding activity
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    Then "[data-tag-name='Reading']" "css_element" should exist in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And "[data-tag-name='Practice']" "css_element" should exist in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And "[data-tag-name='Discuss']" "css_element" should exist in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"

  @javascript
  Scenario: Teacher can create activity with pre-selected tag and activity type
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "[data-tag-name='Reading']" "css_element" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then I should see "Reading" in the ".modal-title" "css_element"
    And I should see "Choose a fitting activity for the area of Reading" in the ".modal-body" "css_element"
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
    When I click on "[data-testid='filter-button'][data-tag-name='Reading']" "css_element"
    And I wait "2" seconds
    Then ".minimoodlewall-card" "css_element" should be visible
    And I should see "Reading Material 1" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Teacher can open standard activity chooser from dropdown
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "[data-tag-name='Reading']" "css_element" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    And I click on "Open all activities" "link" in the ".modal-body" "css_element"
    Then I should see "Add an activity or resource"
    And I should see "Assignment"
    And I should see "Quiz"

  @javascript
  Scenario: Activity is automatically tagged after creation
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    When I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "[data-tag-name='Practice']" "css_element" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
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
    When I click on "[data-testid='filter-button'][data-tag-name='Practice']" "css_element"
    And I wait "2" seconds
    Then I should see "Practice Assignment 1" in the ".minimoodlewall-activities" "css_element"
    And I turn editing mode off
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and not(@disabled)]" "xpath_element" exists
    And "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Practice') and @data-hasactivities='1']" "xpath_element" should exist

  @javascript
  Scenario: Multiple activities can have different tags
    Given the following "format_minimoodlewall > activities" exist:
      | activity | name         | intro            | course | section | tag      |
      | assign   | Assignment 1 | First assignment | TC1    | 1       | Practice |
      | quiz     | Quiz 1       | First quiz       | TC1    | 1       | Practice |
      | page     | Page 1       | First page       | TC1    | 1       | Reading  |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode off
    And I wait until ".minimoodlewall-filterbar" "css_element" exists
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait "2" seconds
    When I click on "[data-testid='filter-button'][data-tag-name='Practice']" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should not see "Page 1" in the ".minimoodlewall-activities" "css_element"
    And I wait until "//button[contains(@class,'minimoodlewall-filterbar-button')][contains(@title,'Reading') and not(@disabled)]" "xpath_element" exists
    When I click on "[data-testid='filter-button'][data-tag-name='Reading']" "css_element"
    Then I should see "Page 1"
