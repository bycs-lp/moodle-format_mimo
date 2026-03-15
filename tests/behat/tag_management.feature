@format @format_mimo @javascript
Feature: Tag management in mimo format
  In order to organize activities with visual tags
  As an admin
  I need to create and manage tags

  Background:
    Given the following "users" exist:
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
  Scenario: Admin can create a new tag with profile-specific images
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I click on "[data-testid='create-tag-button']" "css_element"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Name                              | Biology      |
      | First Suggested Activity Type     | assign       |
      | Second Suggested Activity Type    | quiz         |
    And I upload "course/format/mimo/pix/tags/explore_base.svg" file to "Card Image" filemanager
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Biology"

  @javascript @_file_upload
  Scenario: Admin can edit existing tags
    Given the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 |
      | Biology | assign        | quiz          |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/mimo/tag_management.php"
    When I wait until the page is ready
    And I click on "[data-testid='edit-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Biology']" "css_element"
    And I wait until "[data-region='modal']" "css_element" exists
    And I set the following fields to these values:
      | Name        | Advanced Biology     |
    And I upload "course/format/mimo/pix/tags/read_base.svg" file to "Card Image" filemanager
    And I click on "Save changes" "button" in the "[data-region='modal']" "css_element"
    And I wait until the page is ready
    Then I should see "Advanced Biology"

  @javascript
  Scenario: Admin can delete tags
    Given the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 |
      | Biology | assign        | quiz          |
      | Physics | assign        | forum         |
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
  Scenario: Teachers cannot access tag management
    Given I log in as "teacher1"
    And I am on site homepage
    Then I should not see "Site administration"
