@block @uon @block_courseimport
Feature: Test block_courseimport function
  In order to import course content
  As a moodle editingteacher in a course
  I need to use course searching page to find imported course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | edt1      | Edt      | edt1@example.com |
    And the following "courses" exist:
      | fullname          | shortname         | category |
      | N12401-UK-SPR1415 | N12401-UK-SPR1415 | 0        |
      | N12401-UK-SPR1314 | N12401-UK-SPR1314 | 0        |
      | P12130-UK-SPR1314 | P12130-UK-SPR1314 | 0        |
      # searching course without EditingTeacher role in it
      | P13140-UK-SPR1415 | P13140-UK-SPR1415 | 0        |
    And the following "course enrolments" exist:
      | user     | course            | role           |
      | teacher1 | N12401-UK-SPR1415 | editingteacher |
      | teacher1 | P12130-UK-SPR1314 | editingteacher |
    And the following "block" exists:
      | blockname       | courseimport      |
      | contextlevel    | Course            |
      | reference       | N12401-UK-SPR1415 |
      | pagetypepattern | course-view-*     |
      | defaultregion   | site-pre          |

  Scenario: Previous year's course is not accessible
    Given I log in as "teacher1"
    And I am on "N12401-UK-SPR1415" course homepage
    When I click on "Course Import" "link" in the "Course Import" "block"
    Then I should see "You do not have permission to import from the modules below."
    And I should see "You should contact the module's owner to ask for the Editing Teacher role if you wish to import from one."
    And I should see "N12401-UK-SPR1314"

  Scenario: Previous year's course is accessible and selected by default
    Given the following "course enrolment" exists:
      | user   | teacher1          |
      | course | N12401-UK-SPR1314 |
      | role   | editingteacher    |
    And I log in as "teacher1"
    And I am on "N12401-UK-SPR1415" course homepage
    When I click on "Course Import" "link" in the "Course Import" "block"
    Then the field "N12401-UK-SPR1314" matches value "1"

  Scenario: Searching does not return courses the user cannot access
    Given I log in as "teacher1"
    And I am on "N12401-UK-SPR1415" course homepage
    And I click on "Course Import" "link" in the "Course Import" "block"
    When I set the following fields to these values:
      | search | P13140 |
    And I press "Search"
    Then I should not see "P13140-UK-SPR1415"

  Scenario: Searching returns courses the user can access
    Given I log in as "teacher1"
    And I am on "N12401-UK-SPR1415" course homepage
    And I click on "Course Import" "link" in the "Course Import" "block"
    When I set the following fields to these values:
      | search | P12130 |
    And I press "Search"
    Then I should see "P12130-UK-SPR1314"
    And the field "P12130-UK-SPR1314" matches value "0"

  Scenario: Can queue an import
    Given the following "course enrolment" exists:
      | user   | teacher1          |
      | course | N12401-UK-SPR1314 |
      | role   | editingteacher    |
    And I log in as "teacher1"
    And I am on "N12401-UK-SPR1415" course homepage
    And I click on "Course Import" "link" in the "Course Import" "block"
    When I press "Continue"
    And I set the following fields to these values:
      | Include activities and resources | 1 |
      | Include blocks                   | 1 |
      | Include filters                  | 1 |
      | Include question bank            | 1 |
    And I press "Jump to final step"
    Then I should see "Content queued for import"
    And I click on "Return to course" "link_or_button"
    # Verify that going back still shows things as queued.
    And I click on "Course Import" "link" in the "Course Import" "block"
    And I should see "Content queued for import"
