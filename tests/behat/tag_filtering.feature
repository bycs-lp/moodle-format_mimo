@format @format_mimo @javascript
Feature: Tag filtering in mimo format
  In order to find specific activities
  As a student
  I need to filter activities by tags

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
      | Discuss  | forum         | chat          |
    And the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | enablefiltering |
      | Test Course 1 | TC1       | mimo | 1               |
    And the following "format_mimo > activities" exist:
      | activity | name         | intro            | course | section | tag      |
      | assign   | Assignment 1 | First assignment | TC1    | 1       | Practice |
      | quiz     | Quiz 1       | First quiz       | TC1    | 1       | Practice |
      | page     | Page 1       | First page       | TC1    | 1       | Reading  |
      | forum    | Forum 1      | First forum      | TC1    | 1       | Discuss  |
      | book     | Book 1       | First book       | TC1    | 1       | Reading  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student |

  @javascript
  Scenario: Filter bar is visible when filtering is enabled
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".mimo-filterbar" "css_element" should exist
    And ".mimo-filterbar button" "css_element" should exist
    And "[data-testid='filter-button'][data-tag-name='Reading']" "css_element" should exist
    And "[data-testid='filter-button'][data-tag-name='Practice']" "css_element" should exist
    And "[data-testid='filter-button'][data-tag-name='Discuss']" "css_element" should exist

  @javascript
  Scenario: Filter bar shows tag data attributes
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".mimo-filterbar" "css_element" should exist
    And "button[title*='Practice']" "css_element" should exist
    And "button[data-hasactivities='1']" "css_element" should exist

  @javascript
  Scenario: Student can filter activities by tag
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "[data-testid='filter-button'][data-tag-name='Practice']" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should not see "Page 1" in the ".mimo-activities" "css_element"
    And I should not see "Forum 1" in the ".mimo-activities" "css_element"
    And I should not see "Book 1" in the ".mimo-activities" "css_element"

  @javascript
  Scenario: Student can clear filter to see all activities
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "[data-testid='filter-button'][data-tag-name='Reading']" "css_element"
    Then I should see "Page 1"
    And I should see "Book 1"
    And I should not see "Assignment 1" in the ".mimo-activities" "css_element"
    When I click on "[data-testid='filter-button'][data-tag-name='Reading']" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
    And I should see "Page 1"
    And I should see "Forum 1"
    And I should see "Book 1"

  @javascript
  Scenario: Active filter button is highlighted
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "[data-testid='filter-button'][data-tag-name='Discuss']" "css_element"
    Then "button.mimo-filterbar-button.is-active" "css_element" should exist
    And I should see "Forum 1"

  @javascript
  Scenario: Filter bar is not visible when filtering is disabled
    Given the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | enablefiltering |
      | Test Course 2 | TC2       | mimo | 0               |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC2    | student |
    When I log in as "student1"
    And I am on "Test Course 2" course homepage
    Then ".mimo-filterbar" "css_element" should not exist

  @javascript
  Scenario: Teacher sees filter bar in view mode
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage
    Then ".mimo-filterbar" "css_element" should exist
    And "[data-testid='filter-button'][data-tag-name='Reading']" "css_element" should exist
    And "[data-testid='filter-button'][data-tag-name='Practice']" "css_element" should exist
    When I click on "[data-testid='filter-button'][data-tag-name='Practice']" "css_element"
    Then I should see "Assignment 1"
    And I should see "Quiz 1"
