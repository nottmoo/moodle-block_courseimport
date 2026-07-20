<?php
// This file is part of the courseimport block in Moodle
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

namespace block_courseimport\output;

use block_courseimport\bulk_job;
use block_courseimport\job;
use block_courseimport\local\bulk_progress;
use block_courseimport\local\bulk_results_view;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;

/**
 * Data for the bulk_status Mustache template (single bulk job view on results.php).
 *
 * {@see fetch()} loads the minimum database state; {@see export_for_template()} builds
 * presentation fields when the page is rendered.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_status implements renderable, templatable {
    /** @var bulk_job Loaded parent bulk job. */
    private bulk_job $bulk;

    /** @var int Parent bulk job id. */
    private int $bulkid;

    /** @var string Normalised child list filter ({@see job::FILTER_*}). */
    private string $filter;

    /** @var int Zero-based child table page index. */
    private int $childpage;

    /** @var int Rows per page for the child table. */
    private int $childperpage;

    /** @var array<int, \stdClass> Raw child job records for the current page. */
    private array $childrecords;

    /** @var int Total child jobs (all filters). */
    private int $childcountall;

    /** @var int Finished child jobs. */
    private int $childcountfinished;

    /** @var int Failed child jobs. */
    private int $childcountfailed;

    /** @var int Incomplete child jobs. */
    private int $childcountincomplete;

    /**
     * @param bulk_job $bulk Loaded parent bulk job
     * @param int $bulkid Parent bulk job id
     * @param string $filter Normalised child list filter
     * @param int $childpage Zero-based child table page index
     * @param int $childperpage Rows per page for the child table
     * @param array<int, \stdClass> $childrecords Raw child job records for the current page
     * @param int $childcountall Total child jobs
     * @param int $childcountfinished Finished child jobs
     * @param int $childcountfailed Failed child jobs
     * @param int $childcountincomplete Incomplete child jobs
     */
    private function __construct(
        bulk_job $bulk,
        int $bulkid,
        string $filter,
        int $childpage,
        int $childperpage,
        array $childrecords,
        int $childcountall,
        int $childcountfinished,
        int $childcountfailed,
        int $childcountincomplete
    ) {
        $this->bulk = $bulk;
        $this->bulkid = $bulkid;
        $this->filter = $filter;
        $this->childpage = $childpage;
        $this->childperpage = $childperpage;
        $this->childrecords = $childrecords;
        $this->childcountall = $childcountall;
        $this->childcountfinished = $childcountfinished;
        $this->childcountfailed = $childcountfailed;
        $this->childcountincomplete = $childcountincomplete;
    }

    /**
     * Loads the minimum database state needed to render one bulk job later.
     *
     * @param int $bulkid Parent bulk job id
     * @param int $childpage Child table page index
     * @param string $filter Child list {@see job::FILTER_*} key (empty = all)
     * @param int $childperpage Rows per page for child table
     * @return self
     * @throws \moodle_exception
     */
    public static function fetch(
        int $bulkid,
        int $childpage,
        string $filter = job::FILTER_ALL,
        int $childperpage = 20
    ): self {
        global $USER;

        $bulk = bulk_job::load_viewable_bulk($bulkid, (int) $USER->id);
        $filter = job::normalise_import_jobs_filter($filter);

        $childcountall = job::count_import_jobs($bulkid);
        $childcountfinished = job::count_import_jobs($bulkid, job::FILTER_FINISHED);
        $childcountfailed = job::count_import_jobs($bulkid, job::FILTER_FAILED);
        $childcountincomplete = job::count_import_jobs($bulkid, job::FILTER_INCOMPLETE);
        $childtotal = match ($filter) {
            job::FILTER_FINISHED => $childcountfinished,
            job::FILTER_FAILED => $childcountfailed,
            job::FILTER_INCOMPLETE => $childcountincomplete,
            default => $childcountall,
        };

        $maxchildpage = $childtotal > 0 ? (int) ceil($childtotal / $childperpage) - 1 : 0;
        $childpage = min(max(0, $childpage), max(0, $maxchildpage));

        $childrecords = job::get_import_jobs(
            $bulkid,
            $childperpage,
            $childpage,
            $filter
        );

        return new self(
            $bulk,
            $bulkid,
            $filter,
            $childpage,
            $childperpage,
            $childrecords,
            $childcountall,
            $childcountfinished,
            $childcountfailed,
            $childcountincomplete
        );
    }

    /**
     * Builds Mustache context for the bulk status template.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $completed = $this->bulk->completedcount;
        $failed = $this->bulk->failedcount;
        $total = $this->bulk->totalcount;
        $doneunits = bulk_job::count_done_units($this->bulk);
        $status = $this->bulk->status;
        $isrunning = bulk_job::is_parent_still_running($this->bulk);

        $childtotal = match ($this->filter) {
            job::FILTER_FINISHED => $this->childcountfinished,
            job::FILTER_FAILED => $this->childcountfailed,
            job::FILTER_INCOMPLETE => $this->childcountincomplete,
            default => $this->childcountall,
        };

        $childrows = bulk_results_view::build_child_job_table_rows($this->childrecords);
        $rowoffset = $this->childpage * $this->childperpage;
        foreach ($childrows as $i => $row) {
            $childrows[$i]['rownum'] = $rowoffset + $i + 1;
        }

        $showchildpagination = $childtotal > $this->childperpage;
        $from = 0;
        $to = 0;
        $childpaging = '';
        if ($showchildpagination) {
            $from = $this->childpage * $this->childperpage + 1;
            $to = min($this->childpage * $this->childperpage + $this->childperpage, $childtotal);
            $childnavurl = new url('/blocks/courseimport/bulk/results.php', [
                'bulkid' => $this->bulkid,
                'filter' => $this->filter,
            ]);
            $childpaging = $output->paging_bar(
                $childtotal,
                $this->childpage,
                $this->childperpage,
                $childnavurl
            );
        }

        return [
            'bulkid' => $this->bulkid,
            'heading' => get_string('bulkstatusid', 'block_courseimport', $this->bulkid),
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'doneunits' => $doneunits,
            'barlabel' => get_string('bulkstatusbarlabel', 'block_courseimport', (object) [
                'done' => $doneunits,
                'total' => $total,
            ]),
            'status' => $status,
            'statuslabel' => bulk_job::format_status_label($status),
            'progresstitle' => bulk_job::get_running_progress_title($status),
            'progresspct' => bulk_progress::percentage_complete($doneunits, $total),
            'isrunning' => $isrunning,
            'hasfailed' => $failed > 0,
            'countstext' => bulk_progress::format_count_summary_line($completed, $total, $failed),
            'showchildpagination' => $showchildpagination,
            'from' => $from,
            'to' => $to,
            'childlisttotal' => $childtotal,
            'childpaging' => $childpaging,
            'childjobs' => $childrows,
            'childheading' => get_string('bulkstatuschildjobs', 'block_courseimport'),
            'showchildjobfilters' => true,
            'childjobfilteritems' => bulk_results_view::child_job_filter_items(
                $this->bulkid,
                $this->filter,
                $this->childcountall,
                $this->childcountfinished,
                $this->childcountfailed,
                $this->childcountincomplete
            ),
        ];
    }
}