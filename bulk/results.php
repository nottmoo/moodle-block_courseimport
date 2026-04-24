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
 * Bulk rollover results: paginated list and per-bulk detail.
 * 
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\bulk_job;
use block_courseimport\job;
use core\url;

require_login();
$systemcontext = context_system::instance();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$bulkid = optional_param('bulkid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$cpage = optional_param('cpage', 0, PARAM_INT);
$completedonly = optional_param('completed', 0, PARAM_INT);
$perpage = 20;
$childperpage = 20;

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkresultsheading', 'block_courseimport'));

global $DB, $USER, $OUTPUT;

if ($bulkid) {
    $PAGE->set_url(new url('/blocks/courseimport/bulk/results.php', [
        'bulkid' => $bulkid,
        'cpage' => $cpage,
        'completed' => $completedonly,
    ]));
    $bulk = bulk_job::get_record($bulkid);
    if (!$bulk || !bulk_job::user_can_view($bulk, $USER->id)) {
        throw new moodle_exception('bulkstatusinvalid', 'block_courseimport');
    }

    $total    = max(1, (int) $bulk->total_count);
    $calcpct  = fn(int $d, int $t): int => min(100, (int) round(100 * $d / $t));
    $done     = (int) $bulk->completed_count + (int) $bulk->failed_count;
    $pct      = $calcpct($done, $total);
    $isrunning = $bulk->status === \block_courseimport\bulk_job::STATUS_QUEUED;
    $hasfailed = (int) $bulk->failed_count > 0;

    $sql = "SELECT j.id, j.target, j.source, j.status, j.timecreated,
                   tc.fullname AS targetname, sc.fullname AS sourcename
              FROM {block_courseimport} j
              LEFT JOIN {course} tc ON tc.id = j.target
              LEFT JOIN {course} sc ON sc.id = j.source
             WHERE j.bulk_job_id = :bid
          ORDER BY j.id DESC";
    $children = $DB->get_records_sql($sql, ['bid' => $bulkid]);

    // Fallback: if bulk job claims to be running but no child jobs are still active,
    // compute real progress from child statuses and stop the auto-refresh.
    if ($isrunning && $children) {
        $activecnt = 0;
        $finishedcnt = 0;
        $failedcnt = 0;
        foreach ($children as $c) {
            if ($c->status === job::STATE_WAITING || $c->status === job::STATE_PROCESSING) {
                $activecnt++;
            } else if ($c->status === job::STATE_FINISHED) {
                $finishedcnt++;
            } else {
                $failedcnt++;
            }
        }
        if ($activecnt === 0) {
            // All child jobs done — bulk job tracking may have missed updates; fix status now.
            $isrunning = false;
            $done      = $finishedcnt + $failedcnt;
            $pct       = $calcpct($done, $total);
            $hasfailed = $failedcnt > 0;
            \block_courseimport\bulk_job::sync_status_from_children($bulkid, $finishedcnt, $failedcnt, (int) $bulk->total_count);
        }
    }

    $childrows = [];
    foreach ($children as $c) {
        if ($completedonly && $c->status !== job::STATE_FINISHED) {
            continue;
        }
        $tname = $c->targetname ?? '';
        $sname = $c->sourcename ?? '';
        $tctx = context_course::instance($c->target, IGNORE_MISSING);
        $sctx = context_course::instance($c->source, IGNORE_MISSING);
        $targetid = (int) $c->target;
        $targetlabel = $tctx ? format_string($tname, true, ['context' => $tctx]) : get_string('bulkcoursedeleted', 'block_courseimport');
        $targetlinkurl = ($tctx && $targetid > 0)
            ? (new \moodle_url('/course/view.php', ['id' => $targetid]))->out(false)
            : '';

        $childrows[] = [
            'target' => $targetlabel,
            'targetid' => $targetid,
            'targetlinkurl' => $targetlinkurl,
            'source' => $sctx ? format_string($sname, true, ['context' => $sctx]) : get_string('bulkcoursedeleted', 'block_courseimport'),
            'sourceid' => (int) $c->source,
            'statelabel' => block_courseimport_bulk_results_job_label($c->status),
        ];
    }

    $childtotal = count($childrows);
    $childslice = array_values(array_slice($childrows, $cpage * $childperpage, $childperpage));
    $rowoffset = $cpage * $childperpage;
    foreach ($childslice as $i => $row) {
        $childslice[$i]['rownum'] = $rowoffset + $i + 1;
    }
    $childnavurl = new moodle_url('/blocks/courseimport/bulk/results.php', [
        'bulkid' => $bulkid,
        'completed' => $completedonly,
    ]);
    $childpagination = '';
    if ($childtotal > $childperpage) {
        $from = $cpage * $childperpage + 1;
        $to = min($cpage * $childperpage + $childperpage, $childtotal);
        $childpagination .= html_writer::div(
            get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $childtotal]),
            'mb-2 text-muted'
        );
        $childpagination .= $OUTPUT->paging_bar($childtotal, $cpage, $childperpage, $childnavurl, 'cpage');
    }
    $childpaginationhtml = $childtotal > $childperpage ? $childpagination : '';

    $toggleurlall = new moodle_url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bulkid, 'cpage' => 0, 'completed' => 0]);
    $toggleurlcompleted = new moodle_url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bulkid, 'cpage' => 0, 'completed' => 1]);
    $togglelink = $completedonly
        ? html_writer::link($toggleurlall, get_string('bulkshowallchildjobs', 'block_courseimport'), ['class' => 'badge badge-info'])
        : html_writer::link($toggleurlcompleted, get_string('bulkshowcompletedchildjobs', 'block_courseimport'), ['class' => 'badge badge-info']);

    $backlink = html_writer::link(
        (new url('/blocks/courseimport/bulk/results.php'))->out(false),
        get_string('bulkresultsheading', 'block_courseimport'),
        ['class' => 'badge badge-info']
    );

    $toplinks = html_writer::div($togglelink . ' ' . $backlink, 'mb-3');

    echo $OUTPUT->header();
    echo $toplinks;
    echo $OUTPUT->render_from_template('block_courseimport/bulk_status', [
        'backurl' => '',
        'backlabel' => '',
        'heading' => get_string('bulkstatusid', 'block_courseimport', $bulkid),
        'total' => (int) $bulk->total_count,
        'completed' => (int) $bulk->completed_count,
        'failed' => (int) $bulk->failed_count,
        'status' => $bulk->status,
        'progresspct' => $pct,
        'isrunning' => $isrunning,
        'hasfailed' => $hasfailed,
        'refreshurl' => (new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bulkid, 'cpage' => $cpage, 'completed' => $completedonly]))->out(false),
        'childpaginationtop' => $childpaginationhtml,
        'childjobs' => $childslice,
        'childheading' => get_string('bulkstatuschildjobs', 'block_courseimport'),
        'childpaginationbottom' => $childpaginationhtml,
    ]);
    echo $OUTPUT->footer();
    exit;
}

$totalcount = bulk_job::count_for_user((int) $USER->id);
$listbaseurl = new moodle_url('/blocks/courseimport/bulk/results.php');
$PAGE->set_url($listbaseurl, ['page' => $page]);

$jobs = bulk_job::list_for_user_page((int) $USER->id, $perpage, $page * $perpage);
$list = [];
foreach ($jobs as $bj) {
    $totalpairs = max(1, (int) $bj->total_count);
    $donepairs = (int) $bj->completed_count + (int) $bj->failed_count;
    $pct = min(100, (int) round(100 * $donepairs / $totalpairs));
    $list[] = [
        'id' => (int) $bj->id,
        'status' => $bj->status,
        'total' => (int) $bj->total_count,
        'completed' => (int) $bj->completed_count,
        'failed' => (int) $bj->failed_count,
        'progresspct' => $pct,
        'viewurl' => (new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bj->id]))->out(false),
        'timecreated' => userdate($bj->timecreated),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkstatuslistheading', 'block_courseimport'), 2);
if ($list) {
    $from = $page * $perpage + 1;
    $to = $page * $perpage + count($list);
    echo html_writer::div(
        get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $totalcount]),
        'mb-2 text-muted'
    );
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $listbaseurl);

    $table = new html_table();
    $table->head = [
        get_string('bulkstatuscolumnd', 'block_courseimport'),
        get_string('bulkstatustotal', 'block_courseimport'),
        get_string('bulkstatuscompleted', 'block_courseimport'),
        get_string('bulkstatusfailed', 'block_courseimport'),
        get_string('bulkstatusstate', 'block_courseimport'),
        get_string('bulkstatusprogress', 'block_courseimport'),
        '',
    ];
    foreach ($list as $row) {
        $table->data[] = [
            '#' . $row['id'],
            $row['total'],
            $row['completed'],
            $row['failed'],
            $row['status'],
            $row['progresspct'] . '%',
            html_writer::link($row['viewurl'], get_string('bulkstatusview', 'block_courseimport')),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $listbaseurl);
} else {
    echo html_writer::div(get_string('bulkstatusnone', 'block_courseimport'), 'alert alert-info');
}
echo html_writer::div(
    html_writer::link(new url('/blocks/courseimport/bulk/index.php'), get_string('bulkbacktoupload', 'block_courseimport'), ['class' => 'badge badge-info']),
    'mt-3'
);
echo $OUTPUT->footer();

/**
 * @param string $status
 * @return string
 */
function block_courseimport_bulk_results_job_label(string $status): string {
    switch ($status) {
        case job::STATE_FINISHED:
            return get_string('privacy:export:status:finished', 'block_courseimport');
        case job::STATE_FAILED:
            return get_string('privacy:export:status:failed', 'block_courseimport');
        case job::STATE_PROCESSING:
            return get_string('privacy:export:status:processing', 'block_courseimport');
        case job::STATE_WAITING:
            return get_string('privacy:export:status:waiting', 'block_courseimport');
        default:
            return get_string('privacy:export:status:unknown', 'block_courseimport');
    }
}
