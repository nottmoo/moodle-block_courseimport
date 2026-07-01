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
 * Parent bulk rollover job model and database interface.
 *
 * All reads and writes to the {@see block_courseimport_bulk_job} table should go through
 * this class, matching the pattern used by {@see job} for child import rows.
 *
 * @property-read int|null $id The database id of the parent bulk job.
 * @property-read int $userid The id of the user who submitted the bulk job.
 * @property-read string $status Parent bulk status ({@see self::STATUS_QUEUED} etc.).
 * @property-read int $totalcount Total child imports expected for this bulk batch.
 * @property-read int $completedcount Child imports finished successfully.
 * @property-read int $failedcount Child imports that reached a failed terminal state.
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_job {
    /** @var string Parent bulk batch created; no child import has started processing yet. */
    public const STATUS_QUEUED = 'queued';

    /** @var string Parent bulk batch with at least one child import queued, running, or partially complete. */
    public const STATUS_PROCESSING = 'processing';

    /** @var string Terminal outcome: all child imports finished successfully. */
    public const STATUS_COMPLETED = 'completed';

    /** @var string Terminal outcome: all child imports failed, or no imports were queued due to errors. */
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
            case 'totalcount':
                return $this->totalcount;
            case 'completedcount':
                return $this->completedcount;
            case 'failedcount':
                return $this->failedcount;
        }
        throw new \coding_exception('Invalid bulk_job property');
    }

    /**
     * Sets the parent bulk status and persists immediately when already saved.
     *
     * @param string $status One of {@see self::STATUS_QUEUED}, {@see self::STATUS_PROCESSING},
     *                       {@see self::STATUS_COMPLETED}, {@see self::STATUS_FAILED}, {@see self::STATUS_PARTIAL}.
     * @return void
     * @throws \coding_exception When an unknown status value is passed.
     */
    public function set_status(string $status): void {
        if (!in_array($status, self::valid_statuses(), true)) {
            throw new \coding_exception('Invalid bulk_job status: ' . $status);
        }
        $this->status = $status;
        if ($this->id) {
            $this->update();
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
            $this->update();
        }
    }

    /**
     * Inserts a new parent row, or delegates to {@see update()} when the row already exists.
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
            $this->update();
        }
    }

    /**
     * Updates status and counters for an existing parent row.
     *
     * @return void
     * @throws \dml_exception
     */
    protected function update(): void {
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
     * Creates an instance from a database row.
     *
     * @param \stdClass $record Row from {@see block_courseimport_bulk_job}.
     * @return self
     */
    public static function create_from_record(\stdClass $record): self {
        $bulk = new self((int) $record->userid);
        $bulk->id = (int) $record->id;
        $bulk->status = (string) $record->status;
        $bulk->totalcount = (int) $record->total_count;
        $bulk->completedcount = (int) $record->completed_count;
        $bulk->failedcount = (int) $record->failed_count;
        return $bulk;
    }

    /**
     * Load a bulk job by id.
     *
     * @param int $id Parent bulk job id.
     * @return self|null Matching job, or null when no row exists.
     */
    public static function get_record(int $id): ?self {
        global $DB;
        $rec = $DB->get_record('block_courseimport_bulk_job', ['id' => $id], '*', IGNORE_MISSING);
        return $rec ? self::create_from_record($rec) : null;
    }

    /**
     * Whether a parent bulk status means the batch is still in progress.
     *
     * @param string $status Parent bulk status value.
     * @return bool
     */
    public static function is_running_status(string $status): bool {
        return in_array($status, self::non_terminal_statuses(), true);
    }

    /**
     * Parent bulk statuses that mean the batch has not reached a terminal outcome.
     *
     * @return string[]
     */
    public static function non_terminal_statuses(): array {
        return [self::STATUS_QUEUED, self::STATUS_PROCESSING];
    }

    /**
     * All valid parent bulk status values.
     *
     * @return string[]
     */
    public static function valid_statuses(): array {
        return [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_PARTIAL,
        ];
    }

    /**
     * Terminal child imports counted on the parent bulk row (completed + failed).
     *
     * @param self $bulk Parent bulk job.
     * @return int
     */
    public static function count_done_units(self $bulk): int {
        return $bulk->completedcount + $bulk->failedcount;
    }

    /**
     * Loads a bulk job after optional reconcile, throwing when missing or not viewable.
     *
     * @param int $bulkid Parent bulk job id.
     * @param int $userid User id to authorise.
     * @param bool $reconcile When true, run {@see self::reconcile_queued_parent_if_stale()} first.
     * @return self
     * @throws \moodle_exception
     */
    public static function load_viewable_bulk(int $bulkid, int $userid, bool $reconcile = true): self {
        $bulk = self::get_record($bulkid);
        if (!$bulk || !self::user_can_view($bulk, $userid)) {
            throw new \moodle_exception('bulkstatusinvalid', 'block_courseimport');
        }
        if ($reconcile) {
            self::reconcile_queued_parent_if_stale($bulkid);
            $bulk = self::get_record($bulkid);
            if (!$bulk || !self::user_can_view($bulk, $userid)) {
                throw new \moodle_exception('bulkstatusinvalid', 'block_courseimport');
            }
        }
        return $bulk;
    }

    /**
     * Whether the parent bulk batch should still show as running in the UI / AJAX polling.
     *
     * A row can remain {@see self::STATUS_PROCESSING} while counters already reflect every child
     * import; treat that as finished so the progress card does not stick at 100%.
     *
     * @param self $bulk Parent bulk job.
     * @return bool
     */
    public static function is_parent_still_running(self $bulk): bool {
        if (!self::is_running_status($bulk->status)) {
            return false;
        }
        $total = $bulk->totalcount;
        $done = self::count_done_units($bulk);
        return !($total > 0 && $done >= $total);
    }

    /**
     * Sets a terminal parent status when success/failed counters already cover the batch total.
     *
     * @param int $bulkjobid Parent bulk job id.
     * @return void
     */
    public static function finalize_parent_when_done(int $bulkjobid): void {
        $bulk = self::get_record($bulkjobid);
        if (!$bulk || !self::is_running_status($bulk->status)) {
            return;
        }
        $total = $bulk->totalcount;
        $done = self::count_done_units($bulk);
        if ($total < 1 || $done < $total) {
            return;
        }
        $bulk->apply_terminal_status();
    }

    /**
     * Sets a final status from current success/failure counters.
     *
     * Used when every queued child import has finished ({@see finalize_parent_when_done()},
     * {@see record_child_finished()}, {@see sync_status_from_children()}).
     *
     * @return void
     */
    protected function apply_terminal_status(): void {
        if ($this->failedcount > 0 && $this->completedcount > 0) {
            $this->set_status(self::STATUS_PARTIAL);
        } else if ($this->failedcount > 0) {
            $this->set_status(self::STATUS_FAILED);
        } else {
            $this->set_status(self::STATUS_COMPLETED);
        }
    }

    /**
     * Sets parent status immediately after bulk submit, from queue outcome only.
     *
     * Pre-queue failures do not affect status here. {@see STATUS_PARTIAL} is only set via
     * {@see apply_terminal_status()} once all queued child imports have finished.
     *
     * @param int $queuedcount Child import jobs successfully queued.
     * @param int $skippedcount Rows skipped at submit (e.g. already imported).
     * @return void
     */
    public function apply_status_after_submit(int $queuedcount, int $skippedcount): void {
        if ($queuedcount > 0) {
            $this->set_status(self::STATUS_QUEUED);
        } else if ($skippedcount > 0) {
            $this->set_status(self::STATUS_COMPLETED);
        } else {
            $this->set_status(self::STATUS_FAILED);
        }
    }

    /**
     * Localised label for a parent bulk status code.
     *
     * @param string $status Parent bulk status value.
     * @return string
     */
    public static function format_status_label(string $status): string {
        switch ($status) {
            case self::STATUS_QUEUED:
                return get_string('bulkstatusstatequeued', 'block_courseimport');
            case self::STATUS_PROCESSING:
                return get_string('bulkstatusstateprocessing', 'block_courseimport');
            case self::STATUS_COMPLETED:
                return get_string('bulkstatusstatecompleted', 'block_courseimport');
            case self::STATUS_FAILED:
                return get_string('bulkstatusstatefailed', 'block_courseimport');
            case self::STATUS_PARTIAL:
                return get_string('bulkstatusstatepartial', 'block_courseimport');
            default:
                return $status;
        }
    }

    /**
     * Localised progress card title while a parent bulk batch is still running.
     *
     * @param string $status Parent bulk status value.
     * @return string
     */
    public static function get_running_progress_title(string $status): string {
        if ($status === self::STATUS_QUEUED) {
            return get_string('bulkstatusstatequeued', 'block_courseimport');
        }
        return get_string('bulkstatusprogresstitleprocessing', 'block_courseimport');
    }

    /**
     * Marks a parent bulk job as processing once the first child import starts.
     *
     * @param int|null $bulkjobid Parent bulk job id.
     * @return void
     */
    public static function mark_parent_processing_started(?int $bulkjobid): void {
        if (!$bulkjobid) {
            return;
        }
        $bulk = self::get_record($bulkjobid);
        if (!$bulk || $bulk->status !== self::STATUS_QUEUED) {
            return;
        }
        $bulk->set_status(self::STATUS_PROCESSING);
    }

    /**
     * Keeps parent queued/processing aligned with whether any child is actually running.
     *
     * @param int $bulkjobid Parent bulk job id.
     * @param \stdClass $summary Output from {@see job::summarize_import_states_for_bulk()}.
     * @return void
     */
    protected static function realign_parent_running_status(int $bulkjobid, \stdClass $summary): void {
        $bulk = self::get_record($bulkjobid);
        if (!$bulk || !self::is_running_status($bulk->status)) {
            return;
        }
        if ($summary->processing > 0) {
            self::mark_parent_processing_started($bulkjobid);
            return;
        }
        if ($summary->active > 0 && $bulk->status === self::STATUS_PROCESSING) {
            $bulk->set_status(self::STATUS_QUEUED);
        }
    }

    /**
     * The normal update path is incremental ({@see record_child_finished()} when a child finishes).
     * This method is called after each cron child job and when loading the results page, in case
     * that incremental update was missed or the parent queued/processing flag drifted.
     *
     * When the parent is still queued or processing:
     * - if any child is still waiting or processing, fix queued vs processing on the parent;
     * - if every child has reached a final state (finished or failed), set parent completed/failed counts
     *   from child status counts and apply the appropriate terminal parent status.
     *
     * @param int $bulkjobid Parent bulk job id.
     * @return void
     */
    public static function reconcile_queued_parent_if_stale(int $bulkjobid): void {
        self::finalize_parent_when_done($bulkjobid);
        $bulk = self::get_record($bulkjobid);
        if (!$bulk || !self::is_running_status($bulk->status)) {
            return;
        }
        if (job::count_import_jobs($bulkjobid) < 1) {
            return;
        }
        $summary = job::summarize_import_states_for_bulk($bulkjobid);
        self::realign_parent_running_status($bulkjobid, $summary);
        if ($summary->active > 0) {
            return;
        }
        $bulk = self::get_record($bulkjobid);
        if (!$bulk) {
            return;
        }
        $total = $bulk->totalcount;
        if ($total < 1) {
            $total = max(1, $summary->finished + $summary->failed);
        }
        self::sync_status_from_children($bulkjobid, $summary->finished, $summary->failed, $total);
    }

    /**
     * Whether the user may view this bulk job (owner or manage capability).
     *
     * @param self $bulkrecord Parent bulk job.
     * @param int $userid User id to authorise.
     * @return bool True when user is owner or has manage capability.
     */
    public static function user_can_view(self $bulkrecord, int $userid): bool {
        if ($bulkrecord->userid === $userid) {
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
     * @return array<int, self> Parent bulk jobs for the requested page.
     */
    public static function list_for_user_page(int $userid, int $limit, int $page = 0): array {
        global $DB;
        $offset = max(0, $page) * max(1, $limit);
        $records = $DB->get_records(
            'block_courseimport_bulk_job',
            ['userid' => $userid],
            'timecreated DESC, id DESC',
            '*',
            $offset,
            $limit
        );
        return array_map(static fn(\stdClass $record): self => self::create_from_record($record), $records);
    }

    /**
     * Returns whether the user currently has at least one non-terminal bulk job.
     *
     * @param int $userid User id.
     * @return bool True when the user has a queued or processing parent row.
     */
    public static function user_has_queued(int $userid): bool {
        global $DB;
        list($insql, $params) = $DB->get_in_or_equal(
            self::non_terminal_statuses(),
            SQL_PARAMS_NAMED,
            'activestatus'
        );
        $params['userid'] = $userid;
        return $DB->record_exists_select(
            'block_courseimport_bulk_job',
            "userid = :userid AND status $insql",
            $params
        );
    }

    /**
     * Returns the user's most recent non-terminal bulk job.
     *
     * @param int $userid User id.
     * @return self|null Most recent queued or processing parent job, or null if none exists.
     */
    public static function get_most_recent_queued_for_user(int $userid): ?self {
        global $DB;
        list($insql, $params) = $DB->get_in_or_equal(
            self::non_terminal_statuses(),
            SQL_PARAMS_NAMED,
            'activestatus'
        );
        $params['userid'] = $userid;
        $records = $DB->get_records_select(
            'block_courseimport_bulk_job',
            "userid = :userid AND status $insql",
            $params,
            'timecreated DESC, id DESC',
            '*',
            0,
            1
        );
        if (!$records) {
            return null;
        }
        return self::create_from_record(reset($records));
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
     * @param int $finishedcount
     * @param int $failedcount
     * @param int $totalcount
     * @return void
     */
    public static function sync_status_from_children(
        int $bulkjobid,
        int $finishedcount,
        int $failedcount,
        int $totalcount
    ): void {
        $bulk = self::get_record($bulkjobid);
        if (!$bulk) {
            return;
        }
        $bulk->set_counts($bulk->totalcount, $finishedcount, $failedcount);
        $done = $finishedcount + $failedcount;
        if ($done >= max(1, $totalcount)) {
            $bulk->apply_terminal_status();
        }
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
        self::mark_parent_processing_started($bulkjobid);
        $bulk = self::get_record($bulkjobid);
        if (!$bulk) {
            return;
        }
        if ($success) {
            $bulk->set_counts($bulk->totalcount, $bulk->completedcount + 1, $bulk->failedcount);
        } else {
            $bulk->set_counts($bulk->totalcount, $bulk->completedcount, $bulk->failedcount + 1);
        }
        if ($bulk->totalcount > 0 && self::count_done_units($bulk) >= $bulk->totalcount) {
            $bulk->apply_terminal_status();
        }
    }
}
