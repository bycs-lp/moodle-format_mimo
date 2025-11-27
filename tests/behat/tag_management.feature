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
    Given I log in as "admin1"
    And I am on site homepage
    And I navigate to "Plugins > Course formats > Minimal Moodle Wall > Tag Management" in site administration
    When I click on "Create tagset" "button"
    And I set the following fields to these values:
      | Name        | Science Topics |
      | Description | Tags for science courses |
    And I press "Save"
    Then I should see "Science Topics"
    And I should see "Tags for science courses"

  @javascript
  Scenario: Admin can add tags to a tag set
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description            |
      | Science Topics | Tags for science       |
    And I log in as "admin1"
    And I am on site homepage
    And I navigate to "Plugins > Course formats > Minimal Moodle Wall > Tag Management" in site administration
    When I click on "Manage tags" "link" in the "Science Topics" "table_row"
    And I click on "Create tag" "button"
    And I set the following fields to these values:
      | Name             | Biology      |
      | Description      | Life science |
      | Activity type 1  | assign       |
      | Activity type 2  | quiz         |
    And I press "Save"
    Then I should see "Biology"
    And I should see "Life science"

  @javascript
  Scenario: Admin can edit existing tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description      |
      | Science Topics | Tags for science |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | description  | activitytype1 | activitytype2 |
      | Science Topics | Biology | Life science | assign        | quiz          |
    And I log in as "admin1"
    And I am on site homepage
    And I navigate to "Plugins > Course formats > Minimal Moodle Wall > Tag Management" in site administration
    When I click on "Manage tags" "link" in the "Science Topics" "table_row"
    And I click on "Edit" "link" in the "Biology" "table_row"
    And I set the following fields to these values:
      | Name        | Advanced Biology     |
      | Description | Advanced life science |
    And I press "Save"
    Then I should see "Advanced Biology"
    And I should see "Advanced life science"
    And I should not see "Biology" in the ".tag-list" "css_element"

  @javascript
  Scenario: Admin can delete tags
    Given the following "format_minimoodlewall > tagsets" exist:
      | name           | description      |
      | Science Topics | Tags for science |
    And the following "format_minimoodlewall > tags" exist:
      | tagset         | name    | description  | activitytype1 | activitytype2 |
      | Science Topics | Biology | Life science | assign        | quiz          |
      | Science Topics | Physics | Matter       | assign        | forum         |
    And I log in as "admin1"
    And I am on site homepage
    And I navigate to "Plugins > Course formats > Minimal Moodle Wall > Tag Management" in site administration
    When I click on "Manage tags" "link" in the "Science Topics" "table_row"
    And I click on "Delete" "link" in the "Physics" "table_row"
    And I press "Yes"
    Then I should see "Biology"
    And I should not see "Physics"

  @javascript
  Scenario: Teachers cannot access tag management
    Given I log in as "teacher1"
    And I am on site homepage
    Then I should not see "Site administration"
