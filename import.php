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
 * Page for creating the import job.
 *
 * @package    block_courseimport
 * @author     Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @copyright  University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

use \block_courseimport\import_helper;

// Require both the backup and restore libs
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/blocks/courseimport/renderer.php');

// The courseid we are importing to.
$courseid = required_param('id', PARAM_INT);
// The id of the course we are exporting FROM (will only be set if past first stage).
$importcourseid = optional_param('importid', false, PARAM_INT);
$search = optional_param('searchcourses', false, PARAM_INT);
// The target method for the restore (adding or deleting).
$restoretarget = 1;
// Load the course and context.
$course = get_course($courseid);
$context = context_course::instance($courseid);
// Must pass login.
require_login($course);
// Must hold restoretargetimport in the current course.
require_capability('moodle/backup:backuptargetimport', $context);
require_capability('moodle/restore:restoretargetimport', $context);
$heading = get_string('import');
// Set up the page.
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->set_url(new moodle_url('/blocks/courseimport/import.php', array('id' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$renderer = $PAGE->get_renderer('block_courseimport');

// Before we do anything else check that there are no imports for this course in the queue.
if (\block_courseimport\job::job_queued($courseid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('alreadyimporting', 'block_courseimport'), 'notifyproblem');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $course->id)));
    echo $OUTPUT->footer();
    die();
}

// Check if we already have a import course id.
if ($importcourseid === false || $search !== false) {
    $url = new moodle_url('/blocks/courseimport/import.php', array('id' => $courseid));
    $search = new block_courseimport_search(array('url' => $url), $courseid);
    // Show the course selector.
    echo $OUTPUT->header();
    //Here find and list the user's course.
    echo $renderer->import_course_selector($url, $search);
    echo $OUTPUT->footer();
    die();
}
// Load the course +context to import from.
$importcourse = get_course($importcourseid);
$importcontext = context_course::instance($importcourseid);
// Make sure the user can backup from that course.
require_capability('moodle/backup:backuptargetimport', $importcontext);
// Attempt to load the existing backup controller (backupid will be false if there isn't one).
$backupid = optional_param('backup', false, PARAM_ALPHANUM); // Initial settings - false
if (!($bc = backup_ui::load_controller($backupid))) {
    $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $importcourse->id,
            backup::FORMAT_MOODLE,
        backup::INTERACTIVE_YES,
            backup::MODE_IMPORT,
            $USER->id
    );
    $plan = $bc->get_plan();
    import_helper::disbable_userdata_import($plan);
    // For the initial stage we want to hide all locked settings and if there are no visible settings move to the next stage.
    $visiblesettings = import_helper::hide_locked_settings($plan);
    import_ui::skip_current_stage(!$visiblesettings);
}
// Prepare the import UI.
$backup = new import_ui($bc, array('importid' => $importcourse->id, 'target' => $restoretarget));
// Process the current stage.
$backup->process();
if ($backup->get_stage() === backup_ui::STAGE_SCHEMA) {
    $tasks = $bc->get_plan()->get_tasks();
    foreach ($tasks as $task) {
        import_helper::filter_task($task);
    }
}

// If this is the confirmation stage remove the filename setting.
if ($backup->get_stage() == backup_ui::STAGE_CONFIRMATION) {
    $backup->get_setting('filename')->set_visibility(backup_setting::HIDDEN);
}

if ($backup->get_stage() == backup_ui::STAGE_FINAL) { //backup_ui::STAGE_FINAL=8
    $backup->get_controller()->finish_ui();

    $job = new \block_courseimport\job($importcourseid, $course->id, $backupid, $USER->id);
    $job->save();

    $jobdone = get_string('jobdone', 'block_courseimport');
    echo $OUTPUT->header();
    echo $OUTPUT->notification($jobdone, 'notifysuccess');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $course->id)));
    echo $OUTPUT->footer();
    die();
} else {
    // Otherwise save the controller and progress
    $backup->save_controller();
}
// Adjust the page for the stage
$PAGE->set_title($heading . ': ' . $backup->get_stage_name());
$PAGE->set_heading($heading . ': ' . $backup->get_stage_name());
$PAGE->navbar->add($backup->get_stage_name());

// Display the current stage
echo $OUTPUT->header();
if ($backup->enforce_changed_dependencies()) {
    echo $renderer->dependency_notification(get_string('dependenciesenforced', 'backup'));
}
echo $renderer->progress_bar($backup->get_progress_bar());
echo $backup->display($renderer);
$backup->destroy();
unset($backup);
echo $OUTPUT->footer();
