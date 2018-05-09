<?php
// This file is part of Moodle block plugin courseimport - http://moodle.org/
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
 * This file contains an event for when email user failed.
 *
 * @package    block_courseimport
 * @copyright  2014 University of Nottingham
 * @author     Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event for when send user eamil failed.
 * @since      Moodle 2.7
 */
class email_failed extends \core\event\email_failed {
    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return get_string('emailfailure', 'block_courseimport');
    }
}
