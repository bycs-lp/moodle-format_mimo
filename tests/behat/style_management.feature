@format @format_minimoodlewall @javascript
Feature: Style management in minimoodlewall format
  In order to customize tag appearance per style variant
  As an admin
  I need to create and manage style variants

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > styles" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |
      | dark    | Dark        |

  @javascript
  Scenario: Admin can access style management page
    Given I log in as "admin"
    And I am on site homepage
    When I visit "/course/format/minimoodlewall/style_management.php"
    Then I should see "Style Management"
    And I should see "Create Style"

  @javascript
  Scenario: Admin can create a new style
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/style_management.php"
    When I click on "Create Style" "link"
    And I set the following fields to these values:
      | Internal Name | customstyle  |
      | Display Name  | Custom Style |
    And I press "Save changes"
    Then I should see "Custom Style"
    And I should see "customstyle"

  @javascript
  Scenario: Admin can edit existing styles
    Given the following "format_minimoodlewall > styles" exist:
      | name       | displayname    |
      | teststyle | Test Style    |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/style_management.php"
    When I click on "[data-testid='edit-style-button']" "css_element" in the "[data-testid='style-row'][data-style-name='teststyle']" "css_element"
    And I set the following fields to these values:
      | Display Name | Updated Test Style |
    And I press "Save changes"
    Then I should see "Updated Test Style"

  @javascript
  Scenario: Admin can delete styles that are not in use
    Given the following "format_minimoodlewall > styles" exist:
      | name     | displayname  |
      | unused   | Unused Theme |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/style_management.php"
    When I click on "[data-testid='delete-style-button']" "css_element" in the "[data-testid='style-row'][data-style-name='unused']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should not see "Unused Theme"

  @javascript
  Scenario: Admin cannot delete styles that are in use by courses
    Given the following "format_minimoodlewall > styles" exist:
      | name   | displayname |
      | inuse  | In Use      |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | selectedtags | stylevariant |
      | Test Course | TC1       | minimoodlewall | Reading      | inuse         |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/style_management.php"
    When I click on "[data-testid='delete-style-button']" "css_element" in the "[data-testid='style-row'][data-style-name='inuse']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "In Use"
    And I should see "Cannot delete style"

  @javascript
  Scenario: Styles appear dynamically in course settings dropdown
    Given the following "format_minimoodlewall > styles" exist:
      | name        | displayname      |
      | newvariant  | New Variant      |
      | anothervar  | Another Variant  |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I set the following fields to these values:
      | Course full name  | Test Course         |
      | Course short name | TC1                 |
      | Format            | Minimal Moodle Wall |
    And I expand all fieldsets
    Then the "Style" select box should contain "New Variant"
    And the "Style" select box should contain "Another Variant"

  @javascript
  Scenario: Course uses selected style variant
    Given the following "format_minimoodlewall > styles" exist:
      | name        | displayname    |
      | mystyle    | My Style      |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | selectedtags | stylevariant |
      | Test Course | TC1       | minimoodlewall | Reading      | mystyle      |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 0       |
    And I log in as "admin"
    When I am on "Test Course" course homepage
    Then ".minimoodlewall-activities.minimoodlewall-style-mystyle" "css_element" should exist

  @javascript
  Scenario: Teachers cannot access style management
    Given the following "courses" exist:
      | fullname    | shortname | format         |
      | Test Course | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And I log in as "teacher1"
    And I am on "Test Course" course homepage
    Then I should not see "Site administration"
