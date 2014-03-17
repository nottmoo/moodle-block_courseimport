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
 * This file contains language strings used in the Course life management block
 * @package block_courseimport
 * @copyright University of Nottingham
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/blocks/courseimport/lib.php');

set_time_limit(0);
raise_memory_limit(MEMORY_EXTRA);

$timesetting = get_config('block_courseimport', 'crontime');
if (($timesetting) and (!empty($timesetting))) {
    echo "Time ranges setting " . $timesetting . "\n";
    $ranges = explode("==", $timesetting);
    $checkresult = false;
    foreach($ranges as $range) {
         if(preg_match('/^(([0-1][0-9]|[2][0-3]):([0-5][0-9])-([0-1][0-9]|[2][0-3]):([0-5][0-9]))/' ,$range)===1) {
            $rlist = explode("-", $range);
            $checkresult += block_courseimport_timecheck($rlist[0],$rlist[1]);
        } else {
            echo "\ntime ranges set in the block admin's setting page ( $range )is not in right format and it blocked import process, die now ! \n";
            die();
        }
      }
    if ($checkresult == 0 ) {
        echo "\n time ranges set in the block admin's setting page blocked import process die now ! \n";
        die();
    }
}

$argument1 = null;
if (isset($argv[1])) {
    $argument1 = $argv[1]; // blocks/courseimport/newbackup.php 0 or 1.  if 0 stop, if 1 start
    //222222:job waiting
    //444444:block jobs
    //333333:backup file created
    echo "\n -- Passed argument -- $argument1 --\n";

    if ($argument1 === 0) {
        $table = 'block_courseimport';
        $select = "status ='222222'";
        $counter = $DB->count_records_select($table, $select);
        echo "-- Jobs count -- $counter --\n";
        if ($counter > 1) {
            //get all jobs except 1st, as it is running
            echo "\n-- Block all jobs except 1st --\n";
            $sql = 'SELECT t2.* FROM {block_courseimport} t1 JOIN {block_courseimport} t2 ON t1.id + 1 = t2.id';
            $results = $DB->get_records_sql($sql);
            foreach ($results as $result) {
                $temprecord = new stdClass();
                $temprecord->id = $result->id;
                $temprecord->status = 444444;
                $temprecord->timemodified = time();
                $DB->update_record('block_courseimport', $temprecord);
            }
            echo "\n All jobs been blocked except 1st \n";
        }
    }
    if ($argument1 === 1) {
        $sql = 'SELECT * FROM {block_courseimport} where status = 444444';
        $results = $DB->get_records_sql($sql);
        foreach ($results as $result) {
            $temprecord = new stdClass();
            $temprecord->id = $result->id;
            $temprecord->status = 222222;
            $temprecord->timemodified = time();
            $DB->update_record('block_courseimport', $temprecord);
        }
        echo "\n change stauts to restart processing \n";
    }
    die();
}
//Start jobs
$countstopjobs = $DB->count_records_sql('SELECT COUNT(*) FROM {block_courseimport} where status ="444444"');
$results = $DB->get_records_sql('SELECT * FROM {block_courseimport} where status ="222222" order by id asc');
//Need countstopjobs to avoid execut any new added job.
if ( !empty($results) && ($countstopjobs > 0) ) {
    echo "\n --- Processing is blocked. To unblock, run php -f  /var/www/html/blocks/courseimport/newbackup.php 1 . \n";
    die();
} else {
    foreach ($results as $firstcourse) {
        echo "\n Next processing will start in 20 seconds or you can stop cron now  --  " . date('l jS \of F Y h:i:s A') . " \n";
        sleep(20);
        $courseid = $firstcourse->courseid;
        $coursecontext = context_course::instance($courseid);
        $contextid = $coursecontext->id;
        $importid = $firstcourse->backupid;
        $targetcourseid=$firstcourse->targetcourseid;
        $backupid = $importid;
        // The target method for the restore (adding or deleting)
        $restoretarget = 1;
        $userid = $firstcourse->userid;

        echo "\n Userid:$userid--ImportToCourseid:$courseid ---ImportFromCourseid:$targetcourseid , create backup for $targetcourseid now. \n";

        $bc = backup_ui::load_controller($importid);
        $backup = new block_courseimport_import_ui($bc, array('importid' => $importid, 'target' => $restoretarget));
        $backup->execute();
        $backup->destroy();
        unset($backup);
        $tempdestination = $CFG->tempdir . '/backup/' . $backupid;
        if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
            print_error('unknownbackupexporterror'); // shouldn't happen ever
            die();
        }
        $record = new stdClass();
        $record->id = $firstcourse->id;
        $record->status = 333333;
        $record->timemodified = time();
        $DB->update_record('block_courseimport', $record);
        unset($record);

        echo "\n backupfile had been created successfully, so job's status changed to 333333, , now start to restoring. \n";

        list($context, $course, $cm) = get_context_info_array($contextid);
        $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_YES, backup::MODE_IMPORT, $userid, 1);
        // Mark the UI finished.
        $rc->finish_ui();
        // Execute prechecks
        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                echo "\n Execute prechecks Error in precheck when restoring , cron stoped.\n";
                die();
            }
        } else {
            // Execute the restore
            $rc->execute_plan();
            $rc->destroy();
            fulldelete($tempdestination);
            echo "\n --Job " . $importid . " finished --" . date('l jS \of F Y h:i:s A') . " \n";
        }
    }

    unset($results);
    die();
}