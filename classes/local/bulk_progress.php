<?php
// This file is part of the courseimport block plugin for Moodle
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

namespace block_courseimport\local;

use block_courseimport\bulk_job;
use block_courseimport\job;

/**
 * Percentage helpers for bulk parent jobs.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_progress {

    /**
     * Single-line counts summary for bulk status initial page render (plain text).
     *
     * @param int $completed Successful child jobs.
     * @param int $total Planned child jobs.
     * @param int $failed Failed child jobs.
     * @return string
     */
    public static function format_count_summary_line(int $completed, int $total, int $failed): string {
        return get_string('bulkstatusajaxcounts', 'block_courseimport', (object) [
            'completed' => $completed,
            'total' => $total,
            'failed' => $failed,
        ]);
    }

    /**
     * Percent complete for a bulk job, capped at 100.
     *
     * If there are zero planned imports (`totalunits` &lt; 1), there is no meaningful
     * denominator; treat the job as complete (100%) instead of forcing a fake total of 1.
     *
     * @param int $completedunits Finished + failed child jobs (or equivalent counts).
     * @param int $totalunits Planned total child jobs for this bulk submission.
     * @return int 0–100
     */
    public static function percentage_complete(int $completedunits, int $totalunits): int {
        if ($totalunits < 1) {
            return 100;
        }
        return min(100, (int) round(100 * $completedunits / $totalunits));
    }

    /**
     * Progress snapshot for bulk status page render and AJAX polling (numeric fields only).
     *
     * @param bulk_job $bulk Parent bulk job.
     * @param int $bulkjobid Parent bulk job id (for child-job counts).
     * @return array<string, mixed>
     */
    public static function snapshot_from_bulk_record(bulk_job $bulk, int $bulkjobid): array {
        $completed = $bulk->completedcount;
        $failed = $bulk->failedcount;
        $total = $bulk->totalcount;
        $doneunits = bulk_job::count_done_units($bulk);
        $progresspct = self::percentage_complete($doneunits, $total);
        $status = $bulk->status;

        return [
            'completed' => $completed,
            'failed' => $failed,
            'total' => $total,
            'doneunits' => $doneunits,
            'progresspct' => $progresspct,
            'isrunning' => bulk_job::is_parent_still_running($bulk),
            'hasfailed' => $failed > 0,
            'status' => $status,
            'progresstitle' => bulk_job::get_running_progress_title($status),
            'childcountall' => job::count_import_jobs($bulkjobid),
            'childcountfinished' => job::count_import_jobs($bulkjobid, job::FILTER_FINISHED),
            'childcountfailed' => job::count_import_jobs($bulkjobid, job::FILTER_FAILED),
            'childcountincomplete' => job::count_import_jobs($bulkjobid, job::FILTER_INCOMPLETE),
        ];
    }
}
