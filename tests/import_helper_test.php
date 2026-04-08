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
 * Tests for the import_helper class.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport;

use context_user;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the import_helper class of the course import block.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2020
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Group('block_courseimport')]
#[Group('uon')]
#[CoversClass(import_helper::class)]
final class import_helper_test extends \advanced_testcase {
    /**
     * Tests that we can detect the size of files in a resource correctly.
     *
     * @param string $filename The name of the file.
     * @param int $minsize The minimum size of the file.
     * @param int $maxsize The maximum size of the file.
     * @param string $mimetype The mimetype of the file.
     * @return void
     */
    #[DataProvider('data_get_resource_filesize')]
    public function test_get_resource_filesize(string $filename, int $minsize, int $maxsize, string $mimetype): void {
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator()->get_plugin_generator('mod_resource');
        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $usercontext = context_user::instance($user->id);

        $this->setUser($user);

        // Add a file to Moodle.
        $draftid = file_get_unused_draft_itemid();
        $filerecord = [
            'component' => 'user',
            'filearea' => 'draft',
            'contextid' => $usercontext->id,
            'itemid' => $draftid,
            'filename' => $filename,
            'filepath' => '/',
        ];
        $fs = get_file_storage();
        $fs->create_file_from_pathname($filerecord, __DIR__ . "/fixtures/$filename");

        // Create a resource.
        $resource = $generator->create_instance(['course' => $course->id, 'files' => $draftid]);

        // Run the test.
        $fileinfo = import_helper::get_resource_filesize($resource->cmid);

        // Linux text file end each line with a line feed character.
        // DOS/Windows text files end each line with a carriage return and line feed.
        // Because of this, there is one additional character in a DOS/Windows file for each line in the file.
        // Some file will be in different size in Linux & Windows OS, so here we check range of sizes.
        self::assertGreaterThanOrEqual($minsize, $fileinfo->size);
        self::assertLessThanOrEqual($maxsize, $fileinfo->size);
        self::assertEquals($mimetype, $fileinfo->type);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Data for the get_resource_filesize test.
     *
     * @return array
     */
    public static function data_get_resource_filesize(): array {
        return [
            'small' => ['small.txt', 670, 670, 'text/plain'],
            'medium' => ['medium.txt', 332331, 332382, 'text/plain'],
            'large' => ['large.bmp', 1739814, 1739814, 'image/bmp'],
        ];
    }
}
