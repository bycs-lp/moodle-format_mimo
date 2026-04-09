@format @format_mimo @javascript
Feature: Done visibility state for activities in mimo format
  In order to mark activities as completed for the course
  As a teacher
  I need to set activities as done and see them greyed out

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > tags" exist:
      | name    | activitytype1 | activitytype2 |
      | Reading | page          | book          |
    And the following "format_mimo > courses" exist:
      | fullname    | shortname | format |
      | Done Course | DC1       | mimo   |
    And the following "format_mimo > activities" exist:
      | activity | name    | intro      | course | section | tag     |
      | page     | Page 1  | First page | DC1    | 1       | Reading |
      | page     | Page 2  | Other page | DC1    | 1       | Reading |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | DC1    | editingteacher |
      | student1 | DC1    | student        |

  Scenario: Done option appears in the visibility dropdown
    Given I log in as "teacher1"
    When I am on "Done Course" course homepage with editing mode on
    And I wait until ".mimo-card" "css_element" exists
    And I click on ".visibility-icon-control .dropdown-toggle" "css_element" in the "Page 1" "activity"
    Then I should see "Done"
    And I should see "Show on course page"

  Scenario: Marking an activity as done adds the done class
    Given I log in as "teacher1"
    And I am on "Done Course" course homepage with editing mode on
    And I wait until ".mimo-card" "css_element" exists
    When I click on ".visibility-icon-control .dropdown-toggle" "css_element" in the "Page 1" "activity"
    And I click on "[data-value='done']" "css_element"
    And I wait until ".activity.mimo-done" "css_element" exists
    Then "li.activity.mimo-done" "css_element" should exist in the "Page 1" "activity"

  Scenario: Done activity is still visible to students
    Given I log in as "teacher1"
    And I am on "Done Course" course homepage with editing mode on
    And I wait until ".mimo-card" "css_element" exists
    And I click on ".visibility-icon-control .dropdown-toggle" "css_element" in the "Page 1" "activity"
    And I click on "[data-value='done']" "css_element"
    And I wait until ".activity.mimo-done" "css_element" exists
    And I log out
    When I log in as "student1"
    And I am on "Done Course" course homepage
    And I wait until ".mimo-card" "css_element" exists
    Then I should see "Page 1"
    And I should see "Page 2"
