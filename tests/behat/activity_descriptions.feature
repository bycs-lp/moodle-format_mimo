@format @format_minimoodlewall @javascript
Feature: Activity type descriptions with tags
  In order to provide better guidance for activity types
  As an admin
  I need to manage activity descriptions and assign tags to them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | selectedtags    | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Reading,Practice | 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |

  @javascript
  Scenario: Admin can manage activity descriptions with description tags
    Given I log in as "admin"
    And I am on site homepage
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Activity Descriptions" in site administration
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Description Tag Management" in site administration
    Then "[data-region='tag-management']" "css_element" should exist
    And I click on "[data-testid='create-tag-button']" "css_element"
    And I set the following fields to these values:
      | Name  | Homework |
      | Color | #ff6b6b  |
    And I press "Save"
    And I wait until the page is ready
    Then "[data-region='tag-list-table']" "css_element" should exist
    And "[data-testid='tag-row'][data-tag-name='Homework']" "css_element" should exist
    When I navigate to "Plugins > Course formats > Mini Moodle Wall > Activity Descriptions" in site administration
    And I set the field "description_assign" to "Create homework assignments for students"
    And I set the field "desctag_assign" to "Homework"
    And I press "Save"
    And I wait until the page is ready

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
    And I click on "[data-tag-name='Practice']" "css_element" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then "[data-testid='activity-card'][data-activity-type='assign'] [data-testid='activity-tag']" "css_element" should exist
    And "[data-testid='activity-card'][data-activity-type='quiz'] [data-testid='activity-tag']" "css_element" should exist
    And "[data-testid='activity-tag'][data-tag-name='Homework']" "css_element" should exist
    And "[data-testid='activity-tag'][data-tag-name='Classwork']" "css_element" should exist

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
    And I click on "[data-tag-name='Practice']" "css_element" in the ".format-minimoodlewall-tagchooser .dropdown-menu" "css_element"
    And I wait until ".modal-dialog" "css_element" exists
    Then "[data-testid='activity-card'][data-activity-type='assign'] [data-testid='activity-tag'][data-tag-name='Homework']" "css_element" should exist
    And the "style" attribute of "[data-testid='activity-card'][data-activity-type='assign'] [data-testid='activity-tag']" "css_element" should contain "#ff6b6b"
