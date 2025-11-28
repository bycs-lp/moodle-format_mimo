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
    When I click on "Create Tag Set" "link"
    And I set the following fields to these values:
      | Name        | Science Topics |
      | Description | Tags for science courses |
    And I press "Save"
    Then I should see "Science Topics"
    And I should see "Tags for science courses"

  @javascript @_file_upload
  Scenario: Admin can add tags to a tag set
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description            |
      | Science Topics | Tags for science       |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    And I wait until "Science Topics" "text" exists
    When I click on "Create Tag" "link" in the "//div[contains(@class, 'card')]//h3[contains(text(), 'Science Topics')]//ancestor::div[contains(@class, 'card')]" "xpath_element"
    And I set the following fields to these values:
      | Name                              | Biology      |
      | Description                       | Life science |
      | First Suggested Activity Type     | assign       |
      | Second Suggested Activity Type    | quiz         |
    And I upload "course/format/minimoodlewall/pix/tags/lab.svg" file to "Card Image" filemanager
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Biology"

  @javascript @_file_upload
  Scenario: Admin can edit existing tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description      |
      | Science Topics | Tags for science |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | description  | activitytype1 | activitytype2 |
      | Science Topics | Biology | Life science | assign        | quiz          |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    When I click on "Edit Tag" "link" in the "//tr[contains(., 'Biology')]" "xpath_element"
    And I set the following fields to these values:
      | Name        | Advanced Biology     |
      | Description | Advanced life science |
    And I upload "course/format/minimoodlewall/pix/tags/data.svg" file to "Card Image" filemanager
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Advanced Biology"

  @javascript
  Scenario: Admin can delete tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description      |
      | Science Topics | Tags for science |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | description  | activitytype1 | activitytype2 |
      | Science Topics | Biology | Life science | assign        | quiz          |
      | Science Topics | Physics | Matter       | assign        | forum         |
    And I log in as "admin"
    And I am on site homepage
    And I visit "/course/format/minimoodlewall/tag_management.php"
    When I click on "Delete Tag" "link" in the "//tr[contains(., 'Physics')]" "xpath_element"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Biology"
    And I should not see "Physics"

  @javascript
  Scenario: Teachers cannot access tag management
    Given I log in as "teacher1"
    And I am on site homepage
    Then I should not see "Site administration"
