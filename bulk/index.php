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

/**
 * Bulk rollover CSV submission page.
 *
 * Shows the upload form and (optionally) a notice for the user's current queued job.
 * Full job history/details are shown on bulk/results.php.
 * 
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\bulk_config;
use block_courseimport\bulk_job;
use block_courseimport\form\csv_upload_form;
use block_courseimport\import_helper;
use core\url;

$systemcontext = context_system::instance();
require_login();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$heading = get_string('bulkrolloverheading', 'block_courseimport');
$PAGE->set_context($systemcontext);
$PAGE->set_url(new url('/blocks/courseimport/bulk/index.php'));
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('admin');

$maxbytes = bulk_config::max_csv_bytes();
$submiturl = new url('/blocks/courseimport/bulk/index.php');
$form = new csv_upload_form($submiturl, ['maxbytes' => $maxbytes]);

if ($fromform = $form->get_data()) {
    redirect(new url('/blocks/courseimport/bulk/submit.php', ['draftid' => (int) $fromform->csvfile]));
}

echo $OUTPUT->header();
ob_start();
$form->display();
$formhtml = ob_get_clean();

// Show a notice for the user's most recent queued bulk job.
$activebulkjob = bulk_job::get_most_recent_queued_for_user((int) $USER->id);

$statusurl = (new url('/blocks/courseimport/bulk/results.php'))->out(false);

$labels = import_helper::enabled_profile_sidebar_labels();
$enableditems = [];
foreach ($labels as $label) {
    $enableditems[] = ['label' => $label];
}

$settingsurl = (new moodle_url('/admin/settings.php', ['section' => 'blocksettingcourseimport']))->out(false);

$activebulknotice = null;
if ($activebulkjob) {
    $jobstatusurl = (new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $activebulkjob->id]))->out(false);
    $activebulknotice = [
        'url'   => $jobstatusurl,
        'jobid' => (int) $activebulkjob->id,
    ];
}

echo $OUTPUT->render_from_template('block_courseimport/bulk_upload', [
    'heading' => $heading,
    'statusurl' => $statusurl,
    'formhtml' => $formhtml,
    'activebulknotice' => $activebulknotice,
    'enableditems' => $enableditems,
    'settingsurl' => $settingsurl,
]);
echo $OUTPUT->footer();
