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
 * Tests for the jobs external functions.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport\external;

use block_courseimport\job;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the enternal methods of the jobs class.
 *
 * @package     block_courseimport
 * @category    testing
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group block_courseimport
 * @group uon
 */
class jobs_test extends \advanced_testcase  {
    /**
     * Tests that the progress is calculated correctly.
     *
     * @param float $backup
     * @param float $import
     * @param float $expected
     * @dataProvider data_progress
     */
    public function test_progress(float $backup, float $import, float $expected) {
        $this->resetAfterTest(true);
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $job = $generator->create_job(['backupprogress' => $backup, 'restoreprogress' => $import]);
        $this->setAdminUser();
        $result = jobs::progress($job->id);
        self::assertEquals($expected, $result['progress']);
    }

    /**
     * Data used to test the progress.
     *
     * @return array
     */
    public function data_progress(): array {
        return [
            'none' => [0.0, 0.0, 0.0],
            'partial-backup' => [0.5, 0.0, 0.25],
            'backup-done' => [1.0, 0.0, 0.5],
            'partial-import' => [1.0, 0.5, 0.75],
            'finished' => [1.0, 1.0, 1.0],
        ];
    }

    /**
     * Tests that the status is reported correctly.
     *
     * @param string $status The staus of the job.
     * @param bool $started The expected flag for if the job has started.
     * @param bool $finished The expected flag for if the job has finished.
     * @param bool $failed The expected flag for if the job has failed.
     * @dataProvider data_progress_status
     */
    public function test_progress_status(string $status, bool $started, bool $finished, bool $failed) {
        $this->resetAfterTest(true);
        $generator = self::getDataGenerator()->get_plugin_generator('block_courseimport');
        $job = $generator->create_job(['status' => $status]);
        $this->setAdminUser();
        $result = jobs::progress($job->id);
        self::assertEquals($started, $result['started']);
        self::assertEquals($finished, $result['finished']);
        self::assertEquals($failed, $result['failed']);
    }

    /**
     * Data for the used to test that the status of a job is reported correctly.
     *
     * @return array
     */
    public function data_progress_status(): array {
        return [
            'waiting' => [job::STATE_WAITING, false, false, false],
            'processing' => [job::STATE_PROCESSING, true, false, false],
            'finished' => [job::STATE_FINISHED, true, true, false],
            'failed' => [job::STATE_FAILED, true, true, true],
        ];
    }
}
