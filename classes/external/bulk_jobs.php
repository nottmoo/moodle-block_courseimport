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
use block_courseimport\local\bulk_progress;
use context_system;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External API for parent bulk rollover jobs.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_jobs extends \core_external\external_api {
    /**
     * Returns aggregate progress for a parent bulk rollover job (AJAX polling).
     *
     * Does not run {@see bulk_job::reconcile_queued_parent_if_stale()}; parent counters are
     * updated incrementally when child jobs finish and after each cron child run.
     *
     * @param int $bulkid Parent bulk job id ({@see bulk_job} row id).
     * @return array<string, mixed>
     */
    public static function progress(int $bulkid): array {
        global $USER;

        $params = self::validate_parameters(self::progress_parameters(), ['bulkid' => $bulkid]);
        $id = (int) $params['bulkid'];

        require_capability('block/courseimport:bulkrollover', context_system::instance());

        $bulk = bulk_job::load_viewable_bulk($id, (int) $USER->id, false);
        $snapshot = bulk_progress::snapshot_from_bulk_record($bulk, $id);

        return [
            'progresspct' => $snapshot['progresspct'],
            'completed' => $snapshot['completed'],
            'failed' => $snapshot['failed'],
            'total' => $snapshot['total'],
            'progresstitle' => $snapshot['progresstitle'],
            'childcountall' => $snapshot['childcountall'],
            'childcountfinished' => $snapshot['childcountfinished'],
            'isrunning' => $snapshot['isrunning'],
        ];
    }

    /**
     * Defines the inputs for the bulk progress web service method.
     *
     * @return external_function_parameters
     */
    public static function progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bulkid' => new external_value(PARAM_INT, 'Parent bulk job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Defines the output of the bulk progress web service.
     *
     * @return external_single_structure
     */
    public static function progress_returns(): external_single_structure {
        return new external_single_structure([
            'progresspct' => new external_value(PARAM_INT, 'Percent complete 0..100'),
            'completed' => new external_value(PARAM_INT, 'Child jobs completed'),
            'failed' => new external_value(PARAM_INT, 'Child jobs failed'),
            'total' => new external_value(PARAM_INT, 'Total child jobs'),
            'progresstitle' => new external_value(PARAM_TEXT, 'Localised progress card title while running'),
            'childcountall' => new external_value(PARAM_INT, 'Total child import jobs linked to this bulk job'),
            'childcountfinished' => new external_value(PARAM_INT, 'Child import jobs in finished state'),
            'isrunning' => new external_value(PARAM_BOOL, 'True while parent status is queued or processing'),
        ]);
    }
}
