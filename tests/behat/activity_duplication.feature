@format @format_minimoodlewall @javascript
Feature: Bulk duplicate activity visibility in minimoodlewall format
  In order to duplicate activities in bulk
  As a teacher
  I need newly duplicated cards to be visible in bulk editing mode

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "format_minimoodlewall > tags" exist:
      | name     | activitytype1 | activitytype2 |
      | Reading  | page          | book          |
      | Practice | assign        | quiz          |
    And the following "format_minimoodlewall > courses" exist:
      | fullname    | shortname | format         |
      | Test Course | TC1       | minimoodlewall |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
    And the following "activities" exist:
      | activity | name    | intro    | course | section |
      | page     | Page 1  | Intro 1  | TC1    | 0       |
      | page     | Page 2  | Intro 2  | TC1    | 0       |
      | page     | Page 3  | Intro 3  | TC1    | 0       |
      | page     | Page 4  | Intro 4  | TC1    | 0       |
      | page     | Page 5  | Intro 5  | TC1    | 0       |
      | page     | Page 6  | Intro 6  | TC1    | 0       |
      | page     | Page 7  | Intro 7  | TC1    | 0       |
      | page     | Page 8  | Intro 8  | TC1    | 0       |
      | page     | Page 9  | Intro 9  | TC1    | 0       |
      | page     | Page 10 | Intro 10 | TC1    | 0       |
      | page     | Page 11 | Intro 11 | TC1    | 0       |
      | page     | Page 12 | Intro 12 | TC1    | 0       |
      | page     | Page 13 | Intro 13 | TC1    | 0       |
      | page     | Page 14 | Intro 14 | TC1    | 0       |
      | page     | Page 15 | Intro 15 | TC1    | 0       |
      | page     | Page 16 | Intro 16 | TC1    | 0       |
      | page     | Page 17 | Intro 17 | TC1    | 0       |
      | page     | Page 18 | Intro 18 | TC1    | 0       |
      | page     | Page 19 | Intro 19 | TC1    | 0       |
      | page     | Page 20 | Intro 20 | TC1    | 0       |

  Scenario: Bulk duplicate renders the new card visible in bulk editing mode
    Given I log in as "teacher1"
    And I am on "Test Course" course homepage with editing mode on
    And I wait until ".minimoodlewall-activities .minimoodlewall-card" "css_element" exists
    And I click on "Bulk actions" "button"
    And I should see "0 selected" in the "sticky-footer" "region"
    And I wait "1" seconds
    When I click on "Select activity Page 17" "checkbox"
    And I should see "1 selected" in the "sticky-footer" "region"
    And I click on "Duplicate activities" "button" in the "sticky-footer" "region"
    And I wait "3" seconds
    Then I should see "Page 17 (copy)"
