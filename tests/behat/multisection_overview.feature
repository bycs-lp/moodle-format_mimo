@format @format_mimo @javascript
Feature: Multi-section overview in mimo format
  In order to navigate a course with multiple sections
  As a teacher or student
  I need to see an overview of all sections and interact with them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | enablemultisection | numsections |
      | Test Course 1 | TC1       | mimo | 1                  | 3           |
    And the following "format_mimo > activities" exist:
      | activity | name         | intro            | course | section | tag      |
      | page     | Page 1       | First page       | TC1    | 1       | Reading  |
      | assign   | Assignment 1 | First assignment | TC1    | 1       | Practice |
      | page     | Page 2       | Second page      | TC1    | 2       | Reading  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student        |

  Scenario: Overview grid shows section cards
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".mimo-overview-grid" "css_element" should exist
    And I should see "Section 1" in the ".mimo-overview-grid" "css_element"
    And I should see "Section 2" in the ".mimo-overview-grid" "css_element"
    And I should see "Section 3" in the ".mimo-overview-grid" "css_element"

  Scenario: Section card links to wall view
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    When I click on "Section 1" "link" in the ".mimo-overview-grid" "css_element"
    Then I should see "Page 1"
    And I should see "Assignment 1"
    And ".mimo-overview-grid" "css_element" should not exist

  Scenario: Back to overview button returns to overview
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I click on "Section 1" "link" in the ".mimo-overview-grid" "css_element"
    And I should see "Page 1"
    When I click on ".mimo-overview-btn" "css_element"
    Then ".mimo-overview-grid" "css_element" should exist
    And I should see "Section 1" in the ".mimo-overview-grid" "css_element"

  Scenario: Sticky wall remembers last visited section
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I click on "Section 2" "link" in the ".mimo-overview-grid" "css_element"
    And I should see "Page 2"
    When I am on "Test Course 1" course homepage
    Then I should see "Page 2"
    And ".mimo-overview-grid" "css_element" should not exist

  Scenario: Overview button clears sticky wall preference
    Given I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I click on "Section 2" "link" in the ".mimo-overview-grid" "css_element"
    And I should see "Page 2"
    And I am on "Test Course 1" course homepage
    # Should be on section 2 due to sticky wall
    And I should see "Page 2"
    When I click on ".mimo-overview-btn" "css_element"
    Then ".mimo-overview-grid" "css_element" should exist

  Scenario: Delete button visible only in editing mode for teachers
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    Then "[data-action='delete-section']" "css_element" should exist

  Scenario: Delete button not visible for students
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then "[data-action='delete-section']" "css_element" should not exist

  Scenario: Teacher can delete a section
    Given I log in as "teacher1"
    And I am on "Test Course 1" course homepage with editing mode on
    And I should see "Section 3" in the ".mimo-overview-grid" "css_element"
    When I click on "[data-action='delete-section']" "css_element" in the ".mimo-overview-card[data-section-num='3']" "css_element"
    And I click on "Delete section" "button" in the ".modal" "css_element"
    And I wait until ".mimo-overview-card[data-section-num='3']" "css_element" does not exist
    Then I should not see "Section 3" in the ".mimo-overview-grid" "css_element"

  Scenario: Section cards are draggable in editing mode
    Given I log in as "teacher1"
    When I am on "Test Course 1" course homepage with editing mode on
    And I wait until "[data-for='mimo-overview-card']" "css_element" exists
    Then "[data-for='mimo-overview-card'][draggable='true']" "css_element" should exist

  Scenario: Section cards are not draggable for students
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then "[data-for='mimo-overview-card'][draggable='true']" "css_element" should not exist

  Scenario: Mini-tiles appear on cards with activities
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".mimo-overview-card__miniwall" "css_element" should exist in the ".mimo-overview-card[data-section-num='1']" "css_element"
    And ".mimo-overview-card__minitile" "css_element" should exist in the ".mimo-overview-card[data-section-num='1']" "css_element"

  Scenario: Empty section shows placeholder mini-tiles
    Given I log in as "student1"
    When I am on "Test Course 1" course homepage
    Then ".mimo-overview-card__miniwall--placeholder" "css_element" should exist in the ".mimo-overview-card[data-section-num='3']" "css_element"
