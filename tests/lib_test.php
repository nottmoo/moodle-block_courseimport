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
     * Tests that the block_courseimport_findfilesize function works correctly.
     *
     * @covers block_courseimport_findfilesize
     * @group block_courseimport
     * @group uon
     */
    public function test_findfilesize() {
        global $USER, $SITE;

        require_once(dirname(__DIR__).'/lib.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $resourcegenerator = self::getDataGenerator()->get_plugin_generator('mod_resource');
        $usercontext = context_user::instance($USER->id);

        /* Create a resource for the small file which is 670b */
        $record = new stdClass();
        // Pick a random context id for specified user.
        $record->files = file_get_unused_draft_itemid();
        // Add actual file there.
        $filerecord = array('component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $record->files,
                'filename' => 'small.txt', 'filepath' => '/');
        $fs = get_file_storage();
        $fs->create_file_from_pathname($filerecord, __DIR__.'/fixtures/small.txt');
        $record->course = $SITE->id;
        // Create the resource.
        $resource0 = $resourcegenerator->create_instance($record);
        // Run the test.
        $fileinfo = block_courseimport_findfilesize($resource0->id);
        $this->assertEquals(670, $fileinfo->fsize);
        $this->assertEquals('text/plain', $fileinfo->ftype);

        /* Create a resource for the medium file which is 332,382b */
        $record2 = new stdClass();
        // Pick a random context id for specified user.
        $record2->files = file_get_unused_draft_itemid();
        // Add actual file there.
        $filerecord2 = array('component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $record2->files,
                'filename' => 'medium.txt', 'filepath' => '/');
        $fs2 = get_file_storage();
        $fs2->create_file_from_pathname($filerecord2, __DIR__.'/fixtures/medium.txt');
        $record2->course = $SITE->id;
        // Create the resource.
        $resource1 = $resourcegenerator->create_instance($record2);
        // Run the test.
        $fileinfo2 = block_courseimport_findfilesize($resource1->id);
        // Linux text file end each line with a line feed character.
        // DOS/Windows text files end each line with a carriage return and line feed.
        // Because of this, there is one additional character in a DOS/Windows file for each line in the file.
        // Some file will be in different size in Linux & Windows OS, so here we check file size in range 332331 to 332382.

        $this->assertGreaterThanOrEqual(332331, $fileinfo2->fsize);
        $this->assertLessThanOrEqual(332382, $fileinfo2->fsize);
        $this->assertEquals('text/plain', $fileinfo2->ftype);

        /* Create a resource for the large file which is 1,739,814b */
        $record3 = new stdClass();
        // Pick a random context id for specified user.
        $record3->files = file_get_unused_draft_itemid();
        // Add actual file there.
        $filerecord3 = array('component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $record3->files,
                'filename' => 'large.bmp', 'filepath' => '/');
        $fs3 = get_file_storage();
        $fs3->create_file_from_pathname($filerecord3, __DIR__.'/fixtures/large.bmp');
        $record3->course = $SITE->id;
        // Create the resource.
        $resource2 = $resourcegenerator->create_instance($record3);
        // Run the test.
        $fileinfo3 = block_courseimport_findfilesize($resource2->id);
        $this->assertEquals(1739814, $fileinfo3->fsize);
        $this->assertEquals('image/bmp', $fileinfo3->ftype);

        $this->assertDebuggingNotCalled();
    }

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
