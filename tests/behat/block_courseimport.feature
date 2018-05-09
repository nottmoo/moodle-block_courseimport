@block @uon @block_courseimport
Feature: Test block_courseimport function 
   In order to import course content
   As a moodle editingteacher in a course
   I need to use course searching page to find imported course

    Background:
        Given the following "users" exist:
            | username | firstname | lastname | email |
            | student1 | Stu | Stu | student1@stu1.com |
            | teacher1 | edt1 | Edt | edt1@stu1.com |
        Given the following "courses" exist:
            | fullname | shortname | category |
            | N12401-UK-SPR1415 | N12401-UK-SPR1415 | 0 |
            | N12401-UK-SPR1314 | N12401-UK-SPR1314 | 0 |
            | P12130-UK-SPR1314 | P12130-UK-SPR1314 | 0 |
            # searching course without EditingTeacher role in it
            | P13140-UK-SPR1415 | P13140-UK-SPR1415 | 0 |
        And the following "course enrolments" exist:
            | user | course | role |
            | student1 | N12401-UK-SPR1415 | student |
            | teacher1 | N12401-UK-SPR1415 | editingteacher |
            | teacher1 | P12130-UK-SPR1314 | editingteacher |

    Scenario: only admin can add the plugin in Moodle
        When I log in as "admin"
        And I am on site homepage
        And I follow "N12401-UK-SPR1415"
        And I turn editing mode on
        And I add the "Course Import" block
        And I should see "Course Import"
        # Course Import link can also be seen in courselife block
        And I turn editing mode off
        Then I log out
        #Editing teacher can see the link
        When I log in as "teacher1"
        And I follow "N12401-UK-SPR1415"
        Then I should see "Course Import"
        And I click on "Course Import" "link" in the "Course Import" "block"
        Then I should see "Select a course"
        And I should see "N12401-UK-SPR1314"
        Given I set the following fields to these values:
        | search | P13140 |
        And I press "Search"
        Then I should not see "P13140-UK-SPR1415"
        And I set the following fields to these values:
        | search | P12130 |
        And I press "Search"
        Then I should see "P12130-UK-SPR1314"
        Then I set the field "importid" to "1"
        And I press "Continue"
        Then the field "setting_root_activities" matches value "1"
        Then the field "setting_root_blocks" matches value "1"
        Then the field "setting_root_filters" matches value "1"
        Then the field "setting_root_questionbank" matches value "1"
