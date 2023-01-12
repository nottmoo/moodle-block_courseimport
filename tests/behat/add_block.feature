@block @uon @block_courseimport @javascript
Feature: Adding course import block to a course
  In order to control display of the block
  As a admin
  I need to be able to add the block to a course

  Background:
    Given the following "course" exists:
      | fullname  | Test course       |
      | shortname | N12401-UK-SPR1415 |
      | category  | 0                 |

  Scenario: Admins should be able to add the block
    Given I am on the "N12401-UK-SPR1415" "course" page logged in as "admin"
    And I turn editing mode on
    When I add the "Course import" block
    Then I should see "Course import" in the "Course import" "block"

  Scenario Outline: Other users should not be able to add the block
    Given the following "user" exists:
      | username  | user1            |
      | firstname | Testing          |
      | lastname  | User             |
      | email     | test@example.com |
    And the following "course enrolment" exists:
      | user   | user1             |
      | course | N12401-UK-SPR1415 |
      | role   | <role>            |
    Given I am on the "N12401-UK-SPR1415" "course" page logged in as "user1"
    And I turn editing mode on
    When I click on "Add a block" "link_or_button"
    Then I should not see "Course import" in the "Add a block" "dialogue"

    Examples:
      | role           |
      | manager        |
      | editingteacher |
