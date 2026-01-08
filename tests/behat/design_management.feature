@format @format_minimoodlewall @javascript
Feature: Design management in minimoodlewall format
  In order to customize tag appearance per design variant
  As an admin
  I need to create and manage design variants

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > designs" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |
      | dark    | Dark        |

  @javascript
  Scenario: Admin can access design management page
    Given I log in as "admin"
    And I am on site homepage
    When I visit "/course/format/minimoodlewall/design_management.php"
    Then I should see "Design Management"
    And I should see "Create Design"

  @javascript
  Scenario: Admin can create a new design
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/design_management.php"
    When I click on "Create Design" "link"
    And I set the following fields to these values:
      | Internal Name | customdesign  |
      | Display Name  | Custom Design |
    And I press "Save changes"
    Then I should see "Custom Design"
    And I should see "customdesign"

  @javascript
  Scenario: Admin can edit existing designs
    Given the following "format_minimoodlewall > designs" exist:
      | name       | displayname    |
      | testdesign | Test Design    |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/design_management.php"
    When I click on "[data-testid='edit-design-button']" "css_element" in the "[data-testid='design-row'][data-design-name='testdesign']" "css_element"
    And I set the following fields to these values:
      | Display Name | Updated Test Design |
    And I press "Save changes"
    Then I should see "Updated Test Design"

  @javascript
  Scenario: Admin can delete designs that are not in use
    Given the following "format_minimoodlewall > designs" exist:
      | name     | displayname  |
      | unused   | Unused Theme |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/design_management.php"
    When I click on "[data-testid='delete-design-button']" "css_element" in the "[data-testid='design-row'][data-design-name='unused']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should not see "Unused Theme"

  @javascript
  Scenario: Admin cannot delete designs that are in use by courses
    Given the following "format_minimoodlewall > designs" exist:
      | name   | displayname |
      | inuse  | In Use      |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | selectedtags | designvariant |
      | Test Course | TC1       | minimoodlewall | Reading      | inuse         |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/design_management.php"
    When I click on "[data-testid='delete-design-button']" "css_element" in the "[data-testid='design-row'][data-design-name='inuse']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "In Use"
    And I should see "Cannot delete design"

  @javascript
  Scenario: Designs appear dynamically in course settings dropdown
    Given the following "format_minimoodlewall > designs" exist:
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
    Then the "Design" select box should contain "New Variant"
    And the "Design" select box should contain "Another Variant"

  @javascript
  Scenario: Course uses selected design variant
    Given the following "format_minimoodlewall > designs" exist:
      | name        | displayname    |
      | mydesign    | My Design      |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | selectedtags | designvariant |
      | Test Course | TC1       | minimoodlewall | Reading      | mydesign      |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 0       |
    And I log in as "admin"
    When I am on "Test Course" course homepage
    Then ".minimoodlewall-activities.minimoodlewall-design-mydesign" "css_element" should exist

  @javascript
  Scenario: Teachers cannot access design management
    Given the following "courses" exist:
      | fullname    | shortname | format         |
      | Test Course | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And I log in as "teacher1"
    And I am on "Test Course" course homepage
    Then I should not see "Site administration"
