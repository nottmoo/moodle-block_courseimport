<?php
// This file is part of the the courseimport block plugin for Moodle
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

namespace block_courseimport\task;
defined('MOODLE_INTERNAL') || die();

/**
 * Courseimport task class
 *
 * @package    block_courseimport
 * @author     Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @copyright  2014 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseimport_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'block_courseimport');
    }

    /**
     * Do the scheduled job.
     */
    public function execute() {
        $auto = new \block_courseimport\process();
        $auto->cron();
    }
}
