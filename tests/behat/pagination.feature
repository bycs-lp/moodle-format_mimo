@format @format_minimoodlewall @javascript
Feature: Activity pagination in minimoodlewall format
  In order to navigate large courses easily
  As a student
  I need pagination controls for activities

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_minimoodlewall > tagsets" exist:
      | name         |
      | Default Tags |
    And the following "format_minimoodlewall > tags" exist:
      | tagset       | name     | activitytype1 | activitytype2 |
      | Default Tags | Reading  | page          | book          |
      | Default Tags | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         | tagsetid     | enablefiltering |
      | Test Course 1 | TC1       | minimoodlewall | Default Tags | 1               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
      | student1 | TC1    | student        |

  @javascript
  Scenario: Pagination controls appear when there are more than 8 activities
    Given the following "activities" exist:
      | activity | name         | intro       | course | section |
      | page     | Page 1       | First page  | TC1    | 1       |
      | page     | Page 2       | Second page | TC1    | 1       |
      | page     | Page 3       | Third page  | TC1    | 1       |
      | page     | Page 4       | Fourth page | TC1    | 1       |
      | page     | Page 5       | Fifth page  | TC1    | 1       |
      | page     | Page 6       | Sixth page  | TC1    | 1       |
      | page     | Page 7       | Seventh page| TC1    | 1       |
      | page     | Page 8       | Eighth page | TC1    | 1       |
      | page     | Page 9       | Ninth page  | TC1    | 1       |
      | page     | Page 10      | Tenth page  | TC1    | 1       |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait until ".minimoodlewall-navigation.is-visible" "css_element" exists
    Then ".minimoodlewall-navigation.is-visible" "css_element" should exist
    And "button#minimoodlewall-prev" "css_element" should exist
    And "button#minimoodlewall-next" "css_element" should exist

  @javascript
  Scenario: Student can navigate between pages
    Given the following "activities" exist:
      | activity | name    | intro      | course | section |
      | page     | Page 1  | First page | TC1    | 1       |
      | page     | Page 2  | Page two   | TC1    | 1       |
      | page     | Page 3  | Page three | TC1    | 1       |
      | page     | Page 4  | Page four  | TC1    | 1       |
      | page     | Page 5  | Page five  | TC1    | 1       |
      | page     | Page 6  | Page six   | TC1    | 1       |
      | page     | Page 7  | Page seven | TC1    | 1       |
      | page     | Page 8  | Page eight | TC1    | 1       |
      | page     | Page 9  | Page nine  | TC1    | 1       |
      | page     | Page 10 | Page ten   | TC1    | 1       |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait until ".minimoodlewall-navigation.is-visible" "css_element" exists
    Then I should see "Page 1"
    And I should see "Page 8"
    And I wait until "button#minimoodlewall-next:not([disabled])" "css_element" exists
    When I click on "button#minimoodlewall-next" "css_element"
    And I wait until ".minimoodlewall-activities .col-12:not([style*='display: none'])" "css_element" exists
    Then I should see "Page 9"
    And I should see "Page 10"
    And I should not see "Page 1" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Previous button works correctly
    Given the following "activities" exist:
      | activity | name    | intro      | course | section |
      | page     | Page 1  | First page | TC1    | 1       |
      | page     | Page 2  | Page two   | TC1    | 1       |
      | page     | Page 3  | Page three | TC1    | 1       |
      | page     | Page 4  | Page four  | TC1    | 1       |
      | page     | Page 5  | Page five  | TC1    | 1       |
      | page     | Page 6  | Page six   | TC1    | 1       |
      | page     | Page 7  | Page seven | TC1    | 1       |
      | page     | Page 8  | Page eight | TC1    | 1       |
      | page     | Page 9  | Page nine  | TC1    | 1       |
      | page     | Page 10 | Page ten   | TC1    | 1       |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait until ".minimoodlewall-navigation.is-visible" "css_element" exists
    And I wait until "button#minimoodlewall-next:not([disabled])" "css_element" exists
    And I click on "button#minimoodlewall-next" "css_element"
    And I wait until ".minimoodlewall-activities .col-12:not([style*='display: none'])" "css_element" exists
    Then I should see "Page 9"
    And I wait until "button#minimoodlewall-prev:not([disabled])" "css_element" exists
    When I click on "button#minimoodlewall-prev" "css_element"
    And I wait until ".minimoodlewall-activities .col-12:not([style*='display: none'])" "css_element" exists
    Then I should see "Page 1"
    And I should see "Page 8"
    And I should not see "Page 9" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Pagination works with filtering
    Given the following "activities" exist:
      | activity | name         | intro       | course | section |
      | page     | Reading 1    | First       | TC1    | 1       |
      | page     | Reading 2    | Second      | TC1    | 1       |
      | page     | Reading 3    | Third       | TC1    | 1       |
      | page     | Reading 4    | Fourth      | TC1    | 1       |
      | page     | Reading 5    | Fifth       | TC1    | 1       |
      | assign   | Practice 1   | Sixth       | TC1    | 1       |
      | assign   | Practice 2   | Seventh     | TC1    | 1       |
      | assign   | Practice 3   | Eighth      | TC1    | 1       |
      | assign   | Practice 4   | Ninth       | TC1    | 1       |
      | assign   | Practice 5   | Tenth       | TC1    | 1       |
    And the following "format_minimoodlewall > cmtags" exist:
      | cm         | course | tag      |
      | Reading 1  | TC1    | Reading  |
      | Reading 2  | TC1    | Reading  |
      | Reading 3  | TC1    | Reading  |
      | Reading 4  | TC1    | Reading  |
      | Reading 5  | TC1    | Reading  |
      | Practice 1 | TC1    | Practice |
      | Practice 2 | TC1    | Practice |
      | Practice 3 | TC1    | Practice |
      | Practice 4 | TC1    | Practice |
      | Practice 5 | TC1    | Practice |
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait until ".minimoodlewall-filterbar" "css_element" exists
    And I wait until ".minimoodlewall-filterbar-button.is-empty" "css_element" does not exist
    And I click on "Practice" "button" in the ".minimoodlewall-filterbar" "css_element"
    Then I should see "Practice 1"
    And I should not see "Reading 1" in the ".minimoodlewall-activities" "css_element"

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
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    Then I should see "Page 1"
    And I should see "Page 5"
    And I wait until ".minimoodlewall-navigation.is-booting" "css_element" does not exist
    And ".minimoodlewall-navigation.is-visible" "css_element" should not exist
