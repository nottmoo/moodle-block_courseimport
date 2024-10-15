<?php
// This file is part of courseimport block in Moodle - http://moodle.org/
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
 * Tests for the job class.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport;

/**
 * Tests the job class of the course import block.
 *
 * @package     block_courseimport
 * @category    testing
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group block_courseimport
 * @group uon
 */
class job_test extends \advanced_testcase {
    /**
     * Creates a UK help user.
     */
    protected function create_helpuser() {
        $helpemail = 'help@example.com';
        $user = self::getDataGenerator()->create_user(['email' => $helpemail]);
        set_config('ukemail', $helpemail, 'local_uonlib');
    }

    /**
     * Converts a recordset into an array indexed by the first value of the record.
     *
     * @param \moodle_recordset $recordset
     * @return array
     */
    protected function recordset_to_array(\moodle_recordset $recordset): array {
        $return = [];
        foreach ($recordset as $record) {
            $return[$recordset->key()] = $record;
        }
        return $return;
    }

    /**
     * Tests that the correct types of job are abandoned.
     *
     * @param string $status The initial status of the job.
     * @param string $expected The expected status after running jobs have been abandoned.
     * @param int $messagecount The number of messages that should be sent.
     *
     * @dataProvider data_abandon_running
     */
    public function test_abandon_running(string $status, string $expected, int $messagecount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->create_helpuser();
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $job = $generator->create_job(['status' => $status]);
        $sink = $this->redirectMessages();
        job::abandon_running();
        $messages = $sink->get_messages();
        self::assertEquals($expected, $DB->get_field('block_courseimport', 'status', ['id' => $job->id]));
        self::assertEquals($messagecount, count($messages));
    }

    /**
     * Data for the abandon running rest.
     *
     * @return array
     */
    public static function data_abandon_running(): array {
        return [
            'waiting' => [job::STATE_WAITING, job::STATE_WAITING, 0],
            'processing' => [job::STATE_PROCESSING, job::STATE_FAILED, 1],
            'failed' => [job::STATE_FAILED, job::STATE_FAILED, 0],
            'finished' => [job::STATE_FINISHED, job::STATE_FINISHED, 0],
        ];
    }

    /**
     * Tests that we correctly get jobs that are queued for processing.
     */
    public function test_get_queued_jobs() {
        $this->resetAfterTest(true);
        $source = self::getDataGenerator()->create_course(['fullname' => 'C1']);
        $target = self::getDataGenerator()->create_course(['fullname' => 'C2']);
        // Create some jobs that should not be found.
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $generator->create_job(['status' => job::STATE_PROCESSING]);
        $generator->create_job(['status' => job::STATE_FINISHED]);
        $generator->create_job(['status' => job::STATE_FAILED]);
        // Create a job that should be found.
        $jobparams = [
            'target' => $target->id,
            'source' => $source->id,
            'status' => job::STATE_WAITING,
        ];
        $job = $generator->create_job($jobparams);
        $records = self::recordset_to_array(job::get_queued_jobs());
        self::assertCount(1, $records);
        self::assertArrayHasKey($job->id, $records);
        self::assertEquals($job->id, $records[$job->id]->id);
        // We also expect the course fullnames to be returned, so avoid future database calls to get them.
        self::assertEquals($source->fullname, $records[$job->id]->fromname);
        self::assertEquals($target->fullname, $records[$job->id]->toname);

        // We will now test that we can use the record to create a job object.
        $record = clone $records[$job->id];
        // Change the course names so that we can test they are cached (and not getting the courses real names.
        $record->fromname = 'C3';
        $record->toname = 'C4';
        $instance = job::create_from_record($record);
        self::assertInstanceOf(job::class, $instance);
        self::assertEquals($job->id, $instance->id);
        self::assertEquals($source->id, $instance->source);
        self::assertEquals($target->id, $instance->target);
        self::assertEquals('C3', $instance->sourcename);
        self::assertEquals('C4', $instance->targetname);
        self::assertNotEquals($source->fullname, $instance->sourcename);
        self::assertNotEquals($target->fullname, $instance->targetname);
    }

    /**
     * Tests that a course with queued job is correctly detected.
     *
     * @param string $status The status of the job.
     * @param bool $expected The expected result from the method for the target course.
     *
     * @dataProvider data_job_queued
     */
    public function test_job_queued(string $status, bool $expected) {
        $this->resetAfterTest(true);
        // Create courses that we can test against.
        $source = self::getDataGenerator()->create_course();
        $target = self::getDataGenerator()->create_course();
        $other = self::getDataGenerator()->create_course();
        // Create a job.
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $jobparams = [
            'target' => $target->id,
            'source' => $source->id,
            'status' => $status,
        ];
        $generator->create_job($jobparams);
        // Do the test.
        self::assertFalse(job::job_queued($source->id));
        self::assertFalse(job::job_queued($other->id));
        self::assertEquals($expected, job::job_queued($target->id));
    }

    /**
     * Data for the job_qeued test.
     *
     * @return array
     */
    public static function data_job_queued(): array {
        return [
            'waiting' => [job::STATE_WAITING, true],
            'processing' => [job::STATE_PROCESSING, true],
            'failed' => [job::STATE_FAILED, false],
            'finished' => [job::STATE_FINISHED, false],
        ];
    }

    /**
     * Tests that we can set job statuses correctly.
     *
     * @param string $status
     * @param string $change
     *
     * @dataProvider data_set_status
     */
    public function test_set_status(string $status, string $change) {
        global $DB;
        $this->resetAfterTest(true);
        // Create and load the job.
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $jobrecord = $generator->create_job(['status' => $status]);
        $job = job::create_from_record($jobrecord);
        self::assertEquals($status, $job->status);
        // Change the status.
        $job->set_status($change);
        // Test that both the object tna the database have been updated.
        self::assertEquals($change, $job->status);
        self::assertEquals($change, $DB->get_field('block_courseimport', 'status', ['id' => $job->id]));
    }

    /**
     * Data to testing the likely state transitions.
     *
     * @return array
     */
    public static function data_set_status(): array {
        return [
            'waiting->processing' => [job::STATE_WAITING, job::STATE_WAITING],
            'processing->fail' => [job::STATE_PROCESSING, job::STATE_FAILED],
            'processing->finished' => [job::STATE_PROCESSING, job::STATE_FINISHED],
        ];
    }

    /**
     * Tests that a job gets saved correctly.
     */
    public function test_save() {
        global $DB;
        $this->resetAfterTest(true);
        $source = self::getDataGenerator()->create_course();
        $target = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        $job = new job($source->id, $target->id, 'testbid', $user->id);
        self::assertNull($job->id);
        $job->save();
        self::assertNotNull($job->id);
        $record = $DB->get_record('block_courseimport', ['id' => $job->id], '*', MUST_EXIST);
        self::assertEquals($job->source, $record->source);
        self::assertEquals($job->target, $record->target);
        self::assertEquals($job->bid, $record->backupid);
        self::assertEquals($job->user, $record->userid);
        self::assertEquals(job::STATE_WAITING, $record->status);
        $now = time();
        self::assertLessThanOrEqual($now, $record->timecreated);
        self::assertLessThanOrEqual($now, $record->timemodified);
        self::assertEquals($record->timecreated, $record->timemodified);
    }

    /**
     * Tests that we can get data from the job about the courses from a job.
     */
    public function test_course_properties() {
        $this->resetAfterTest(true);
        $source = self::getDataGenerator()->create_course();
        $target = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        $job = new job($source->id, $target->id, 'testbid', $user->id);
        self::assertEquals($source->id, $job->source);
        self::assertEquals($target->id, $job->target);
        self::assertEquals($source->fullname, $job->sourcename);
        self::assertEquals($target->fullname, $job->targetname);
        self::assertInstanceOf(\context_course::class, $job->sourcecontext);
        self::assertEquals($source->id, $job->sourcecontext->instanceid);
        self::assertInstanceOf(\context_course::class, $job->targetcontext);
        self::assertEquals($target->id, $job->targetcontext->instanceid);
    }
}
