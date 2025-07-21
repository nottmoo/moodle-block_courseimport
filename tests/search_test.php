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

namespace block_courseimport;

/**
 * Tests the block_courseimport_search class.
 *
 * @package     block_courseimport
 * @copyright   University of Nottingham, 2014
 * @author      Neill Magill <neill.magill@nottingham.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_courseimport\search
 * @group block_courseimport
 * @group uon
 */
final class search_test extends \advanced_testcase {
    /**
     * Tests that the block_courseimport_search::searchshortname method works correctly.
     *
     * @group block_courseimport
     * @group uon
     *
     * @return void
     */
    public function test_searchshortname(): void {
        $this->resetAfterTest(true);

        // First create some courses.
        // This group should all match each other.
        $course0g1 = self::getDataGenerator()->create_course(['shortname' => 'XX3L11-UK-FYR1112']);
        $course1g1 = self::getDataGenerator()->create_course(['shortname' => 'XX3L11-UK-FYR1213']);
        $course2g1 = self::getDataGenerator()->create_course(['shortname' => 'XX3L11-UK-FYR1314']);
        $course3g1 = self::getDataGenerator()->create_course(['shortname' => 'XX3L11-MY-FYR1314']);
        $course4g1 = self::getDataGenerator()->create_course(['shortname' => 'XX3L11-CN-FYR1314']);
        // The UK codes in this group group should also match each other.
        $course0g2 = self::getDataGenerator()->create_course(['shortname' => 'LE-CAREERS-ECON-UK-1213']);
        $course1g2 = self::getDataGenerator()->create_course(['shortname' => 'LE-CAREERS-ECON-UK-1314']);
        $course2g2 = self::getDataGenerator()->create_course(['shortname' => 'LE-CAREERS-ECON-CN-1314']);
        // Some random courses.
        $course0g3 = self::getDataGenerator()->create_course(['shortname' => 'XX3L12-UK-FYR1413']);
        $course0g3 = self::getDataGenerator()->create_course(['shortname' => 'G53NMD-UK-AUT-G53NMD-MY-AUT-1314']);
        $course0g3 = self::getDataGenerator()->create_course(['shortname' => 'PA-HS-INDUCT-UK']);
        $search = new search();

        $results0 = $search->searchshortname('XX3L11', 'XX3L11-UK-FYR1112');
        $this->assertEquals(4, $results0);
        // Check that caching is working correctly.
        $results1 = $search->searchshortname('ggg', '');
        $this->assertNotEquals($results1, $results0);

        $search = new search();
        $results0 = $search->get_shortnameresults('XX3L11', 'XX3L11-UK-FYR1112');
        $this->assertArrayHasKey($course1g1->id, $results0);
        $this->assertArrayHasKey($course2g1->id, $results0);
        $this->assertArrayHasKey($course3g1->id, $results0);
        $this->assertArrayHasKey($course4g1->id, $results0);
        // Check caching.
        $results1 = $search->get_shortnameresults('ggg', '');
        $results2 = $search->get_shortnameresults('XX3L11', 'XX3L11-UK-FYR1112');
        $this->assertNotEquals($results1, $results0);
        $this->assertEquals($results2, $results0);

        $results0 = $search->get_shortnameresults('LE-CAREERS-ECON-UK-', 'LE-CAREERS-ECON-CN-1314');
        $this->assertArrayHasKey($course0g2->id, $results0);
        $this->assertArrayHasKey($course1g2->id, $results0);
        $this->assertCount(2, $results0);
        $results0 = $search->searchshortname('LE-CAREERS-ECON-UK-', 'LE-CAREERS-ECON-CN-1314');
        $this->assertEquals(2, $results0);
        $this->assertDebuggingNotCalled();
    }
}
