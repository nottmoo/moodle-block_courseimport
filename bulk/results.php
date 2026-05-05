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
 * Bulk rollover results: thin routing page (params → domain classes → template).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__DIR__, 3) . '/config.php');

use block_courseimport\output\bulk_results_list;
use block_courseimport\output\bulk_status;

require_login();
$systemcontext = context_system::instance();
require_capability('block/courseimport:bulkrollover', $systemcontext);

$bulkid = optional_param('bulkid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$childpage = optional_param('cpage', 0, PARAM_INT);
$completedonly = optional_param('completed', 0, PARAM_INT);
$perpage = 20;
$childperpage = 20;

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkresultsheading', 'block_courseimport'));

global $USER, $OUTPUT;

$blockrenderer = $PAGE->get_renderer('block_courseimport');

if ($bulkid) {
    $bulkstatus = bulk_status::fetch($bulkid, $childpage, $completedonly, $childperpage);
    echo $OUTPUT->header();
    echo $blockrenderer->render($bulkstatus);
    echo $OUTPUT->footer();
    exit;
}

$listpage = bulk_results_list::fetch((int) $USER->id, $page, $perpage);

echo $OUTPUT->header();
echo $blockrenderer->render($listpage);
echo $OUTPUT->footer();
