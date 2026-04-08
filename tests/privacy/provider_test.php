<?php
// This file is part of course import block.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests the course import block Privacy API implementation.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2018
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport\privacy;

use block_courseimport\job;
use core_privacy\tests\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the course import block privacy provider class.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2018
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Group('block_courseimport')]
#[Group('uon')]
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \block_courseimport_generator The course import block data generator. */
    protected $generator;

    /**
     * Setup for each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $this->resetAfterTest(true);
    }

    /**
     * Run at the end of each test.
     */
    public function tearDown(): void {
        $this->assertDebuggingNotCalled();
        parent::tearDown();
    }

    /**
     * Test that when a user has not started any import jobs that nothing is exported.
     *
     * @return void
     */
    public function test_user_with_no_jobs(): void {
        $user = self::getDataGenerator()->create_user();
        $otheruser = self::getDataGenerator()->create_user();
        $this->generator->create_job(['userid' => $otheruser->id]);
        // Test no contexts are retrived.
        $contextlist = $this->get_contexts_for_userid($user->id, 'block_courseimport');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(0, $contexts);
    }

    /**
     * Tests that when a user has started an import job that it is exported.
     *
     * @return void
     */
    public function test_user_with_job(): void {
        $user = self::getDataGenerator()->create_user();
        $otheruser = self::getDataGenerator()->create_user();
        $this->generator->create_job(['userid' => $user->id]);
        $this->generator->create_job(['userid' => $otheruser->id]);
        // Test a context is retrived.
        $contextlist = $this->get_contexts_for_userid($user->id, 'block_courseimport');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(1, $contexts);

        $context = \context_user::instance($user->id);
        $this->assertEquals($context, $contextlist->current());

        // Test export.
        $this->export_context_data_for_user($user->id, $context, 'block_courseimport');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $subcontext = get_string('privacy:export:jobs', 'block_courseimport');
        $this->assertTrue($writer->has_any_data([$subcontext]));
    }

    /**
     * Tests that we delete all jobs in a context.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $user = self::getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);
        $otheruser = self::getDataGenerator()->create_user();
        // Jobs that should not be deleted.
        $job1 = $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_WAITING]);
        $job2 = $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_PROCESSING]);
        // Jobs that should be deleted.
        $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_FINISHED]);
        // Job for another user.
        $this->generator->create_job(['userid' => $otheruser->id]);
        // Make the call to delete.
        provider::delete_data_for_all_users_in_context($usercontext);
        // Test that the correct data has been removed.
        $this->assertEquals(2, $DB->count_records('block_courseimport', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('block_courseimport', ['id' => $job1->id]));
        $this->assertTrue($DB->record_exists('block_courseimport', ['id' => $job2->id]));
        // Other contexts should not be affected.
        $this->assertEquals(3, $DB->count_records('block_courseimport'));
    }

    /**
     * Test that we delete the resolved jobs for the user.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);
        $otheruser = self::getDataGenerator()->create_user();
        // Jobs that should not be deleted.
        $job1 = $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_WAITING]);
        $job2 = $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_PROCESSING]);
        // Jobs that should be deleted.
        $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user->id, 'status' => job::STATE_FINISHED]);
        // Job for another user.
        $this->generator->create_job(['userid' => $otheruser->id]);
        // Make the call to delete.
        $approvedcontextlist = new approved_contextlist(
            \core_user::get_user($user->id),
            'block_courseimport',
            [$usercontext->id]
        );
        provider::delete_data_for_user($approvedcontextlist);
        // Test that the correct data has been removed.
        $this->assertEquals(2, $DB->count_records('block_courseimport', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('block_courseimport', ['id' => $job1->id]));
        $this->assertTrue($DB->record_exists('block_courseimport', ['id' => $job2->id]));
        // Other users jobs should be unaffected.
        $this->assertEquals(3, $DB->count_records('block_courseimport'));
    }

    /**
     * Test that data for users in approved userlist is deleted.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $component = 'block_courseimport';

        $user1 = $this->getDataGenerator()->create_user();
        $usercontext1 = \context_user::instance($user1->id);
        $user2 = $this->getDataGenerator()->create_user();
        $usercontext2 = \context_user::instance($user2->id);

        // Jobs for $user1 that should not be deleted.
        $this->setUser($user1);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_WAITING]);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_PROCESSING]);
        // Jobs for $user1 that should be deleted.
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_FINISHED]);

        // Jobs for $user2 all should be deleted.
        $this->setUser($user2);
        $this->generator->create_job(['userid' => $user2->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user2->id, 'status' => job::STATE_FINISHED]);

        $userlist1 = new userlist($usercontext1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);

        $userlist2 = new userlist($usercontext2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);

        // Create approveduserlist for delete_data_for_users().
        $approveduserlist1 = new approved_userlist($usercontext1, $component, $userlist1->get_userids());
        $approveduserlist2 = new approved_userlist($usercontext2, $component, $userlist2->get_userids());

        // All user2's data should be deleted.
        provider::delete_data_for_users($approveduserlist2);
        $this->assertEquals(0, $DB->count_records('block_courseimport', ['userid' => $user2->id]));
        $this->assertEquals(4, $DB->count_records('block_courseimport', ['userid' => $user1->id]));

        // Only user1's 2 records left.
        provider::delete_data_for_users($approveduserlist1);
        $this->assertEquals(2, $DB->count_records('block_courseimport', ['userid' => $user1->id]));
    }

    /**
     * Test get users in the context
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $user1 = self::getDataGenerator()->create_user();
        $usercontext1 = \context_user::instance($user1->id);

        $user2 = self::getDataGenerator()->create_user();
        $usercontext2 = \context_user::instance($user2->id);
        $component = 'block_courseimport';

        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_FINISHED]);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_PROCESSING]);
        $this->generator->create_job(['userid' => $user1->id, 'status' => job::STATE_WAITING]);

        $this->generator->create_job(['userid' => $user2->id, 'status' => job::STATE_FAILED]);
        $this->generator->create_job(['userid' => $user2->id, 'status' => job::STATE_FINISHED]);

        $userlist1 = new userlist($usercontext1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);
        $expected = [$user1->id];
        $actual = $userlist1->get_userids();
        $this->assertEquals($expected, $actual);

        $userlist2 = new userlist($usercontext2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
        $expected = [$user2->id];
        $actual = $userlist2->get_userids();
        $this->assertEquals($expected, $actual);
    }
}
