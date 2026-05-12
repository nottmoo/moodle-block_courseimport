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

namespace block_courseimport\external;

use block_courseimport\bulk_job;
use block_courseimport\local\bulk_progress as BulkProgressCalc;
use block_courseimport\output\progress;
use context_system;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Class that contains the external functions for the plugin.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs extends \core_external\external_api {
    /**
     * Gets the progress of a job.
     *
     * @param $id The id of the job that progress should be found for.
     * @return array
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     */
    public static function progress($id): array {
        $progress = progress::get_for_job($id);
        // Verify that the user has the capability to import into the course.
        // If the target course has been deleted, gracefully stop polling.
        try {
            $context = \context_course::instance($progress->courseid);
            require_capability('moodle/restore:restoretargetimport', $context);
        } catch (\dml_missing_record_exception $e) {
            $progress->stop_polling_missing_target_course();
        }
        return $progress->export_for_external();
    }

    /**
     * Defines the inputs for the progress web service method.
     *
     * @return \core_external\external_function_parameters
     */
    public static function progress_parameters(): external_function_parameters {
        $params = [
            'id' => new external_value(PARAM_INT, 'The id of a job', VALUE_REQUIRED),
        ];
        return new external_function_parameters($params);
    }

    /**
     * Defines the output of the progress web service.
     *
     * @return \core_external\external_single_structure
     */
    public static function progress_returns(): external_single_structure {
        return progress::external_export_definition();
    }

    /**
     * Returns aggregate progress for a parent bulk rollover job (AJAX polling).
     *
     * @param int $bulkid Parent bulk job id ({@see bulk_job} row id).
     * @return array<string, mixed>
     */
    public static function bulk_progress(int $bulkid): array {
        global $USER;

        $params = self::validate_parameters(self::bulk_progress_parameters(), ['bulkid' => $bulkid]);
        $id = (int) $params['bulkid'];

        require_capability('block/courseimport:bulkrollover', context_system::instance());

        bulk_job::reconcile_queued_parent_if_stale($id);
        $bulk = bulk_job::get_record($id);
        if (!$bulk || !bulk_job::user_can_view($bulk, (int) $USER->id)) {
            throw new \moodle_exception('bulkstatusinvalid', 'block_courseimport');
        }

        $totalunits = (int) $bulk->total_count;
        $completedunits = (int) $bulk->completed_count + (int) $bulk->failed_count;
        $progresspct = BulkProgressCalc::percentage_complete($completedunits, $totalunits);
        $isrunning = $bulk->status === bulk_job::STATUS_QUEUED;
        $hasfailed = (int) $bulk->failed_count > 0;
        $failed = (int) $bulk->failed_count;
        $countstext = BulkProgressCalc::format_count_summary_line(
            (int) $bulk->completed_count,
            $totalunits,
            $failed
        );

        return [
            'progress' => $progresspct / 100.0,
            'progresspct' => $progresspct,
            'completed' => (int) $bulk->completed_count,
            'failed' => $failed,
            'total' => $totalunits,
            'status' => (string) $bulk->status,
            'isrunning' => $isrunning,
            'hasfailed' => $hasfailed,
            'countstext' => $countstext,
        ];
    }

    /**
     * @return external_function_parameters
     */
    public static function bulk_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bulkid' => new external_value(PARAM_INT, 'Parent bulk job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * @return external_single_structure
     */
    public static function bulk_progress_returns(): external_single_structure {
        return new external_single_structure([
            'progress' => new external_value(PARAM_FLOAT, 'Fraction complete 0..1'),
            'progresspct' => new external_value(PARAM_INT, 'Percent complete 0..100'),
            'completed' => new external_value(PARAM_INT, 'Child jobs completed'),
            'failed' => new external_value(PARAM_INT, 'Child jobs failed'),
            'total' => new external_value(PARAM_INT, 'Total child jobs'),
            'status' => new external_value(PARAM_TEXT, 'Parent bulk status'),
            'isrunning' => new external_value(PARAM_BOOL, 'True while parent status is queued'),
            'hasfailed' => new external_value(PARAM_BOOL, 'True if any child failed'),
            'countstext' => new external_value(PARAM_TEXT, 'Human-readable counts for the UI'),
        ]);
    }
}
