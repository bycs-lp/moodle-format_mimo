@format @format_minimoodlewall @javascript
Feature: Activity profile management in minimoodlewall format
  In order to customize tag appearance per activity profile
  As an admin
  I need to create and manage activity profiles

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > profiles" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |
      | dark    | Dark        |

  @javascript
  Scenario: Admin can access profile management page
    Given I log in as "admin"
    And I am on site homepage
    When I visit "/course/format/minimoodlewall/profile_management.php"
    Then I should see "Activity Profile Management"
    And I should see "Create Activity Profile"

  @javascript
  Scenario: Admin can create a new profile
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/profile_management.php"
    When I click on "Create Activity Profile" "button"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Internal Name | customprofile  |
      | Display Name  | Custom Profile |
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Custom Profile"
    And I should see "customprofile"

  @javascript
  Scenario: Admin can edit existing profiles
    Given the following "format_minimoodlewall > profiles" exist:
      | name        | displayname   |
      | testprofile | Test Profile  |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/profile_management.php"
    When I click on "[data-testid='edit-profile-button']" "css_element" in the "[data-testid='profile-row'][data-profile-name='testprofile']" "css_element"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Display Name | Updated Test Profile |
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Updated Test Profile"

  @javascript
  Scenario: Admin can delete profiles that are not in use
    Given the following "format_minimoodlewall > profiles" exist:
      | name     | displayname   |
      | unused   | Unused Profile |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/profile_management.php"
    When I click on "[data-testid='delete-profile-button']" "css_element" in the "[data-testid='profile-row'][data-profile-name='unused']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should not see "Unused Profile"

  @javascript
  Scenario: Admin cannot delete profiles that are in use by courses
    Given the following "format_minimoodlewall > profiles" exist:
      | name   | displayname |
      | inuse  | In Use      |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | activityprofile |
      | Test Course | TC1       | minimoodlewall | inuse           |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/profile_management.php"
    When I click on "[data-testid='delete-profile-button']" "css_element" in the "[data-testid='profile-row'][data-profile-name='inuse']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "In Use"
    And I should see "Cannot delete"

  @javascript
  Scenario: Profiles appear dynamically in course settings dropdown
    Given the following "format_minimoodlewall > profiles" exist:
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
    Then the "Activity Profile" select box should contain "New Variant"
    And the "Activity Profile" select box should contain "Another Variant"

  @javascript
  Scenario: Course uses selected activity profile
    Given the following "format_minimoodlewall > profiles" exist:
      | name        | displayname    |
      | mystyle     | My Style       |
    And the following "format_minimoodlewall > tags" exist:
      | name    | activitytype1 |
      | Reading | page          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         | activityprofile |
      | Test Course | TC1       | minimoodlewall | mystyle         |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 0       |
    And I log in as "admin"
    When I am on "Test Course" course homepage
    Then ".minimoodlewall-activities.minimoodlewall-style-mystyle" "css_element" should exist

  @javascript
  Scenario: Teachers cannot access profile management
    Given the following "courses" exist:
      | fullname    | shortname | format         |
      | Test Course | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And I log in as "teacher1"
    And I am on "Test Course" course homepage
    Then I should not see "Site administration"
