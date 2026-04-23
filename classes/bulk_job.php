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

defined('MOODLE_INTERNAL') || die();

/**
 * Parent bulk rollover job metadata.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class bulk_job {
    /** @var string */
    public const STATUS_QUEUED = 'queued';
    /** @var string */
    public const STATUS_COMPLETED = 'completed';
    /** @var string */
    public const STATUS_FAILED = 'failed';
    /** @var string */
    public const STATUS_PARTIAL = 'partial';

    /** @var int|null */
    protected $id;

    /** @var int */
    protected $userid;

    /** @var string|null */
    protected $sourceyear;

    /** @var string|null */
    protected $targetyear;

    /** @var string */
    protected $status = self::STATUS_QUEUED;

    /** @var int */
    protected $totalcount = 0;

    /** @var int */
    protected $completedcount = 0;

    /** @var int */
    protected $failedcount = 0;

    /** @var \core\clock */
    protected \core\clock $clock;

    /**
     * @param int $userid
     * @param string|null $sourceyear
     * @param string|null $targetyear
     */
    public function __construct(int $userid, ?string $sourceyear = null, ?string $targetyear = null) {
        $this->userid = $userid;
        $this->sourceyear = $sourceyear;
        $this->targetyear = $targetyear;
        $this->clock = \core\di::get(\core\clock::class);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) {
        if ($name === 'id') {
            return $this->id;
        }
        throw new \coding_exception('Invalid bulk_job property');
    }

    /**
     * @param string $status
     * @return void
     */
    public function set_status(string $status): void {
        $this->status = $status;
        if ($this->id) {
            $this->persist_counts_and_status();
        }
    }

    /**
     * @param int $total
     * @param int $completed
     * @param int $failed
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
     * @return void
     * @throws \dml_exception
     */
    public function save(): void {
        global $DB;
        $now = $this->clock->time();
        if (!$this->id) {
            $record = (object) [
                'userid' => $this->userid,
                'source_year' => $this->sourceyear,
                'target_year' => $this->targetyear,
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
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_record(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('block_courseimport_bulk_job', ['id' => $id], '*', IGNORE_MISSING);
        return $rec ?: null;
    }

    /**
     * Whether the user may view this bulk job (owner or manage capability).
     *
     * @param \stdClass $bulkrecord
     * @param int $userid
     * @return bool
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
     * @param int $userid
     * @param int $limit  Maximum rows to return.
     * @param int $offset Row offset for pagination (default 0).
     * @return array<int, \stdClass>
     */
    public static function list_for_user_page(int $userid, int $limit, int $offset = 0): array {
        global $DB;
        return $DB->get_records_sql(
            'SELECT * FROM {block_courseimport_bulk_job} WHERE userid = :u ORDER BY timecreated DESC, id DESC',
            ['u' => $userid],
            $offset,
            $limit
        );
    }

    /**
     * Convenience wrapper — returns the most recent bulk jobs without pagination.
     *
     * @param int $userid
     * @param int $limit
     * @return array<int, \stdClass>
     */
    public static function list_for_user(int $userid, int $limit = 50): array {
        return self::list_for_user_page($userid, $limit);
    }

    /**
     * @return int
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
        $record = $DB->get_record('block_courseimport_bulk_job', ['id' => $bulkjobid], '*', IGNORE_MISSING);
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
        $record = $DB->get_record('block_courseimport_bulk_job', ['id' => $bulkjobid], '*', IGNORE_MISSING);
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
