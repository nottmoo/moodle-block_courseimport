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
 * External functions for the plugin.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport\external;

use block_courseimport\output\progress;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Class that contains the external functions for the plugin.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs extends \external_api {
    /**
     * Gets the progress of a job.
     *
     * @param $id The id of the job that progress should be found for.
     * @return array
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     */
    public static function progress($id): array {
        $params = self::validate_parameters(self::progress_parameters(), array('id' => $id));
        $progress = progress::get_for_job($params['id']);
        // Verify that the user has the capability to import into the course.
        $context = \context_course::instance($progress->courseid);
        require_capability('moodle/restore:restoretargetimport', $context);
        // Send back the progress.
        return $progress->export_for_external();
    }

    /**
     * Defines the inputs for the progress web service method.
     *
     * @return \external_function_parameters
     */
    public static function progress_parameters(): \external_function_parameters {
        $params = [
            'id' => new \external_value(PARAM_INT, 'The id of a job', VALUE_REQUIRED),
        ];
        return new \external_function_parameters($params);
    }

    /**
     * Defines the output of the progress web service.
     *
     * @return \external_single_structure
     */
    public static function progress_returns(): \external_single_structure {
        return progress::external_export_definition();
    }
}