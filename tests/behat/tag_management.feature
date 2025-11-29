@format @format_minimoodlewall @javascript
Feature: Tag management in minimoodlewall format
  In order to organize activities with visual tags
  As an admin
  I need to create and manage tag sets and tags

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | admin1   | Admin     | User     | admin1@example.com   |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | admin1 | manager | System       |           |

  @javascript
  Scenario: Admin can create a new tag set
    Given I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    When I click on "[data-testid='create-tagset-button']" "css_element"
    And I set the following fields to these values:
      | Name        | Science Topics |
    And I press "Save"
    Then I should see "Science Topics"

  @javascript @_file_upload
  Scenario: Admin can add tags to a tag set
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           |
      | Science Topics |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    And I wait until "Science Topics" "text" exists
    When I click on "[data-testid='create-tag-button']" "css_element" in the "[data-testid='tagset-card'][data-tagset-name='Science Topics']" "css_element"
    And I set the following fields to these values:
      | Name                              | Biology      |
      | First Suggested Activity Type     | assign       |
      | Second Suggested Activity Type    | quiz         |
    And I upload "course/format/minimoodlewall/pix/tags/lab.svg" file to "Card Image" filemanager
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Biology"

  @javascript @_file_upload
  Scenario: Admin can edit existing tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           |
      | Science Topics |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | activitytype1 | activitytype2 |
      | Science Topics | Biology | assign        | quiz          |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    When I click on "[data-testid='edit-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Biology']" "css_element"
    And I set the following fields to these values:
      | Name        | Advanced Biology     |
    And I upload "course/format/minimoodlewall/pix/tags/data.svg" file to "Card Image" filemanager
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Advanced Biology"

  @javascript
  Scenario: Admin can delete tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           |
      | Science Topics |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | activitytype1 | activitytype2 |
      | Science Topics | Biology | assign        | quiz          |
      | Science Topics | Physics | assign        | forum         |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    When I click on "[data-testid='delete-tag-button']" "css_element" in the "[data-testid='tag-row'][data-tag-name='Physics']" "css_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Biology"
    And I should not see "Physics"

  @javascript
  Scenario: Teachers cannot access tag management
    Given I log in as "teacher1"
    And I am on site homepage
    Then I should not see "Site administration"
