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
 * Data for the {@see bulk_status} Mustache template (single bulk job view on results.php).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_status implements renderable, templatable {
    /** @var array<string, mixed> */
    private array $data;

    /**
     * Creates bulk status output state.
     *
     * @param array<string, mixed> $data JSON-serialisable context for block_courseimport/bulk_status
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Build template context for one bulk job (validates access and updates parent job if stale).
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
        global $USER, $PAGE, $OUTPUT;

        $bulk = bulk_job::load_viewable_bulk($bulkid, (int) $USER->id);
        $snapshot = bulk_progress::snapshot_from_bulk_record($bulk, $bulkid);

        $filter = job::normalise_import_jobs_filter($filter);
        $childcountall = $snapshot['childcountall'];
        $childcountfinished = $snapshot['childcountfinished'];
        $childcountfailed = $snapshot['childcountfailed'];
        $childcountincomplete = $snapshot['childcountincomplete'];
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
        $childrows = bulk_results_view::build_child_job_table_rows($childrecords);

        $childslice = array_values($childrows);
        $rowoffset = $childpage * $childperpage;
        foreach ($childslice as $i => $row) {
            $childslice[$i]['rownum'] = $rowoffset + $i + 1;
        }

        $childnavurl = new url('/blocks/courseimport/bulk/results.php', [
            'bulkid' => $bulkid,
            'filter' => $filter,
        ]);
        $childpagination = '';
        if ($childtotal > $childperpage) {
            $from = $childpage * $childperpage + 1;
            $to = min($childpage * $childperpage + $childperpage, $childtotal);
            $childpagination .= \html_writer::div(
                get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $childtotal]),
                'mb-2 text-muted'
            );
            $childpagination .= $OUTPUT->paging_bar($childtotal, $childpage, $childperpage, $childnavurl);
        }
        $childpaginationhtml = $childtotal > $childperpage ? $childpagination : '';

        $PAGE->set_url(new url('/blocks/courseimport/bulk/results.php', [
            'bulkid' => $bulkid,
            'page' => $childpage,
            'filter' => $filter,
        ]));
        $PAGE->set_title(get_string('bulkstatusid', 'block_courseimport', $bulkid));
        $PAGE->navbar->add(get_string('bulkrollover', 'block_courseimport'), new url('/blocks/courseimport/bulk/index.php'));
        $PAGE->navbar->add(
            get_string('bulkresultsheading', 'block_courseimport'),
            new url('/blocks/courseimport/bulk/results.php')
        );
        $PAGE->navbar->add(get_string('bulkstatusid', 'block_courseimport', $bulkid));

        $data = [
            'bulkid' => $bulkid,
            'heading' => get_string('bulkstatusid', 'block_courseimport', $bulkid),
            'total' => $snapshot['total'],
            'completed' => $snapshot['completed'],
            'failed' => $snapshot['failed'],
            'doneunits' => $snapshot['doneunits'],
            'barlabel' => get_string('bulkstatusbarlabel', 'block_courseimport', (object) [
                'done' => $snapshot['doneunits'],
                'total' => $snapshot['total'],
            ]),
            'status' => $snapshot['status'],
            'statuslabel' => bulk_job::format_status_label($snapshot['status']),
            'progresstitle' => $snapshot['progresstitle'],
            'progresspct' => $snapshot['progresspct'],
            'isrunning' => $snapshot['isrunning'],
            'hasfailed' => $snapshot['hasfailed'],
            'countstext' => bulk_progress::format_count_summary_line(
                $snapshot['completed'],
                $snapshot['total'],
                $snapshot['failed']
            ),
            'childpaginationtop' => $childpaginationhtml,
            'childjobs' => $childslice,
            'childheading' => get_string('bulkstatuschildjobs', 'block_courseimport'),
            'childpaginationbottom' => $childpaginationhtml,
            'showchildjobfilters' => true,
            'childjobfilteritems' => bulk_results_view::child_job_filter_items(
                $bulkid,
                $filter,
                $childcountall,
                $childcountfinished,
                $childcountfailed,
                $childcountincomplete
            ),
        ];

        return new self($data);
    }

    /**
     * Exports Mustache context for the bulk status template.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        return $this->data;
    }
}
