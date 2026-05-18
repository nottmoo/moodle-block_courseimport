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

/**
 * Bulk job progress calculations (unit-testable).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\local;

/**
 * Percentage helpers for bulk parent jobs.
 */
final class bulk_progress {

    /**
     * Single-line counts summary for bulk status UI and AJAX polling (plain text).
     *
     * @param int $completed Successful child jobs.
     * @param int $total Planned child jobs.
     * @param int $failed Failed child jobs.
     * @return string
     */
    public static function format_count_summary_line(int $completed, int $total, int $failed): string {
        $failedsuffix = $failed > 0
            ? ' · ' . get_string('bulkstatusfailed', 'block_courseimport') . ': ' . $failed
            : '';
        return get_string('bulkstatusajaxcounts', 'block_courseimport', (object) [
            'completed' => $completed,
            'total' => $total,
            'failedsuffix' => $failedsuffix,
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
}
