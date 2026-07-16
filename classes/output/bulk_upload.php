<?php
// This file is part of the courseimport block in Moodle
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

use block_courseimport\bulk_job;
use block_courseimport\import_helper;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;

/**
 * Data for the bulk_upload Mustache template (bulk CSV upload on bulk/index.php).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_upload implements renderable, templatable {
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data JSON-serialisable context for block_courseimport/bulk_upload
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Build template context for the bulk CSV upload page.
     *
     * @param int $userid Current user id.
     * @param string $heading Page heading (plain text).
     * @param string $formhtml Rendered form markup from {@see \moodleform::display()}.
     * @return self
     */
    public static function fetch(int $userid, string $heading, string $formhtml): self {
        $activebulkjob = bulk_job::get_most_recent_queued_for_user($userid);
        $statusurl = (new url('/blocks/courseimport/bulk/results.php'))->out(false);

        $labels = import_helper::get_enabled_profile_sidebar_labels();
        $enableditems = [];
        foreach ($labels as $label) {
            $enableditems[] = ['label' => $label];
        }

        $systemcontext = \context_system::instance();
        $showsettings = has_capability('block/courseimport:manage', $systemcontext)
            || has_capability('moodle/site:config', $systemcontext);
        $settingsurl = $showsettings
            ? (new url('/admin/settings.php', ['section' => 'blocksettingcourseimport']))->out(false)
            : '';

        $activebulknotice = null;
        if ($activebulkjob) {
            $jobstatusurl = (new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $activebulkjob->id]))->out(false);
            $activebulknotice = [
                'url' => $jobstatusurl,
                'jobid' => (int) $activebulkjob->id,
            ];
        }

        return new self([
            'heading' => $heading,
            'statusurl' => $statusurl,
            'formhtml' => $formhtml,
            'activebulknotice' => $activebulknotice,
            'enableditems' => $enableditems,
            'showsettings' => $showsettings,
            'settingsurl' => $settingsurl,
        ]);
    }

    /**
     * Exports Mustache context for the bulk upload template.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        return $this->data;
    }
}
