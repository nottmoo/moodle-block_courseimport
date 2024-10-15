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

namespace block_courseimport\output;

use block_courseimport\job;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Progress output component.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress implements \renderable, \templatable {
    /** @var int The id of the course that content is being imported into. */
    public $courseid;

    /** @var bool The job finished in failure. */
    public $failed = false;

    /** @var bool True when the job has completed. */
    public $finished = false;

    /** @var int The id of the job the progress is for. */
    public $job;

    /** @var float The progress of the job. */
    public $progress = 0.0;

    /** @var bool True when progress on the job has started. */
    public $started = false;

    /**
     * Gets the progress for a specific job.
     *
     * This method should be used to create progress objects.
     *
     * @param int $jobid
     * @return progress
     */
    public static function get_for_job(int $jobid): progress {
        $progress = new progress();
        $job = job::instance($jobid);
        $progress->courseid = $job->target;
        $progress->failed = ($job->status === job::STATE_FAILED);
        $progress->finished = in_array($job->status, [job::STATE_FINISHED, job::STATE_FAILED]);
        $progress->job = $job->id;
        $progress->progress = $job->progress;
        $progress->started = ($job->status !== job::STATE_WAITING);
        return $progress;
    }

    /**
     * Gets variables needed for exporting.
     *
     * The output must match the definition (@see \block_courseimport\output\progress::external_export_definition())
     *
     * @return array
     * @throws \moodle_exception
     */
    public function export_for_external(): array {
        $courseurl = new \moodle_url('/course/view.php', ['id' => $this->courseid]);
        return [
            'backupid' => $this->job,
            'courseurl' => $courseurl->out(false),
            'failed' => $this->failed,
            'finished' => $this->finished,
            'started' => $this->started,
            'progress' => $this->progress,
        ];
    }

    /**
     * Defines the structure of data that will be returned for an external function.
     *
     * @return \core_external\external_single_structure
     */
    public static function external_export_definition(): external_single_structure {
        $params = [
            'backupid' => new external_value(PARAM_INT, 'The id of the job', VALUE_REQUIRED),
            'courseurl' => new external_value(PARAM_LOCALURL, 'URL for the import course', VALUE_REQUIRED),
            'failed' => new external_value(PARAM_BOOL, 'Flags if the job has failed', VALUE_REQUIRED),
            'finished' => new external_value(PARAM_BOOL, 'Flags if the job has finished', VALUE_REQUIRED),
            'started' => new external_value(PARAM_BOOL, 'Flags if the job has started', VALUE_REQUIRED),
            'progress' => new external_value(PARAM_FLOAT, 'The progress of the job', VALUE_REQUIRED),
        ];
        return new external_single_structure($params);
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(\renderer_base $output) {
        return $this->export_for_external();
    }
}
