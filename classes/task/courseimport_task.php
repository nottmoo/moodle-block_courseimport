<?php
// This file is part of the the courseimport block plugin for Moodle
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

namespace block_courseimport\task;

use block_courseimport\job;
use block_courseimport\job_failed;
use block_courseimport\messenger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

/**
 * Courseimport task class
 *
 * @package    block_courseimport
 * @author     Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @copyright  2014 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseimport_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'block_courseimport');
    }

    /**
     * Do the scheduled job.
     */
    public function execute() {
        // If a job still is processing status, should be abandoned.
        job::abandon_running();

        // Get the jobs we wish to process and run them.
        $results = job::get_queued_jobs();
        foreach ($results as $result) {
            $job = job::create_from_record($result);
            $this->process_job($job);
        }
        $results->close();
    }

    /**
     * Imports content from the source course into the target course.
     *
     * @param \block_courseimport\job $job
     */
    protected function process_job(job $job) {
        // Start processing, successfully will change to 555555, otherwise abandon and email admin.
        $job->set_status(job::STATE_PROCESSING);
        mtrace("Jobid: {$job->id}, Userid: {$job->user}, Import course: {$job->target}, Export course:{$job->source}");
        mtrace("Creating backup for course ID:{$job->source}");
        try {
            $this->backup($job);
            $this->restore($job);
        } catch (job_failed $e) {
            $job->set_status(job::STATE_FAILED);
            if (messenger::failure($e->subject, $e->getMessage(), $job->target)) {
                mtrace("Error! Jobid: {$job->id}. Failed to send email to admin.");
            }
        }
    }

    /**
     * Finishes the backup of the source course.
     *
     * @param \block_courseimport\job $job
     * @throws \block_courseimport\job_failed
     */
    protected function backup(job $job) {
        $bc = \backup_ui::load_controller($job->bid);
        $bc->set_progress(new \core\progress\db_updater($job->id, 'block_courseimport', 'backupprogress'));
        if ($bc->get_status() == \backup::STATUS_AWAITING && $bc->get_mode() == \backup::MODE_IMPORT) {
            $bc->execute_plan();
        } else {
            $message = "Error! Jobid: {$job->id}. Backup state invalid.";
            mtrace($message);
            throw new job_failed($message);
        }
    }

    /**
     * Restores the backup into the target course.
     *
     * @param \block_courseimport\job $job
     * @throws \block_courseimport\job_failed
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \restore_controller_exception
     */
    protected function restore(job $job) {
        global $CFG, $DB;

        // Check the backup file is there.
        $tempdestination = $CFG->tempdir . '/backup/' . $job->bid;
        if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
            $message = "Error: Job ({$job->id}) could not find backup file $tempdestination.";
            mtrace($message);
            mtrace(get_string('unknownbackupexporterror', 'error')); // Shouldn't happen ever.
            throw new job_failed($message);
        }

        $rc = new \restore_controller(
            $job->bid,
            $job->target,
            \backup::INTERACTIVE_YES,
            \backup::MODE_IMPORT,
            $job->user,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->set_progress(new \core\progress\db_updater($job->id, 'block_courseimport', 'restoreprogress'));
        $this->prepare_for_restore($rc, $job);

        $preservedtarget = $DB->get_record('course', ['id' => $job->target], 'id, fullname, shortname, idnumber', IGNORE_MISSING);
        if (!$preservedtarget) {
            $message = "Error: Job ({$job->id}) target course {$job->target} no longer exists.";
            mtrace($message);
            throw new job_failed($message);
        }

        // Execute the restore.
        try {
            $rc->execute_plan();
        } catch (\Exception $e) {
            // We need to abandon this job.
            $job->set_status(job::STATE_FAILED);
            $message = $e->getMessage();
            $params = [
                'timenow' => date('Y-m-d H:i:s'),
                'jobid' => $job->id,
                'source' => $job->source,
                'target' => $job->target,
            ];
            $message .= get_string('importfail', 'block_courseimport', $params);
            mtrace("Error! Jobid: {$job->id} " . "\n$message");
            $subject = get_string('useremailsubject', 'block_courseimport');
            throw new job_failed($message, $subject);
        } finally {
            $rc->destroy();
            fulldelete($tempdestination);
        }

        // Restore may reset metadata from source course; keep CSV-driven fields.
        if ($preservedtarget) {
            if (trim((string) $preservedtarget->fullname) !== '') {
                $DB->set_field('course', 'fullname', $preservedtarget->fullname, ['id' => (int) $job->target]);
            }
            $DB->set_field('course', 'shortname', $preservedtarget->shortname, ['id' => (int) $job->target]);
            $DB->set_field('course', 'idnumber', (string) $preservedtarget->idnumber, ['id' => (int) $job->target]);
        }

        $job->set_status(job::STATE_FINISHED);
        mtrace("Success in Jobid: {$job->id}. Import from course {$job->source} to course {$job->target} completed.");

        // Send a message.
        if (!messenger::import_success($job->user, $job->target, $job->targetname, $job->sourcename)) {
            mtrace("Error! Jobid: {$job->id}. Failed to send email to user: {$job->user}");
        }
    }

    /**
     * Ensures that the restore controller is in the correct state to be executed.
     *
     * @param \restore_controller $rc
     * @param \block_courseimport\job $job
     * @throws \block_courseimport\job_failed
     */
    protected function prepare_for_restore(\restore_controller $rc, job $job) {
        // Convert the backup if required.... it should NEVER happen.
        if ($rc->get_status() == \backup::STATUS_REQUIRE_CONV) {
            $rc->convert();
        }

        // Mark the UI finished.
        $rc->finish_ui();

        // Execute prechecks.
        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            $this->display_precheck_problems($precheckresults);

            if (!empty($precheckresults['errors'])) {
                // We cannot proceed while there are errors.
                $params = [
                    'timenow' => date('Y-m-d H:i:s'),
                    'jobid' => $job->id,
                    'target' => $job->target,
                    'source' => $job->source,
                ];
                $message = get_string('precheckfail', 'block_courseimport', $params);
                mtrace($message);
                throw new job_failed($message);
            }

            // We may proceed when there are only warnings.
        }
    }

    /**
     * Displays the list of errors and warnings in pre-checks
     *
     * This is so that they will appear in any task logs, which will help us to
     * check what is wrong when there is a problem with imports.
     *
     * @param array $precheckresults
     */
    protected function display_precheck_problems(array $precheckresults): void {
        if (!empty($precheckresults['errors'])) {
            mtrace('Pre-check errors:');
            foreach ($precheckresults['errors'] as $error) {
                mtrace("* {$error}");
            }
        }

        if (!empty($precheckresults['warnings'])) {
            mtrace('Pre-check warnings:');
            foreach ($precheckresults['warnings'] as $warning) {
                mtrace("* {$warning}");
            }
        }
    }
}
