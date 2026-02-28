@format @format_minimoodlewall @javascript
Feature: Change activity tag via module edit form
  As a teacher
  I want to change the tag of an activity after creation
  So that I can recategorize activities on the course wall

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
      | Writing  | assign        | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         |
      | Test Course | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And the following "format_minimoodlewall > activities" exist:
      | activity | name       | intro          | course | section | tag      |
      | page     | Test Page  | A test page    | TC1    | 0       | Reading  |
      | assign   | Test Assign| A test assign  | TC1    | 0       | Practice |

  Scenario: Teacher sees current tag pre-selected when editing an activity
    Given I log in as "teacher1"
    And I am on "Test Course" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Page" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    Then the field "Select a tag" matches value "Reading"

  Scenario: Teacher changes tag on an existing activity
    Given I log in as "teacher1"
    And I am on "Test Course" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Page" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    And I set the field "Select a tag" to "Practice"
    And I press "Save and return to course"
    # Re-open settings and verify the tag was persisted.
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Page" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    Then the field "Select a tag" matches value "Practice"

  Scenario: Teacher removes tag from an activity
    Given I log in as "teacher1"
    And I am on "Test Course" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Assign" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    And I set the field "Select a tag" to "No tag"
    And I press "Save and return to course"
    # Re-open settings and verify no tag is selected.
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Assign" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    Then the field "Select a tag" matches value "No tag"

  Scenario: Tag selector only shows tags selected for the course
    Given I log in as "teacher1"
    And I am on "Test Course" course homepage with editing mode on
    And I wait until ".minimoodlewall-card" "css_element" exists
    When I click on ".mmw-icon-btn .fa-cog" "css_element" in the "Test Page" "activity"
    And I expand all fieldsets
    And I click on "Activity tag" "link"
    Then the "Select a tag" select box should contain "Reading"
    And the "Select a tag" select box should contain "Practice"
    And the "Select a tag" select box should contain "Writing"
    And the "Select a tag" select box should contain "No tag"
