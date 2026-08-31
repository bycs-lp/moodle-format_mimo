@format @format_mimo @javascript
Feature: Empty wall message
  As a course participant
  I want guidance when a mimo wall has no activities
  So that teachers know how to start and students know the course is empty

  Background:
    Given I change window size to "large"
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > courses" exist:
      | fullname     | shortname | format |
      | Empty Course | EC1       | mimo   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | EC1    | editingteacher |
      | student1 | EC1    | student        |

  Scenario: Teacher sees message and CTA on an empty wall and can enable editing
    Given I log in as "teacher1"
    When I am on "Empty Course" course homepage
    Then I should see "This wall has no activities yet."
    And "Turn editing on to add activities" "button" should exist
    When I click on "Turn editing on to add activities" "button"
    Then ".mimo-emptywall" "css_element" should not exist
    And ".btn.add-content" "css_element" should exist

  Scenario: Student sees info text on an empty wall
    Given I log in as "student1"
    When I am on "Empty Course" course homepage
    Then I should see "There is nothing here yet"
    And "Turn editing on to add activities" "button" should not exist

  Scenario: Student sees info text when all activities are hidden
    Given the following "activities" exist:
      | activity | name        | course | section | visible |
      | page     | Hidden Page | EC1    | 1       | 0       |
    And I log in as "student1"
    When I am on "Empty Course" course homepage
    Then I should see "There is nothing here yet"

  Scenario: No empty wall message when activities are visible
    Given the following "activities" exist:
      | activity | name         | course | section |
      | page     | Visible Page | EC1    | 1       |
    And I log in as "teacher1"
    When I am on "Empty Course" course homepage
    Then ".mimo-emptywall" "css_element" should not exist
    And I should not see "This wall has no activities yet."
