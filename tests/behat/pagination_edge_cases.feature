@format @format_mimo @javascript
Feature: Pagination edge cases in mimo format
  In order to handle various activity counts
  As a student
  I need pagination to adapt to filtering and small activity counts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_mimo > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_mimo > courses" exist:
      | fullname      | shortname | format         | enablefiltering |
      | Test Course 1 | TC1       | mimo | 1               |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC1    | student |

  @javascript
  Scenario: Pagination works with filtering
    Given the following "format_mimo > activities" exist:
      | activity | name       | intro   | course | section | tag      |
      | page     | Reading 1  | First   | TC1    | 1       | Reading  |
      | page     | Reading 2  | Second  | TC1    | 1       | Reading  |
      | page     | Reading 3  | Third   | TC1    | 1       | Reading  |
      | page     | Reading 4  | Fourth  | TC1    | 1       | Reading  |
      | page     | Reading 5  | Fifth   | TC1    | 1       | Reading  |
      | assign   | Practice 1 | Sixth   | TC1    | 1       | Practice |
      | assign   | Practice 2 | Seventh | TC1    | 1       | Practice |
      | assign   | Practice 3 | Eighth  | TC1    | 1       | Practice |
      | assign   | Practice 4 | Ninth   | TC1    | 1       | Practice |
      | assign   | Practice 5 | Tenth   | TC1    | 1       | Practice |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".mimo-activities .mimo-card" "css_element" exists
    And I wait until ".mimo-filterbar" "css_element" exists
    And I wait until ".mimo-filterbar-button.is-empty" "css_element" does not exist
    And I click on "Practice" "button" in the ".mimo-filterbar" "css_element"
    Then I should see "Practice 1"
    And I should not see "Reading 1" in the ".mimo-activities" "css_element"

  @javascript
  Scenario: No pagination controls when 8 or fewer activities
    Given the following "activities" exist:
      | activity | name   | intro      | course | section |
      | page     | Page 1 | First page | TC1    | 1       |
      | page     | Page 2 | Second     | TC1    | 1       |
      | page     | Page 3 | Third      | TC1    | 1       |
      | page     | Page 4 | Fourth     | TC1    | 1       |
      | page     | Page 5 | Fifth      | TC1    | 1       |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".mimo-activities .mimo-card" "css_element" exists
    Then I should see "Page 1"
    And I should see "Page 5"
    And I wait until ".mimo-navigation.is-booting" "css_element" does not exist
    And ".mimo-navigation.is-visible" "css_element" should not exist
