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
 * Bulk rollover submit page.
 * 
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\bulk_config;
use block_courseimport\bulk_submitter;
use block_courseimport\csv_parser;
use block_courseimport\form\csv_upload_form;
use block_courseimport\module_pair_resolver;
use core\url;

global $SESSION, $USER, $OUTPUT, $DB;

require_login();
$systemcontext = context_system::instance();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$previewpage = optional_param('previewpage', 0, PARAM_INT);
$errorpage = optional_param('errorpage', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$perpage = 25;

$PAGE->set_context($systemcontext);
$basepreviewurl = new moodle_url('/blocks/courseimport/bulk/submit.php');
$PAGE->set_url($basepreviewurl, ['previewpage' => $previewpage, 'errorpage' => $errorpage]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkrolloverheading', 'block_courseimport'));

// Handle confirm before instantiating the form to avoid session writes from form registration.
if ($confirm && confirm_sesskey()) {
    $pack = $SESSION->block_courseimport_bulk_confirm ?? null;
    unset($SESSION->block_courseimport_bulk_confirm);
    if (empty($pack['pairs']) || !is_array($pack['pairs'])) {
        throw new moodle_exception('bulkpreviewinvalidtoken', 'block_courseimport');
    }
    $result = bulk_submitter::submit(
        $pack['pairs'],
        $USER->id,
        null,
        null
    );
    $bulkid = $result['bulkjob']->id;
    \core\notification::success(get_string('bulksubmitcreated', 'block_courseimport', [
        'bulkid' => $bulkid,
        'created' => $result['created'],
        'failed'  => $result['failed'],
    ]));
    foreach ($result['failures'] as $failure) {
        $name = $failure['csv_shortname'] ?? $failure['shortname'] ?? null;
        if ($name === null && !empty($failure['source_id'])) {
            $name = '#' . (int) $failure['source_id'];
        }
        \core\notification::warning(get_string('bulkqueuefailure', 'block_courseimport', (object) [
            'name'  => s($name ?? '?'),
            'error' => s($failure['error'] ?? get_string('bulkunknownerror', 'block_courseimport')),
        ]));
    }
    redirect(new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bulkid]));
}

$maxbytes = bulk_config::max_csv_bytes();
$formurl = new url('/blocks/courseimport/bulk/submit.php');
$form = new csv_upload_form($formurl, ['maxbytes' => $maxbytes]);

if ($form->is_cancelled()) {
    redirect(new url('/blocks/courseimport/bulk/index.php'));
}

if ($fromform = $form->get_data()) {
    $draftid = $fromform->csvfile;
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'itemid, filepath, filename', false);
    $file = null;
    foreach ($files as $f) {
        if ($f->get_filename() !== '.') {
            $file = $f;
            break;
        }
    }
    if (!$file || $file->get_filesize() > $maxbytes) {
        throw new moodle_exception('bulkcsvtoobig', 'block_courseimport', '', $maxbytes);
    }
    $tmp = $file->copy_content_to_temp();
    if (!$tmp) {
        throw new moodle_exception('bulkcsvinvalidtype', 'block_courseimport');
    }
    $rows = csv_parser::parse_file($tmp);
    unlink($tmp);
    if ($rows === []) {
        throw new moodle_exception('bulkcsvrequired', 'block_courseimport');
    }

    $maxrows = bulk_config::max_csv_rows();
    if (count($rows) > $maxrows) {
        throw new moodle_exception('bulkmaxrowsexceeded', 'block_courseimport', '', $maxrows);
    }

    $resolution = module_pair_resolver::resolve($rows);
    $pairs = array_values($resolution['resolved']);
    $seen = [];
    $seencreateshort = [];
    foreach ($pairs as $p) {
        $tid = (int) ($p['target_id'] ?? 0);
        if ($tid > 0 && isset($seen[$tid])) {
            throw new moodle_exception('bulkduplicatetargets', 'block_courseimport');
        }
        if ($tid > 0) {
            $seen[$tid] = true;
        }
        if (!empty($p['pending_create'])) {
            $sn = \core_text::strtolower(trim((string) ($p['csv_shortname'] ?? '')));
            if ($sn !== '' && isset($seencreateshort[$sn])) {
                throw new moodle_exception('bulkduplicateshortnames', 'block_courseimport');
            }
            $seencreateshort[$sn] = true;
        }
    }

    $SESSION->block_courseimport_bulk_confirm = [
        'pairs' => $pairs,
        'errors' => array_values($resolution['errors']),
        'summary' => [
            'rows' => count($rows),
            'resolved' => count($pairs),
            'unmatched' => count($resolution['errors']),
        ],
    ];

    redirect(new moodle_url('/blocks/courseimport/bulk/submit.php', ['previewpage' => 0, 'errorpage' => 0]));
}

$pack = $SESSION->block_courseimport_bulk_confirm ?? null;
if ($pack && (isset($pack['pairs']) || isset($pack['errors']))) {
    $pairs = is_array($pack['pairs'] ?? null) ? $pack['pairs'] : [];
    $errors = is_array($pack['errors'] ?? null) ? $pack['errors'] : [];
    $summary = $pack['summary'] ?? ['rows' => 0, 'resolved' => 0, 'unmatched' => 0];

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulkpreviewheading', 'block_courseimport'), 2);
    echo html_writer::tag('p', get_string('bulkpreviewsummary', 'block_courseimport', (object) $summary));

    if ($pairs) {
        echo $OUTPUT->heading(get_string('bulkpreviewresolved', 'block_courseimport'), 4);
        $pairtotal = count($pairs);
        $pairslice = array_slice($pairs, $previewpage * $perpage, $perpage);
        $pairnavurl = new moodle_url('/blocks/courseimport/bulk/submit.php', ['errorpage' => $errorpage]);

        // Pre-fetch source course names for this page slice.
        $sourceids = array_filter(array_column($pairslice, 'source_id'));
        $sourcenames = [];
        if ($sourceids) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_values($sourceids), SQL_PARAMS_NAMED);
            $sourcenames = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, shortname, fullname');
        }

        if ($pairtotal > $perpage) {
            $from = $previewpage * $perpage + 1;
            $to = min($previewpage * $perpage + $perpage, $pairtotal);
            echo html_writer::div(
                get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $pairtotal]),
                'mb-2 text-muted'
            );
            echo $OUTPUT->paging_bar($pairtotal, $previewpage, $perpage, $pairnavurl, 'previewpage');
        }
        $table = new html_table();
        $table->head = [
            get_string('bulkstatussource', 'block_courseimport'),
            get_string('bulkpreviewnewtargetheading', 'block_courseimport'),
            get_string('shortname'),
            get_string('fullname'),
        ];
        foreach ($pairslice as $p) {
            $sid = (int) ($p['source_id'] ?? 0);
            $sourcecourse = $sourcenames[$sid] ?? null;
            if ($sourcecourse) {
                $sourcecol = html_writer::tag('span', s($sourcecourse->shortname), ['class' => 'font-weight-bold'])
                    . html_writer::tag('br', '')
                    . html_writer::tag('small', s($sourcecourse->fullname), ['class' => 'text-muted']);
            } else {
                $sourcecol = html_writer::tag('span', '#' . $sid, ['class' => 'text-muted']);
            }

            if (!empty($p['pending_create'])) {
                $targetcol = html_writer::tag(
                    'span',
                    get_string('bulkpreviewtargetnew', 'block_courseimport'),
                    ['class' => 'badge badge-info']
                );
            } else {
                $tid = (int) ($p['target_id'] ?? 0);
                $targetcol = html_writer::tag('span', '#' . $tid, ['class' => 'text-muted']);
            }

            $table->data[] = [
                $sourcecol,
                $targetcol,
                s($p['csv_shortname'] ?? ''),
                s($p['csv_fullname'] ?? ''),
            ];
        }
        echo html_writer::table($table);
        if ($pairtotal > $perpage) {
            echo $OUTPUT->paging_bar($pairtotal, $previewpage, $perpage, $pairnavurl, 'previewpage');
        }
    }

    if ($errors) {
        echo $OUTPUT->heading(get_string('bulkpreviewerrors', 'block_courseimport'), 4);
        $errtotal = count($errors);
        $errslice = array_slice($errors, $errorpage * $perpage, $perpage);
        $errnavurl = new moodle_url('/blocks/courseimport/bulk/submit.php', ['previewpage' => $previewpage]);
        if ($errtotal > $perpage) {
            $from = $errorpage * $perpage + 1;
            $to = min($errorpage * $perpage + $perpage, $errtotal);
            echo html_writer::div(
                get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $errtotal]),
                'mb-2 text-muted'
            );
            echo $OUTPUT->paging_bar($errtotal, $errorpage, $perpage, $errnavurl, 'errorpage');
        }
        $etable = new html_table();
        $etable->head = [get_string('bulkpreviewcolrow', 'block_courseimport'), get_string('error')];
        foreach ($errslice as $err) {
            $rowlabel = isset($err['row']) ? (string) $err['row'] : '';
            $etable->data[] = [$rowlabel, s(block_courseimport_bulk_submit_format_error($err))];
        }
        echo html_writer::table($etable);
        if ($errtotal > $perpage) {
            echo $OUTPUT->paging_bar($errtotal, $errorpage, $perpage, $errnavurl, 'errorpage');
        }
    } else {
        echo html_writer::tag('p', get_string('bulkpreviewnoerrors', 'block_courseimport'));
    }

    if (!$pairs) {
        echo $OUTPUT->continue_button(new url('/blocks/courseimport/bulk/index.php'));
        echo $OUTPUT->footer();
        exit;
    }

    $confirmurl = new url('/blocks/courseimport/bulk/submit.php', ['confirm' => 1]);
    echo $OUTPUT->single_button($confirmurl, get_string('bulkconfirmsubmit', 'block_courseimport'));
    echo html_writer::div(
        html_writer::link(new url('/blocks/courseimport/bulk/index.php'), get_string('bulkbacktoupload', 'block_courseimport'), ['class' => 'badge badge-info']),
        'mt-2'
    );
    echo $OUTPUT->footer();
    exit;
}

redirect(new url('/blocks/courseimport/bulk/index.php'));

/**
 * Human-readable unmatched row message for preview.
 *
 * @param array<string, mixed> $err
 * @return string
 */
function block_courseimport_bulk_submit_format_error(array $err): string {
    $msg = (string) ($err['error'] ?? get_string('bulkunknownerror', 'block_courseimport'));
    $parts = [$msg];
    foreach (['target_id', 'modulecode', 'shortname'] as $k) {
        if (!empty($err[$k])) {
            $parts[] = $k . ': ' . $err[$k];
        }
    }
    return implode(' — ', $parts);
}
