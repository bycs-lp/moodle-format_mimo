@format @format_mimo @javascript
Feature: Tag management in mimo format
  In order to organize activities with visual tags
  As an admin
  I need to create and manage tags per tagset

  Background:
    Given I change window size to "large"
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | admin1   | Admin     | User     | admin1@example.com   |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | admin1 | manager | System       |           |
    And the following "format_mimo > profiles" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |

  @javascript @_file_upload
  Scenario: Admin can create a new tag in the base set
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I click on "[data-testid='create-tag-button']" "css_element"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Tag Name        | Biology |
      | Activity Type 1 | assign  |
      | Activity Type 2 | quiz    |
    And I upload "course/format/mimo/pix/tags/base_receive.png" file to "Card Image (Base tags)" filemanager
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Biology"

  @javascript @_file_upload
  Scenario: Admin can edit existing tags in the active set
    Given the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 | enabledin |
      | Biology | assign        | quiz          | base      |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    And I click on "[data-testid='edit-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Biology']" "css_element"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Tag Name | Advanced Biology |
    And I upload "course/format/mimo/pix/tags/base_inform.png" file to "Card Image (Base tags)" filemanager
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Advanced Biology"

  @javascript
  Scenario: Tag created in one set is disabled in other sets
    Given the following "format_mimo > tags" exist:
      | name     | activitytype1 | enabledin |
      | Setlocal | assign        | classic   |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    And I click on "[data-testid='profile-button-classic']" "css_element"
    Then I should not see "Disabled" in the "[data-testid='tag-row'][data-tag-name='Setlocal']" "css_element"
    When I click on "[data-testid='profile-button-base']" "css_element"
    Then I should see "Disabled" in the "[data-testid='tag-row'][data-tag-name='Setlocal']" "css_element"

  @javascript
  Scenario: Eye toggle enables and disables a tag in the active set only
    Given the following "format_mimo > tags" exist:
      | name    | activitytype1 | enabledin |
      | Physics | assign        | classic   |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    Then I should see "Disabled" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    When I click on "[data-testid='toggle-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    And I wait until the page is ready
    Then I should not see "Disabled" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    When I click on "[data-testid='toggle-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    And I wait until the page is ready
    Then I should see "Disabled" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"

  @javascript
  Scenario: Delete is only possible once a tag is disabled in every set
    Given the following "format_mimo > tags" exist:
      | name      | activitytype1 | enabledin |
      | Chemistry | assign        | base      |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    Then "[data-testid='delete-tag-button']" "css_element" should not exist in the "[data-testid='tag-row'][data-tag-name='Chemistry']" "css_element"
    When I click on "[data-testid='toggle-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Chemistry']" "css_element"
    And I wait until the page is ready
    Then "[data-testid='delete-tag-button']" "css_element" should exist in the "[data-testid='tag-row'][data-tag-name='Chemistry']" "css_element"
    When I click on "[data-testid='delete-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Chemistry']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should not see "Chemistry"

  @javascript
  Scenario: Admin can delete tags that are disabled everywhere
    Given the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 | enabledin |
      | Biology | assign        | quiz          |           |
      | Physics | assign        | forum         |           |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    And I click on "[data-testid='delete-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Biology"
    And I should not see "Physics"

  @javascript
  Scenario: Base is the default activity profile in course settings
    Given the following "format_mimo > courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And I log in as "admin"
    When I am on the "C1" "course editing" page
    And I expand all fieldsets
    Then the field "Activity Profile" matches value "Base tags"

  @javascript
  Scenario: Teachers cannot access tag management
    Given I log in as "teacher1"
    And I am on site homepage
    Then I should not see "Site administration"
