@format @format_mimo @javascript
Feature: Activity profile variants in mimo format
  In order to customize course appearance
  As a teacher
  I need to select different activity profiles

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > profiles" exist:
      | name    | displayname |
      | classic | Classic     |
      | light   | Light       |
      | dark    | Dark        |
    And the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 |
      | Reading | page          | book          |

  @javascript
  Scenario: Admin can set activity profile when creating course
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I set the following fields to these values:
      | Course full name  | Test Course 1       |
      | Course short name | TC1                 |
      | Format            | mimo wall |
    And I expand all fieldsets
    And I set the field "Activity Profile" to "Classic"
    And I press "Save and display"
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 1       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC1    | student |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    Then ".mimo-activities.mimo-style-classic" "css_element" should exist

  @javascript
  Scenario: Course retains selected activity profile
    Given the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | activityprofile |
      | Test Course 2 | TC2       | mimo | light           |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC2    | 1       |
    And I log in as "admin"
    When I am on "Test Course 2" course homepage
    Then ".mimo-activities.mimo-style-light" "css_element" should exist

  @javascript
  Scenario: Background design override adds CSS class to activity wall
    Given the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | activityprofile | backgrounddesign |
      | Test Course 3 | TC3       | mimo | classic         | darkmode         |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC3    | 1       |
    And I log in as "admin"
    When I am on "Test Course 3" course homepage
    Then ".mimo-bgdesign-wrapper.mimo-bgdesign-darkmode" "css_element" should exist
    And ".mimo-activities.mimo-bgdesign-darkmode" "css_element" should exist

  @javascript
  Scenario: New course gets primary-school background design by default
    Given the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | activityprofile |
      | Test Course 4 | TC4       | mimo | classic         |
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC4    | 1       |
    And I log in as "admin"
    When I am on "Test Course 4" course homepage
    Then ".mimo-bgdesign-wrapper.mimo-bgdesign-primary-school" "css_element" should exist
    And ".mimo-activities.mimo-bgdesign-primary-school" "css_element" should exist

  @javascript
  Scenario: Admin can set background design when creating course
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Courses > Add a new course" in site administration
    When I set the following fields to these values:
      | Course full name  | Test Course 5       |
      | Course short name | TC5                 |
      | Format            | mimo wall |
    And I expand all fieldsets
    And I set the field "Activity Profile" to "Classic"
    And I set the field "Background design" to "Darkmode"
    And I press "Save and display"
    And the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC5    | 1       |
    When I am on "Test Course 5" course homepage
    Then ".mimo-bgdesign-wrapper.mimo-bgdesign-darkmode" "css_element" should exist
    And ".mimo-activities.mimo-bgdesign-darkmode" "css_element" should exist
