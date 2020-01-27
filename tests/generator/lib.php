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

defined('MOODLE_INTERNAL') || die();

/**
 * The block_courseimport data generator.
 *
 * @package    block_courseimport
 * @copyright  2018 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_courseimport_generator extends testing_block_generator {
    /**
     * Creates a job in the import queue.
     *
     * @global moodle_database $DB
     * @param array|stdClass $job
     * @return stdClass
     */
    public function create_job($job) : stdClass {
        global $DB;
        $now = time();
        $defaults = array(
            'target' => null,
            'source' => null,
            'userid' => null,
            'backupid' => 'utterlyinvalidid',
            'status' => \block_courseimport\job::STATE_WAITING,
            'timecreated' => $now,
            'timemodified' => $now,
        );
        $record = (object)$this->datagenerator->combine_defaults_and_record($defaults, $job);
        // Ensure there is a source course.
        if (is_null($record->source)) {
            $source = $this->datagenerator->create_course();
            $record->source = $source->id;
        }
        // Ensure there is a target course.
        if (is_null($record->target)) {
            $target = $this->datagenerator->create_course();
            $record->target = $target->id;
        }
        if (is_null($record->userid)) {
            $user = $this->datagenerator->create_user();
            $record->userid = $user->id;
        }
        $id = $DB->insert_record('block_courseimport', $record);
        return $DB->get_record('block_courseimport', ['id' => $id]);
    }
}
