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

use \block_courseimport\messenger;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

/**
 * Courseimport process class
 *
 * @package    block_courseimport
 * @copyright  University of Nottingham
 * @author     2012 Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_courseimport_process {
    /**
     * Cron job for courseimport.
     *
     * @global moodle_database $DB
     * @global stdClass $CFG
     * @return void
     */
    public function cron() {
        global $CFG, $DB;
        $log = new local_uonlib_cronlib();

        // If a job still is processing status, should be abandoned.
        \block_courseimport\job::abandon_running();
        // Get the jobs we wish to process and run them.
        $results = \block_courseimport\job::get_queued_jobs();
        foreach ($results as $result) {
            $job = \block_courseimport\job::create_from_record($result);
            // Start processing, successfully will change to 555555, otherwise abandon and email admin.
            $job->set_status(\block_courseimport\job::STATE_PROCESSING);
            $log->logline("Jobid:{$job->id}--Userid:{$job->user}\nImport To Course ID:{$job->target}"
                    . "\nImport From Course ID:{$job->source},\nCreating backup for course ID:{$job->source} now.", false);

            $bc = backup_ui::load_controller($job->bid);
            if ($bc->get_status() == \backup::STATUS_AWAITING && $bc->get_mode() == \backup::MODE_IMPORT) {
                $bc->execute_plan();
            } else {
                $job->set_status(\block_courseimport\job::STATE_FAILED);
                $log->logline("Error! Jobid: {$job->id}. Backup state invalid.");
                continue;
            }
            $tempdestination = $CFG->tempdir . '/backup/' . $job->bid;
            if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
                $log->logline("Error, could not find file in CFG->tempdir/backup folder, "
                        . "Userid:{$job->user}--ImportToCourseid:{$job->target} ---ImportFromCourseid:{$job->target}", false);
                $log->logline(get_string('unknownbackupexporterror', 'error'), false); // Shouldn't happen ever.
                return;
            }
            $rc = new restore_controller($job->bid, $job->target, backup::INTERACTIVE_YES, backup::MODE_IMPORT, $job->user, backup::TARGET_CURRENT_ADDING);
            // Convert the backup if required.... it should NEVER happen.
            if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }
            // Mark the UI finished.
            $rc->finish_ui();
            // Execute prechecks.
            if (!$rc->execute_precheck()) {
                $precheckresults = $rc->get_precheck_results();
                if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                    $message = get_string('precheckfail', 'block_courseimport', array(
                        'timenow' => date('Y-m-d H:i:s'),
                        'jobid' => $job->id,
                        'target' => $job->target,
                        'source' => $job->source,
                    ));
                    $log->logline($message, false);
                    // Send email to Moodle admin.
                    $subject = get_string('alteremailsubject', 'block_courseimport');
                    $isemail = messenger::failure($subject, $message, $job->target);
                    if (!$isemail) {
                        $log->logline("Error! Jobid: {$job->id}. "
                                . "Failed to send email to admin. Content of message below.\n$message", false);
                    }
                }
            } else {
                $message = null;
                // Execute the restore.
                try {
                    $rc->execute_plan();
                } catch (Exception $e) {
                    // need to abandon this job.
                    $job->set_status(\block_courseimport\job::STATE_FAILED);
                    $message = $e->getMessage();
                    $message .= get_string('importfail', 'block_courseimport', array(
                        'timenow' => date('Y-m-d H:i:s'),
                        'jobid' => $job->id,
                        'source' => $job->source,
                        'target' => $job->target,
                    ));
                    $log->logline("Error! Jobid: {$job->id} " . "\n$message", false);
                }

                $rc->destroy(); // Always call these.
                fulldelete($tempdestination);

                if ($message === null) {
                    $job->set_status(\block_courseimport\job::STATE_FINISHED);
                    $log->logline("Success in Jobid: {$job->id}. "
                            . "Import is complete.\nImport From Course ID:{$job->source} -> Import To Course ID:{$job->target}.", false);
                    // Send a message.
                    $isemail = messenger::import_success($job->user, $job->target, $job->targetname, $job->sourcename);
                } else {
                    $subject = get_string('useremailsubject', 'block_courseimport');
                    $isemail = messenger::failure($subject, $message, $job->target); // Send error to Moodle admin.
                }
                if (!$isemail) {
                    $log->logline("Error! Jobid: {$job->id}. Failed to send email to user: {$job->user}", false);
                }
            }
        }
        $results->close();
    }
}
