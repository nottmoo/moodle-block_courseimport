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
 * File containing the job class.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for import jobs.
 *
 * @property-read int $id The database id of the job.
 * @property-read int $source The id of the course we are exporting from.
 * @property-read string $bid The backup id for the job.
 * @property-read float $progress The progress of the import.
 * @property-read \course_context $sourcecontext The context of the course we are exporting from.
 * @property-read string $sourcename The name of the course we are exporting from.
 * @property-read string $status The status of the export job.
 * @property-read int $target The id of the course we are importing to.
 * @property-read \coursecontext $targetcontext The contect of the course we are impoting to.
 * @property-read string $targetname The name of the course we are importing to.
 * @property-read int $user The id of the user who triggered the job.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job {
    /** Import failed. */
    const STATE_FAILED = '777777';

    /** Import finished successfully. */
    const STATE_FINISHED = '555555';

    /** Import is being run by cron. */
    const STATE_PROCESSING = '666666';

    /** The Job is waiting to be processed. */
    const STATE_WAITING = '222222';

    /** @var string The id of the backup controller for this import. */
    protected $bid;

    /** @var int The id of the import job. */
    protected $id;

    /** @var float The progress of the import. 0.0 is no progress, 1.0 is complete. */
    protected $progress = 0.0;

    /** @var int The id of the course we are exporting from. */
    protected $source;

    /** @var string The name of the course we are exporting from. */
    protected $sourcename;

    /** @var string The status of the job. */
    protected $status;

    /** @var int The id of the course we are importing to. */
    protected $target;

    /** @var string The name of the course we are importing into. */
    protected $targetname;

    /** @var int The id of the user who started the import. */
    protected $user;

    /**
     * Constructor for the job class.
     *
     * @param int $source The id of the course that content will be exported from.
     * @param int $target The id of the course that content will be imported to.
     * @param string $bid The backup controller id for the backup.
     * @param int $user The id of the user who started the job.
     */
    public function __construct(int $source, int $target, string $bid, int $user) {
        $this->source = $source;
        $this->target = $target;
        $this->bid = $bid;
        $this->user = $user;
        $this->status = static::STATE_WAITING;
    }

    /**
     * Gets protected properties.
     *
     * @param string $name The name of a peroperty
     * @return mixed
     * @throws \coding_exception
     */
    public function __get(string $name) {
        $functionname = "get_$name";
        if (method_exists($this, $functionname)) {
            return $this->$functionname();
        } else if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new \coding_exception('Invalid property requested');
    }

    /**
     * Sets any running jobs to a failed state.
     *
     * This should only be used by the cron task when it starts running.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function abandon_running() {
        global $DB;
        $jobs = $DB->get_records('block_courseimport', ['status' => static::STATE_PROCESSING]);
        foreach ($jobs as $abandon) {
            $job = static::create_from_record($abandon);
            $job->set_status(static::STATE_FAILED);
            $params = [
                'timenow' => date('Y-m-d H:i:s'),
                'jobid' => $job->id,
                'userid' => $job->user,
                'target' => $job->target,
                'source' => $job->source,
            ];
            $message = get_string('abandonedjobmessage', 'block_courseimport', $params);
            $subject = get_string('alertemailsubject', 'block_courseimport');
            $isemail = \block_courseimport\messenger::failure($subject, $message, $job->target);
            if (!$isemail) {
                mtrace("Error! Jobid: {$job->id}. Failed to send email to admin. Email message: .\n$message");
            }
        }
    }

    /**
     * Creates an instance of the job class from a database record.
     *
     * @param \stdClass $record Database record from the block_courseimport table.
     * @return job
     */
    public static function create_from_record(\stdClass $record): job {
        $job = new job($record->source, $record->target, $record->backupid, $record->userid);
        $job->id = $record->id;
        $job->status = $record->status;
        if (isset($record->fromname)) {
            $job->sourcename = $record->fromname;
        }
        if (isset($record->toname)) {
            $job->targetname = $record->toname;
        }
        // Backup and import progress are stored separately, so we need to combine them.
        $job->progress = (($record->backupprogress + $record->restoreprogress) / 2);
        return $job;
    }

    /**
     * Gets a specific job.
     *
     * @param int $id The database id of the job record.
     * @return \block_courseimport\job
     * @throws \dml_exception when the job does not exist.
     */
    public static function instance(int $id): job {
        global $DB;
        $record = $DB->get_record('block_courseimport', ['id' => $id], '*', MUST_EXIST);
        return static::create_from_record($record);
    }

    /**
     * Checks if a course has a job queued for processing.
     *
     * @param int $courseid The id of the course.
     * @return bool
     * @throws \dml_exception
     */
    public static function job_queued(int $courseid): bool {
        global $DB;
        list($table, $conditions, $params) = static::job_for_course_sql($courseid);
        return $DB->record_exists_select($table, $conditions, $params);
    }

    /**
     * Gets the queued job for the course.
     *
     * @param int $courseid
     * @return job
     * @throws \dml_exception
     */
    public static function get_queued_job(int $courseid): job {
        global $DB;
        list($table, $conditions, $params) = static::job_for_course_sql($courseid);
        $record = $DB->get_record_select($table, $conditions, $params);
        return static::create_from_record($record);
    }

    /**
     * Gets the SQL fragments needed to get an active job for a course.
     *
     * @param int $courseid
     * @return array
     */
    protected static function job_for_course_sql(int $courseid): array {
        $table = 'block_courseimport';
        $conditions = "target = :courseid AND (status = :status1 OR status = :status2)";
        $params = [
                'courseid' => $courseid,
                'status1' => static::STATE_WAITING,
                'status2' => static::STATE_PROCESSING,
        ];
        return [$table, $conditions, $params];
    }

    /**
     * Gets the context of the source course.
     *
     * @return \context_course
     */
    protected function get_sourcecontext(): \context_course {
        return \context_course::instance($this->source);
    }

    /**
     * Get the name of the source course.
     *
     * @return string
     */
    protected function get_sourcename(): string {
        if (!isset($this->sourcename)) {
            $this->sourcename = $this->get_sourcecontext()->get_context_name(false);
        }
        return $this->sourcename;
    }

    /**
     * Gets the context of the target course.
     *
     * @return \context_course
     */
    protected function get_targetcontext(): \context_course {
        return \context_course::instance($this->target);
    }

    /**
     * Gets the name of the target course.
     *
     * @return string
     */
    protected function get_targetname(): string {
        if (!isset($this->targetname)) {
            $this->targetname = $this->get_targetcontext()->get_context_name(false);
        }
        return $this->targetname;
    }

    /**
     * Gets all the queued import jobs.
     *
     * @return \moodle_recordset
     * @throws \dml_exception
     */
    public static function get_queued_jobs(): \moodle_recordset {
        global $DB;
        $sql = "SELECT ci.*, tc.fullname AS fromname, sc.fullname AS toname
                  FROM {block_courseimport} ci
                  JOIN {course} tc ON ci.source = tc.id
                  JOIN {course} sc ON ci.target = sc.id
                 WHERE ci.status = :status";
        $params = ['status' => self::STATE_WAITING];
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Updates the status of the job.
     *
     * @param string $status One of the job::STATE_* constants.
     * @return void
     * @throws \dml_exception
     */
    public function set_status(string $status) {
        global $DB;
        $this->status = $status;
        if (!isset($this->id)) {
            // The job has not been saved to the database.
            return;
        }
        $record = (object)[
            'id' => $this->id,
            'status' => $this->status,
            'timemodified' => time(),
        ];
        $DB->update_record('block_courseimport', $record);
    }

    /**
     * Saves the job in the database.
     *
     * @throws \dml_exception
     */
    public function save() {
        if (!isset($this->id)) {
            $this->insert();
        } else {
            $this->update();
        }
    }

    /**
     * Creates a new job record in the database.
     *
     * @throws \dml_exception
     */
    protected function insert() {
        global $DB;
        $time = time();
        $record = (object)[
            'target' => $this->target,
            'source' => $this->source,
            'userid' => $this->user,
            'backupid' => $this->bid,
            'status' => $this->status,
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $this->id = $DB->insert_record('block_courseimport', $record);
    }

    /**
     * Updates the job record in the database.
     *
     * @throws \dml_exception
     */
    protected function update() {
        global $DB;
        $record = (object)[
            'id' => $this->id,
            'target' => $this->target,
            'source' => $this->source,
            'userid' => $this->user,
            'backupid' => $this->bid,
            'status' => $this->status,
            'timemodified' => time(),
        ];
        $DB->update_record('block_courseimport', $record);
    }
}