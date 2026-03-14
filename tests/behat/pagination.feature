@format @format_minimoodlewall @javascript
Feature: Activity pagination in minimoodlewall format
  In order to navigate large courses easily
  As a student
  I need pagination controls for activities

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname      | shortname | format         |
      | Test Course 1 | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TC1    | student |
    And the following "activities" exist:
      | activity | name    | intro        | course | section |
      | page     | Page 1  | First page   | TC1    | 1       |
      | page     | Page 2  | Second page  | TC1    | 1       |
      | page     | Page 3  | Third page   | TC1    | 1       |
      | page     | Page 4  | Fourth page  | TC1    | 1       |
      | page     | Page 5  | Fifth page   | TC1    | 1       |
      | page     | Page 6  | Sixth page   | TC1    | 1       |
      | page     | Page 7  | Seventh page | TC1    | 1       |
      | page     | Page 8  | Eighth page  | TC1    | 1       |
      | page     | Page 9  | Ninth page   | TC1    | 1       |
      | page     | Page 10 | Tenth page   | TC1    | 1       |

  @javascript
  Scenario: Pagination controls appear when there are more than 8 activities
    When I log in as "student1"
    And I am on "Test Course 1" course homepage
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I wait until ".minimoodlewall-navigation.is-visible" "css_element" exists
    Then ".minimoodlewall-navigation.is-visible" "css_element" should exist
    And "button#minimoodlewall-prev" "css_element" should exist
    And "button#minimoodlewall-next" "css_element" should exist

  @javascript
  Scenario: Student can navigate between pages
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
    And I should not see "Page 2" in the ".minimoodlewall-activities" "css_element"

  @javascript
  Scenario: Previous button works correctly
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
