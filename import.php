<?php
// This file is part of Moodle - http://moodle.org/
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
 * Defines class for course appointments block
 *
 * @package block_courseimport
 * @author      Yijun Xue
 * @copyright   University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

// Require both the backup and restore libs
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/blocks/courseimport/renderer.php');
require_once($CFG->dirroot . '/blocks/courseimport/lib.php');
require_once($CFG->dirroot . '/backup/util/settings/base_setting.class.php');

// The courseid we are importing to
$courseid = required_param('id', PARAM_INT);
// The id of the course we are importing FROM (will only be set if past first stage
$importcourseid = optional_param('importid', false, PARAM_INT);
$search = optional_param('searchcourses', false, PARAM_INT);

// The target method for the restore (adding or deleting)
$restoretarget = 1;
// Load the course and context
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);
// Must pass login
require_login($course);
// Must hold restoretargetimport in the current course
require_capability('moodle/backup:backuptargetimport', $context);
require_capability('moodle/restore:restoretargetimport', $context);
$heading = get_string('import');
// Set up the page
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->set_url(new moodle_url('/blocks/courseimport/import.php', array('id' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$shortname = $COURSE->shortname;
$coursecode = substr($shortname, 0, strpos($shortname, '-'));
$renderer = $PAGE->get_renderer('block_courseimport');
// Check if we already have a import course id
if ($importcourseid === false || $search !== false) {
    $url = new moodle_url('/blocks/courseimport/import.php', array('id' => $courseid));
    $search = new block_courseimport_search(array('url' => $url), $courseid);
    // show the course selector
    echo $OUTPUT->header();
    //here find and list the user's course
    echo $renderer->import_course_selector($url, $search);
    //$ddd=$_REQUEST["search"];    echo "<input type='hidden' value='-----$ddd----' name='checkserver'>";
    echo $OUTPUT->footer();
    die();
}
// Load the course +context to import from
$importcourse = $DB->get_record('course', array('id' => $importcourseid), '*', MUST_EXIST);
$importcontext = context_course::instance($importcourseid);
// Make sure the user can backup from that course
require_capability('moodle/backup:backuptargetimport', $importcontext);
// Attempt to load the existing backup controller (backupid will be false if there isn't one)
$backupid = optional_param('backup', false, PARAM_ALPHANUM); // Initial settings - false
if (!($bc = backup_ui::load_controller($backupid))) {
    $bc = new backup_controller(backup::TYPE_1COURSE, $importcourse->id, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_YES, backup::MODE_IMPORT, $USER->id);
    $bc->get_plan()->get_setting('users')->set_status(backup_setting::LOCKED_BY_CONFIG);
    $settings = $bc->get_plan()->get_settings();
    // For the initial stage we want to hide all locked settings and if there are
    // no visible settings move to the next stage
    $visiblesettings = false;
    foreach ($settings as $setting) {
        if ($setting->get_status() !== backup_setting::NOT_LOCKED) {
            $setting->set_visibility(backup_setting::HIDDEN);
        } else {
            $visiblesettings = true;
        }
    }
    import_ui::skip_current_stage(!$visiblesettings);
}
//Prepare the import UI
$backup = new import_ui($bc, array('importid' => $importcourse->id, 'target' => $restoretarget));
// Process the current stage
$backup->process();
if ($backup->get_stage() === 2) {
    $setsize = '';
    if (get_config('block_courseimport', 'filesize')) {
        $setsize = (int)get_config('block_courseimport', 'filesize');
    }
    $limitsize = $setsize * 1000000;
    $tsks = $bc->get_plan()->get_tasks();
    foreach ($tsks as $task) {
        foreach ($task->get_settings() as $setting) {

            $tname = $task->get_name();
            $setname = $setting->get_name();
            $pos1 = strpos($setname, 'resource_');
            $pos2 = strpos($setname, '_included');
            $resourceid = '';
            //here check forum
            if(preg_match('/^forum_[0-9]+_[a-z]+/' ,$setname)===1) {
                $setting->set_value("0");
                $setting->make_ui(10, "<b>$tname</b>", array('disabled' => true), null);
                $setting->set_status(7);
            }
            if(preg_match('/^turnitintool_[0-9]+_[a-z]+/' ,$setname)===1) {
                $setting->set_value("0");
                $setting->make_ui(10, "<b>$tname</b>", array('disabled' => true), null);
                $setting->set_status(7);
            }
            if (($pos1 !== false) && ($pos2 !== false)) {
                $resourceid = str_replace("_included", "", str_replace("resource_", "", $setname));
                $afile = block_courseimport_findfilesize($resourceid);
                if ($afile !== false) {
                    $ttype = $afile->ftype;
                    $tsize = (int)$afile->fsize;
                    if (strpos($ttype, 'video') !== false) {
                        $setting->set_value("0");
                        //$setting->make_ui(10, "<b>$tname</b></br><div class='fitemtitle'><label for='id_setting_root_activities'>Include activities </label></div>", array('disabled' => true), null);
                        $videofile =get_string('videofile', 'block_courseimport');
                        $setting->make_ui(10, "$tname <b><u>$videofile</u></b>", array('disabled' => true), null);
                        //$setting->make_ui(10, "<b>$tname</b>", array('disabled' => true), null);
                        $setting->set_status(7);
                    } else {
                    if ($tsize >= $limitsize){
                        $setting->set_value("0");
                        $bigfile =get_string('bigfile', 'block_courseimport');
                        $setting->make_ui(10, "$tname <b><u>$bigfile</u></b>", array('disabled' => true), null);
                        $setting->set_status(7);
                    }
                    }
                }
            }
        }
    }
}

// If this is the confirmation stage remove the filename setting
if ($backup->get_stage() == backup_ui::STAGE_CONFIRMATION) {
    $backup->get_setting('filename')->set_visibility(backup_setting::HIDDEN);
}

if ($backup->get_stage() == backup_ui::STAGE_FINAL) { //backup_ui::STAGE_FINAL=8
    $record = new stdClass();
    $record->courseid = $COURSE->id;
    $record->targetcourseid = $importcourseid;
    $record->userid = $USER->id;
    $record->backupid = $backupid;
    $record->status = 222222; // means job is waiting to be done.
    $record->timecreated = time();
    $record->timemodified = time();
    $DB->insert_record('block_courseimport', $record);
    $jobdone = get_string('jobdone', 'block_courseimport');
    echo $OUTPUT->header();
    echo $OUTPUT->notification($jobdone);
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