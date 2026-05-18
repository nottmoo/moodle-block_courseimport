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

namespace block_courseimport;

/**
 * Parent bulk rollover job metadata.
 *
 * @property-read int|null $id The database id of the parent bulk job.
 * @property-read int $userid The id of the user who submitted the bulk job.
 * @property-read string $status Parent bulk status ({@see self::STATUS_QUEUED} etc.).
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_job {
    /** @var string Non-terminal parent bulk batch : set at creation and kept until every child
     * import has finished, including while children are actively processing. */
    public const STATUS_QUEUED = 'queued';

    /** @var string Terminal outcome: all child imports finished successfully. */
    public const STATUS_COMPLETED = 'completed';

    /** @var string Terminal outcome: all child imports failed (or no child imports could be queued). */
    public const STATUS_FAILED = 'failed';

    /** @var string Terminal outcome: batch finished with a mix of successful and failed child imports.*/
    public const STATUS_PARTIAL = 'partial';

    /**  @var int|null Database id of the parent bulk job row (null until first save()). */
    protected $id;

    /** @var int User id that owns/submitted this bulk job. */
    protected $userid;

    /** @var string Parent job lifecycle status ({@see self::STATUS_QUEUED} etc.). */
    protected $status = self::STATUS_QUEUED;

    /** @var int Total number of child imports expected for this bulk batch. */
    protected $totalcount = 0;

    /** @var int Number of child imports that have reached the finished/success state. */
    protected $completedcount = 0;

    /** @var int Number of child imports that have reached a failed terminal state. */
    protected $failedcount = 0;

    /** @var \core\clock Clock dependency used for deterministic timestamps in writes. */
    protected \core\clock $clock;

    /**
     * Creates an in-memory parent bulk job model.
     *
     * @param int $userid User id that owns/submitted this bulk batch.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
        $this->clock = \core\di::get(\core\clock::class);
    }

    /**
     * Gets protected properties.
     *
     * @param string $name Property name.
     * @return mixed Property value for supported names.
     */
    public function __get(string $name) {
        switch ($name) {
            case 'id':
                return $this->id;
            case 'userid':
                return $this->userid;
            case 'status':
                return $this->status;
        }
        throw new \coding_exception('Invalid bulk_job property');
    }

    /**
     * Sets the parent bulk status and persists immediately when already saved.
     *
     * @param string $status One of {@see self::STATUS_QUEUED}, {@see self::STATUS_COMPLETED},
     *                       {@see self::STATUS_FAILED}, {@see self::STATUS_PARTIAL}.
     * @return void
     */
    public function set_status(string $status): void {
        $this->status = $status;
        if ($this->id) {
            $this->persist_counts_and_status();
        }
    }

    /**
     * Replaces the parent counters and persists immediately when already saved.
     *
     * @param int $total Total child imports expected for this bulk batch.
     * @param int $completed Number of child imports finished successfully.
     * @param int $failed Number of child imports finished with failure.
     * @return void
     */
    public function set_counts(int $total, int $completed, int $failed): void {
        $this->totalcount = $total;
        $this->completedcount = $completed;
        $this->failedcount = $failed;
        if ($this->id) {
            $this->persist_counts_and_status();
        }
    }

    /**
     * Inserts a new parent row or updates persisted status/counters for an existing one.
     *
     * @return void
     * @throws \dml_exception
     */
    public function save(): void {
        global $DB;
        $now = $this->clock->time();
        if (!$this->id) {
            $record = (object) [
                'userid' => $this->userid,
                'status' => $this->status,
                'total_count' => $this->totalcount,
                'completed_count' => $this->completedcount,
                'failed_count' => $this->failedcount,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $this->id = $DB->insert_record('block_courseimport_bulk_job', $record);
        } else {
            $this->persist_counts_and_status();
        }
    }

    /**
     * Persists current status and counters for an existing parent row.
     *
     * @return void
     * @throws \dml_exception
     */
    protected function persist_counts_and_status(): void {
        global $DB;
        $record = (object) [
            'id' => $this->id,
            'status' => $this->status,
            'total_count' => $this->totalcount,
            'completed_count' => $this->completedcount,
            'failed_count' => $this->failedcount,
            'timemodified' => $this->clock->time(),
        ];
        $DB->update_record('block_courseimport_bulk_job', $record);
    }

    /**
     * Load a bulk job row by id.
     *
     * @param int $id Parent bulk job id.
     * @return \stdClass|null Matching DB row, or null when no row exists.
     */
    public static function get_record(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('block_courseimport_bulk_job', ['id' => $id], '*', IGNORE_MISSING);
        return $rec ?: null;
    }

    /**
     * Child import job rows for a bulk parent job (newest child first).
     *
     * For paginated UI, prefer {@see self::count_import_jobs_for_bulk_job()} and
     * {@see self::get_import_jobs_for_bulk_job_page()} to avoid loading every child row.
     *
     * @param int $bulkjobid Parent bulk job id.
     * @return array<int, \stdClass>
     */
    public static function get_import_jobs_for_bulk_job(int $bulkjobid): array {
        global $DB;
        $sql = "SELECT j.id, j.target, j.source, j.status, j.timecreated,
                       tc.fullname AS targetname, sc.fullname AS sourcename
                  FROM {block_courseimport} j
             LEFT JOIN {course} tc ON tc.id = j.target
             LEFT JOIN {course} sc ON sc.id = j.source
                 WHERE j.bulk_job_id = :bid
              ORDER BY j.id DESC";
        return $DB->get_records_sql($sql, ['bid' => $bulkjobid]);
    }

    /**
     * Total child import jobs for a bulk parent (optionally only finished jobs).
     *
     * @param int $bulkjobid Parent bulk job id.
     * @param bool $completedonly If true, count rows whose status is {@see job::STATE_FINISHED}.
     * @return int
     */
    public static function count_import_jobs_for_bulk_job(int $bulkjobid, bool $completedonly): int {
        global $DB;
        $params = ['bid' => $bulkjobid];
        $statussql = '';
        if ($completedonly) {
            $statussql = ' AND j.status = :finished';
            $params['finished'] = job::STATE_FINISHED;
        }
        $sql = "SELECT COUNT(1)
                  FROM {block_courseimport} j
                 WHERE j.bulk_job_id = :bid
                       {$statussql}";
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * One page of child import job rows for a bulk parent (newest child first).
     *
     * @param int $bulkjobid Parent bulk job id.
     * @param int $limit Max rows.
     * @param int $offset Row offset.
     * @param bool $completedonly If true, only rows whose status is {@see job::STATE_FINISHED}.
     * @return array<int, \stdClass>
     */
    public static function get_import_jobs_for_bulk_job_page(
        int $bulkjobid,
        int $limit,
        int $offset,
        bool $completedonly
    ): array {
        global $DB;
        $params = ['bid' => $bulkjobid];
        $statussql = '';
        if ($completedonly) {
            $statussql = ' AND j.status = :finished';
            $params['finished'] = job::STATE_FINISHED;
        }
        $sql = "SELECT j.id, j.target, j.source, j.status, j.timecreated,
                       tc.fullname AS targetname, sc.fullname AS sourcename
                  FROM {block_courseimport} j
             LEFT JOIN {course} tc ON tc.id = j.target
             LEFT JOIN {course} sc ON sc.id = j.source
                 WHERE j.bulk_job_id = :bid
                       {$statussql}
              ORDER BY j.id DESC";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Count child import rows by coarse lifecycle bucket.
     *
     * @param array<int, \stdClass> $childrecords Rows from {@see self::get_import_jobs_for_bulk_job()}.
     * @return \stdClass Object with int fields: active, finished, failed.
     */
    public static function summarize_child_import_states(array $childrecords): \stdClass {
        $activecount = 0;
        $finishedcount = 0;
        $failedcount = 0;
        foreach ($childrecords as $child) {
            if ($child->status === job::STATE_WAITING || $child->status === job::STATE_PROCESSING) {
                $activecount++;
            } else if ($child->status === job::STATE_FINISHED) {
                $finishedcount++;
            } else {
                $failedcount++;
            }
        }
        $out = new \stdClass();
        $out->active = $activecount;
        $out->finished = $finishedcount;
        $out->failed = $failedcount;
        return $out;
    }

    /**
     * If the parent bulk row is still {@see self::STATUS_QUEUED} but every child import is terminal,
     * realign parent counts and status from the child rows.
     *
     * This runs after each child job in the scheduled task (so the DB is corrected without opening the
     * results page) and when rendering bulk results as a safety net if incremental updates were missed.
     *
     * @param int $bulkjobid Parent bulk job id.
     * @return void
     */
    public static function reconcile_queued_parent_if_stale(int $bulkjobid): void {
        global $DB;
        $bulk = $DB->get_record('block_courseimport_bulk_job', ['id' => $bulkjobid], '*', IGNORE_MISSING);
        if (!$bulk || $bulk->status !== self::STATUS_QUEUED) {
            return;
        }
        $children = self::get_import_jobs_for_bulk_job($bulkjobid);
        if (!$children) {
            return;
        }
        $summary = self::summarize_child_import_states($children);
        if ($summary->active > 0) {
            return;
        }
        $total = (int) $bulk->total_count;
        if ($total < 1) {
            $total = max(1, $summary->finished + $summary->failed);
        }
        self::sync_status_from_children($bulkjobid, $summary->finished, $summary->failed, $total);
    }

    /**
     * Whether the user may view this bulk job (owner or manage capability).
     *
     * @param \stdClass $bulkrecord Parent bulk job DB row.
     * @param int $userid User id to authorise.
     * @return bool True when user is owner or has manage capability.
     */
    public static function user_can_view(\stdClass $bulkrecord, int $userid): bool {
        if ((int) $bulkrecord->userid === (int) $userid) {
            return true;
        }
        return has_capability('block/courseimport:manage', \context_system::instance(), $userid);
    }

    /**
     * Recent bulk jobs for a user (newest first).
     *
     * @param int $userid User id.
     * @param int $limit  Maximum rows to return.
     * @param int $page Zero-based page index for pagination (default 0).
     * @return array<int, \stdClass> Parent bulk rows for the requested page.
     */
    public static function list_for_user_page(int $userid, int $limit, int $page = 0): array {
        global $DB;
        $offset = max(0, $page) * max(1, $limit);
        return $DB->get_records_sql(
            'SELECT * FROM {block_courseimport_bulk_job} WHERE userid = :u ORDER BY timecreated DESC, id DESC',
            ['u' => $userid],
            $offset,
            $limit
        );
    }

    /**
     * Returns whether the user currently has at least one queued bulk job.
     *
     * @param int $userid User id.
     * @return bool True when the user has at least one parent row in queued state.
     */
    public static function user_has_queued(int $userid): bool {
        global $DB;
        return $DB->record_exists('block_courseimport_bulk_job', [
            'userid' => $userid,
            'status' => self::STATUS_QUEUED,
        ]);
    }

    /**
     * Returns the user's most recent queued bulk job.
     *
     * @param int $userid User id.
     * @return \stdClass|null Most recent queued parent row, or null if none exists.
     */
    public static function get_most_recent_queued_for_user(int $userid): ?\stdClass {
        global $DB;
        $records = $DB->get_records('block_courseimport_bulk_job', [
            'userid' => $userid,
            'status' => self::STATUS_QUEUED,
        ], 'timecreated DESC, id DESC', '*', 0, 1);
        if (!$records) {
            return null;
        }
        return reset($records) ?: null;
    }

    /**
     * Counts parent bulk rows for a user.
     *
     * @param int $userid User id.
     * @return int Number of parent rows owned by this user.
     */
    public static function count_for_user(int $userid): int {
        global $DB;
        return (int) $DB->count_records('block_courseimport_bulk_job', ['userid' => $userid]);
    }

    /**
     * Reconcile bulk job status from actual child job counts (fallback for missed record_child_finished calls).
     *
     * @param int $bulkjobid
     * @param int $finishedcnt
     * @param int $failedcnt
     * @param int $totalcount
     * @return void
     */
    public static function sync_status_from_children(int $bulkjobid, int $finishedcnt, int $failedcnt, int $totalcount): void {
        global $DB;
        $record = self::get_record($bulkjobid);
        if (!$record) {
            return;
        }
        $record->completed_count = $finishedcnt;
        $record->failed_count    = $failedcnt;
        $record->timemodified    = time();
        $done = $finishedcnt + $failedcnt;
        if ($done >= max(1, $totalcount)) {
            if ($finishedcnt > 0 && $failedcnt > 0) {
                $record->status = self::STATUS_PARTIAL;
            } else if ($finishedcnt > 0) {
                $record->status = self::STATUS_COMPLETED;
            } else {
                $record->status = self::STATUS_FAILED;
            }
        }
        $DB->update_record('block_courseimport_bulk_job', $record);
    }

    /**
     * Update parent bulk job when a child import finishes (from {@see \block_courseimport\job::set_status}).
     *
     * @param int|null $bulkjobid
     * @param bool $success
     * @return void
     */
    public static function record_child_finished(?int $bulkjobid, bool $success): void {
        if (!$bulkjobid) {
            return;
        }
        global $DB;
        $record = self::get_record($bulkjobid);
        if (!$record) {
            return;
        }
        if ($success) {
            $record->completed_count = (int) $record->completed_count + 1;
        } else {
            $record->failed_count = (int) $record->failed_count + 1;
        }
        $record->timemodified = time();
        $total = (int) $record->total_count;
        $done = (int) $record->completed_count + (int) $record->failed_count;
        if ($total > 0 && $done >= $total) {
            if ((int) $record->failed_count > 0 && (int) $record->completed_count > 0) {
                $record->status = self::STATUS_PARTIAL;
            } else if ((int) $record->failed_count > 0) {
                $record->status = self::STATUS_FAILED;
            } else {
                $record->status = self::STATUS_COMPLETED;
            }
        }
        $DB->update_record('block_courseimport_bulk_job', $record);
    }
}
