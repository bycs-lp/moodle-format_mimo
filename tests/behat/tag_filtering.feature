@format @format_minimoodlewall @javascript
Feature: Tag filtering in minimoodlewall format
  In order to find specific activities
  As a student
  I need to filter activities by tags

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_minimoodlewall > tagsets" exist:
      | name         | description     |
      | Default Tags | Default tag set |
    And the following "format_minimoodlewall > tags" exist:
      | tagset       | name     | description       | activitytype1 | activitytype2 |
      | Default Tags | Reading  | Reading materials | page          | book          |
      | Default Tags | Practice | Practice tasks    | assign        | quiz          |
      | Default Tags | Discuss  | Discussion topics | forum         | chat          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Default Tags | 1               |
    And the following "activities" exist:
      | activity | name         | intro            | course | section |
      | assign   | Assignment 1 | First assignment | TC1    | 0       |
      | quiz     | Quiz 1       | First quiz       | TC1    | 0       |
      | page     | Page 1       | First page       | TC1    | 0       |
      | forum    | Forum 1      | First forum      | TC1    | 0       |
      | book     | Book 1       | First book       | TC1    | 0       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm           | tag      |
      | Assignment 1 | Practice |
      | Quiz 1       | Practice |
      | Page 1       | Reading  |
      | Forum 1      | Discuss  |
      | Book 1       | Reading  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student |

  @javascript
  Scenario: Filter bar is visible when filtering is enabled
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".minimoodlewall-filterbar" "css_element" should exist
    And ".minimoodlewall-filterbar button" "css_element" should exist
    And I should see "Reading" in the ".minimoodlewall-filterbar" "css_element"
    And I should see "Practice" in the ".minimoodlewall-filterbar" "css_element"
    And I should see "Discuss" in the ".minimoodlewall-filterbar" "css_element"

  @javascript
  Scenario: Filter bar shows tag data attributes
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".minimoodlewall-filterbar" "css_element" should exist
    And "button[title*='Practice']" "css_element" should exist
    And "button[data-hasactivities='1']" "css_element" should exist

  @javascript
  Scenario: Student can filter activities by tag
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "Practice" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should not see "Page 1" in the ".minimoodlewall-activities" "css_element"
    And I should not see "Forum 1" in the ".minimoodlewall-activities" "css_element"
    And I should not see "Book 1" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Student can clear filter to see all activities
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "Reading" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Page 1"
    And I should see "Book 1"
    And I should not see "Assignment 1" in the ".minimoodlewall-activities" "css_element"
    When I click on "Reading" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should see "Page 1"
    And I should see "Forum 1"
    And I should see "Book 1"

  @javascript
  Scenario: Active filter button is highlighted
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "Discuss" "button" in the ".minimoodlewall-filterbar" "css_element"
    And I wait "1" seconds
    Then "button.minimoodlewall-filterbar-button.is-active" "css_element" should exist
    And I should see "Forum 1"

  @javascript
  Scenario: Filter bar is not visible when filtering is disabled
    Given the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 2 | TC2       | minimoodlewall | Default Tags | 0               |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC2    | student |
    When I log in as "student1"
    And I am on "Test Course 2" course homepage
    Then ".minimoodlewall-filterbar" "css_element" should not exist

  @javascript
  Scenario: Teacher sees filter bar in view mode
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    Then ".minimoodlewall-filterbar" "css_element" should exist
    And I should see "Reading"
    And I should see "Practice"
    When I click on "Practice" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
