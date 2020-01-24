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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the lib.php file of the Course Import block.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2014
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group block_courseimport
 * @group uon
 */
class block_courseimport_lib_testcase extends advanced_testcase {
    /**
     * Tests that the block_courseimport_timecheck function works correctly.
     *
     * Note: This test will fail at midnight and for a minute at either side of it.
     *
     * @covers block_courseimport_timecheck
     * @group block_courseimport
     * @group uon
     */
    public function test_timecheck() {
        require_once(dirname(__DIR__).'/lib.php');
        $this->resetAfterTest(false);
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('midnight')), date('G:i', strtotime('midnight'))));
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('today')), date('G:i', strtotime('tomorrow -1 second'))));
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('today')), date('G:i', strtotime('+1 minute'))));
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('-1 minute')), date('G:i', strtotime('tomorrow'))));
        $this->assertFalse(block_courseimport_timecheck(date('G:i', strtotime('-2 minute')), date('G:i', strtotime('-1 minute'))));
        $this->assertFalse(block_courseimport_timecheck(date('G:i', strtotime('+1 minute')), date('G:i', strtotime('+2 minute'))));
        $now = date('G:i');
        $this->assertFalse(block_courseimport_timecheck($now, $now));
        // The same as saying only for the last minute of the day.
        $this->assertFalse(block_courseimport_timecheck(date('G:i', strtotime('tomorrow -1 second')), date('G:i', strtotime('today'))));
        // Same as saying time after 1 minute in the future.
        $this->assertFalse(block_courseimport_timecheck(date('G:i', strtotime('+1 minute')), date('G:i', strtotime('today'))));
        // Same as saying not the minute that started one minute ago.
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('-1 minute')), date('G:i', strtotime('-2 minute'))));
        // Same as saying not the minute that will start in one minute.
        $this->assertTrue(block_courseimport_timecheck(date('G:i', strtotime('+2 minute')), date('G:i', strtotime('+1 minute'))));
        $this->assertDebuggingNotCalled();
    }
}
