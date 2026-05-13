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

use block_courseimport\local\bulk_index_page;

global $USER, $OUTPUT;

$systemcontext = context_system::instance();
require_login();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$heading = bulk_index_page::get_page_heading();
$PAGE->set_context($systemcontext);
$PAGE->set_url(bulk_index_page::get_form_action_url());
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('bulkrollover', 'block_courseimport'));

$form = bulk_index_page::make_upload_form(bulk_index_page::get_form_action_url());
if ($fromform = $form->get_data()) {
    redirect(bulk_index_page::get_post_upload_redirect((int) $fromform->csvfile));
}

echo $OUTPUT->header();
ob_start();
$form->display();
$formhtml = ob_get_clean();

echo $OUTPUT->render_from_template(
    'block_courseimport/bulk_upload',
    bulk_index_page::build_upload_template_context((int) $USER->id, $heading, $formhtml)
);
echo $OUTPUT->footer();
