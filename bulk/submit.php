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
 * Bulk rollover submit page: thin routing (params → services → output).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\local\form\csv_upload_form;
use block_courseimport\local\bulk_submit_confirmation_cache;
use block_courseimport\local\bulk_submit_preview;
use block_courseimport\local\bulk_submit_service;
use core\url;

global $USER, $OUTPUT, $DB;

require_login();
$systemcontext = context_system::instance();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$previewpage = optional_param('previewpage', 0, PARAM_INT);
$errorpage = optional_param('errorpage', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$draftidparam = optional_param('draftid', 0, PARAM_INT);
$rowsperpage = 25;

$PAGE->set_context($systemcontext);
$basepreviewurl = new url('/blocks/courseimport/bulk/submit.php');
$PAGE->set_url($basepreviewurl, ['previewpage' => $previewpage, 'errorpage' => $errorpage]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkrolloverheading', 'block_courseimport'));

// Handle confirm before instantiating the form to avoid session writes from form registration.
if ($confirm && confirm_sesskey()) {
    $pack = bulk_submit_confirmation_cache::get_pack();
    bulk_submit_confirmation_cache::delete_pack();
    if (empty($pack['pairs']) || !is_array($pack['pairs'])) {
        throw new \core\exception\coding_exception(
            'Bulk rollover confirm: expected non-empty pairs array in session after confirm/sesskey; session pack missing or invalid.'
        );
    }
    $result = bulk_submit_service::submit_confirmed_pairs($pack['pairs'], (int) $USER->id);
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

$formurl = new url('/blocks/courseimport/bulk/submit.php');
$form = new csv_upload_form($formurl);

if ($form->is_cancelled()) {
    redirect(new url('/blocks/courseimport/bulk/index.php'));
}

$payload = null;
if ($fromform = $form->get_data()) {
    $csvcontent = $form->get_file_content('csvfile');
    if ($csvcontent === false || $csvcontent === '') {
        throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
    }
    $payload = bulk_submit_service::build_confirmation_payload_from_csv_string($csvcontent);
} else if ($draftidparam) {
    $payload = bulk_submit_service::build_confirmation_payload_from_draft((int) $USER->id, $draftidparam);
}

if ($payload !== null) {
    bulk_submit_confirmation_cache::set_pack([
        'pairs' => $payload['pairs'],
        'errors' => $payload['errors'],
        'summary' => $payload['summary'],
    ]);
    redirect(new url('/blocks/courseimport/bulk/submit.php', ['previewpage' => 0, 'errorpage' => 0]));
}

$pack = bulk_submit_confirmation_cache::get_pack();
if ($pack && (isset($pack['pairs']) || isset($pack['errors']))) {
    $resolvedpairs = is_array($pack['pairs'] ?? null) ? $pack['pairs'] : [];
    $resolutionerrors = is_array($pack['errors'] ?? null) ? $pack['errors'] : [];
    $summarycounts = $pack['summary'] ?? ['rows' => 0, 'resolved' => 0, 'unmatched' => 0];

    echo $OUTPUT->header();
    echo bulk_submit_preview::render_confirmation_preview(
        $OUTPUT,
        $DB,
        $resolvedpairs,
        $resolutionerrors,
        $summarycounts,
        $previewpage,
        $errorpage,
        $rowsperpage
    );
    echo $OUTPUT->footer();
    exit;
}

redirect(new url('/blocks/courseimport/bulk/index.php'));
