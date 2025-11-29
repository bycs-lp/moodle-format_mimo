@format @format_minimoodlewall @javascript
Feature: Activity type descriptions with tags
  In order to provide better guidance for activity types
  As an admin
  I need to manage activity descriptions and assign tags to them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tagsets" exist:
      | name         |
      | Chooser Tags |
    And the following "format_minimoodlewall > tags" exist:
      | tagset       | name     | activitytype1 | activitytype2 |
      | Chooser Tags | Reading  | page          | book          |
      | Chooser Tags | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Chooser Tags | 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Admin can manage activity descriptions with description tags
    Given I log in as "admin"
    And I am on site homepage
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Activity Descriptions" in site administration
    Then I should see "Activity Descriptions"
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Description Tag Management" in site administration
    Then I should see "Description Tag Management"
    And I click on "Create Description Tag" "button"
    And I set the following fields to these values:
      | Name  | Homework |
      | Color | #ff6b6b  |
    And I click on "Save changes" "button"
    And I wait until the page is ready
    Then I should see "Homework"
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Activity Descriptions" in site administration
    And I should see "Assignment"
    And I set the field "description_assign" to "Create homework assignments for students"
    And I set the field "desctag_assign" to "Homework"
    And I click on "Save changes" "button"
    Then I should see "Changes saved"

  @javascript
  Scenario: Teacher sees description tag pill on activity cards in chooser modal
    Given the following "format_minimoodlewall > description tags" exist:
      | name     | color   |
      | Homework | #ff6b6b |
      | Classwork| #4ecdc4 |
    And the following "format_minimoodlewall > activity descriptions" exist:
      | activitytype | description                              | desctag   |
      | assign       | Create homework assignments for students | Homework  |
      | quiz         | Create tests and quizzes for classwork   | Classwork |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "Practice" "link" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then ".mmw-activity-card-tag" "css_element" should exist
    And I should see "Homework" in the ".mmw-activity-card[data-activity-type='assign'] .mmw-activity-card-tag" "css_element"
    And I should see "Classwork" in the ".mmw-activity-card[data-activity-type='quiz'] .mmw-activity-card-tag" "css_element"

  @javascript
  Scenario: Description tag pills have correct background colors
    Given the following "format_minimoodlewall > description tags" exist:
      | name     | color   |
      | Homework | #ff6b6b |
    And the following "format_minimoodlewall > activity descriptions" exist:
      | activitytype | description                              | desctag  |
      | assign       | Create homework assignments for students | Homework |
    When I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I click on "button[data-action='open-tagchooser']" "css_element"
    And I wait until ".format-minimoodlewall-tagchooser .dropdown-menu.show" "css_element" exists
    And I click on "Practice" "link" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then the "style" attribute of ".mmw-activity-card[data-activity-type='assign'] .mmw-activity-card-tag" "css_element" should contain "#ff6b6b"
