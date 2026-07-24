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
 * Bulk rollover confirmation and queueing page.
 *
 * CSV is uploaded via filepicker on {@see bulk/index.php} (draft filestore). This page
 * receives only the draft item id, streams rows from the stored file, stores a paged
 * preview pack under a unique pack id (so concurrent tabs do not clash), and on confirm
 * streams a non-AJAX progress bar while queueing jobs then sends the browser to bulk/results.php.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\bulk_job;
use block_courseimport\bulk_submitter;
use block_courseimport\local\bulk_submit_confirmation_cache;
use block_courseimport\local\bulk_submit_service;
use block_courseimport\output\bulk_submit_preview;
use core\output\progress_bar;
use core\url;

require_login();
$systemcontext = context_system::instance();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$previewpage = optional_param('previewpage', 0, PARAM_INT);
$errorpage = optional_param('errorpage', 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);
$cancel = optional_param('cancel', false, PARAM_BOOL);
$draftidparam = optional_param('draftid', 0, PARAM_INT);
$packid = optional_param('packid', '', PARAM_ALPHANUM);
$rowsperpage = bulk_submit_confirmation_cache::ROWS_PER_PAGE;

$PAGE->set_context($systemcontext);
$basepreviewurl = new url('/blocks/courseimport/bulk/submit.php');
$pageurlparams = ['previewpage' => $previewpage, 'errorpage' => $errorpage];
if ($packid !== '') {
    $pageurlparams['packid'] = $packid;
}
$PAGE->set_url($basepreviewurl, $pageurlparams);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkrolloverheading', 'block_courseimport'));

// Cancel clears this pack only (other tabs keep their own pack id).
if ($cancel && confirm_sesskey()) {
    if ($packid !== '') {
        bulk_submit_confirmation_cache::delete_pack($packid);
    }
    redirect(new url('/blocks/courseimport/bulk/index.php'));
}

if ($confirm && confirm_sesskey()) {
    if ($packid === '' || !bulk_submit_confirmation_cache::is_valid_packid($packid)) {
        \core\notification::error(get_string('bulkconfirmexpired', 'block_courseimport'));
        redirect(new url('/blocks/courseimport/bulk/index.php'));
    }
    $pairs = bulk_submit_confirmation_cache::get_all_pairs($packid);
    if ($pairs === null) {
        \core\notification::error(get_string('bulkconfirmexpired', 'block_courseimport'));
        redirect(new url('/blocks/courseimport/bulk/index.php'));
    }
    if ($pairs === []) {
        bulk_submit_confirmation_cache::delete_pack($packid);
        \core\notification::error(get_string('bulkconfirminemptypairs', 'block_courseimport'));
        redirect(new url('/blocks/courseimport/bulk/index.php'));
    }
    // Clear after we know the pack is valid so a failed double-submit cannot clear a good preview early.
    bulk_submit_confirmation_cache::delete_pack($packid);

    /* @global stdClass $USER */
    $bulkjob = new bulk_job((int) $USER->id);
    $bulkjob->save();
    $resultsurl = new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bulkjob->id]);

    // Stream non-AJAX progress while queueing so long confirms do not hit proxy/browser idle timeouts (GURU).
    $PAGE->set_cacheable(false);
    $PAGE->navbar->add(get_string('bulkrollover', 'block_courseimport'), new url('/blocks/courseimport/bulk/index.php'));
    $PAGE->navbar->add(get_string('bulksubmitqueuing', 'block_courseimport'));
    \core_php_time_limit::raise(HOURSECS);
    raise_memory_limit(MEMORY_EXTRA);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulksubmitqueuing', 'block_courseimport'));
    $progressbar = new progress_bar();
    $progressbar->create();

    $result = bulk_submitter::submit($pairs, (int) $USER->id, $progressbar, $bulkjob);
    $bulkid = $result['bulkjob']->id;

    \core\notification::success(get_string('bulksubmitcreated', 'block_courseimport', [
        'bulkid' => $bulkid,
        'created' => $result['created'],
        'skipped' => $result['skipped'],
        'failed' => $result['failed'],
    ]));
    foreach ($result['skips'] as $skip) {
        $name = $skip['csv_shortname'] ?? null;
        if ($name === null && !empty($skip['target_id'])) {
            $name = '#' . (int) $skip['target_id'];
        }
        \core\notification::info(get_string('bulkqueueskip', 'block_courseimport', (object) [
            'name' => s($name ?? '?'),
            'reason' => get_string($skip['reason'] ?? 'bulkskipalreadyimported', 'block_courseimport'),
        ]));
    }
    foreach ($result['failures'] as $failure) {
        $name = $failure['csv_shortname'] ?? $failure['shortname'] ?? null;
        if ($name === null && !empty($failure['source_id'])) {
            $name = '#' . (int) $failure['source_id'];
        }
        \core\notification::warning(get_string('bulkqueuefailure', 'block_courseimport', (object) [
            'name' => s($name ?? '?'),
            'error' => s($failure['error'] ?? get_string('bulkunknownerror', 'block_courseimport')),
        ]));
    }

    // Progress already flushed output, so navigate with JS; session notices appear on results.
    echo $OUTPUT->notification(get_string('bulksubmitqueuedone', 'block_courseimport'), \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->continue_button($resultsurl);
    echo \html_writer::script(
        'setTimeout(function() { window.location.href = ' . json_encode($resultsurl->out(false)) . '; }, 400);'
    );
    echo $OUTPUT->footer();
    exit;
}

// Upload lands here only via draft item id from bulk/index.php (filepicker → filestore).
if ($draftidparam) {
    $payload = bulk_submit_service::build_confirmation_payload_from_draft((int) $USER->id, $draftidparam);
    $newpackid = bulk_submit_confirmation_cache::store_paged(
        $payload['pairs'],
        $payload['errors'],
        $payload['summary'],
        $rowsperpage
    );
    redirect(new url('/blocks/courseimport/bulk/submit.php', [
        'packid' => $newpackid,
        'previewpage' => 0,
        'errorpage' => 0,
    ]));
}

if ($packid !== '') {
    $meta = bulk_submit_confirmation_cache::get_meta($packid);
    if ($meta !== null) {
        $summarycounts = $meta['summary'] ?? ['rows' => 0, 'resolved' => 0, 'unmatched' => 0, 'toimport' => 0, 'skipped' => 0];
        $pairtotal = (int) ($meta['pairtotal'] ?? 0);
        $errortotal = (int) ($meta['errortotal'] ?? 0);
        $cachedperpage = (int) ($meta['rowsperpage'] ?? $rowsperpage);
        $pairpagecount = (int) ($meta['pairpagecount'] ?? 0);
        $errorpagecount = (int) ($meta['errorpagecount'] ?? 0);

        if ($pairpagecount > 0) {
            $previewpage = max(0, min($previewpage, $pairpagecount - 1));
        } else {
            $previewpage = 0;
        }
        if ($errorpagecount > 0) {
            $errorpage = max(0, min($errorpage, $errorpagecount - 1));
        } else {
            $errorpage = 0;
        }

        $PAGE->navbar->add(get_string('bulkrollover', 'block_courseimport'), new url('/blocks/courseimport/bulk/index.php'));
        $PAGE->navbar->add(get_string('bulkpreviewheading', 'block_courseimport'));

        $blockrenderer = $PAGE->get_renderer('block_courseimport');
        $preview = bulk_submit_preview::fetch(
            $packid,
            is_array($summarycounts) ? $summarycounts : [],
            bulk_submit_confirmation_cache::get_pairs_page($packid, $previewpage),
            $pairtotal,
            bulk_submit_confirmation_cache::get_errors_page($packid, $errorpage),
            $errortotal,
            $previewpage,
            $errorpage,
            $cachedperpage
        );

        echo $OUTPUT->header();
        echo $blockrenderer->render($preview);
        echo $OUTPUT->footer();
        exit;
    }
    \core\notification::error(get_string('bulkconfirmexpired', 'block_courseimport'));
}

redirect(new url('/blocks/courseimport/bulk/index.php'));
