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

/**
 * Bulk CSV upload index page (routing + template context).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\local;

use block_courseimport\bulk_job;
use block_courseimport\local\form\csv_upload_form;
use block_courseimport\import_helper;
use core\url;

/**
 * Builds data for {@see bulk_upload} template and related form wiring.
 */
final class bulk_index_page {

    /**
     * Page heading string (kept in PHP because template currently expects a single heading value).
     *
     * @return string
     */
    public static function get_page_heading(): string {
        return get_string('bulkrolloverheading', 'block_courseimport');
    }

    /**
     * Form action URL for same-page POST (Moodle forms pattern).
     *
     * @return url
     */
    public static function get_form_action_url(): url {
        return new url('/blocks/courseimport/bulk/index.php');
    }

    /**
     * CSV upload form instance for the bulk index page.
     *
     * @param url $actionurl
     * @return csv_upload_form
     */
    public static function make_upload_form(url $actionurl): csv_upload_form {
        return new csv_upload_form($actionurl);
    }

    /**
     * Redirect target after a valid upload from the index form.
     *
     * @param int $draftitemid Draft area item id from the filepicker.
     * @return url
     */
    public static function get_post_upload_redirect(int $draftitemid): url {
        return new url('/blocks/courseimport/bulk/submit.php', ['draftid' => $draftitemid]);
    }

    /**
     * Context for block_courseimport/bulk_upload (excluding rendered form HTML).
     *
     * @param int $userid
     * @param string $heading
     * @param string $formhtml Rendered form markup from {@see moodleform::display()}.
     * @return array<string, mixed> keys: heading, statusurl, formhtml, csvheadinghelp, activebulknotice, enableditems,
     *         cansettings (bool), settingsurl (string, empty when cansettings is false).
     */
    public static function build_upload_template_context(int $userid, string $heading, string $formhtml): array {
        $activebulkjob = bulk_job::get_most_recent_queued_for_user($userid);
        $statusurl = (new url('/blocks/courseimport/bulk/results.php'))->out(false);

        $labels = import_helper::get_enabled_profile_sidebar_labels();
        $enableditems = [];
        foreach ($labels as $label) {
            $enableditems[] = ['label' => $label];
        }

        $systemcontext = \context_system::instance();
        $cansettings = has_capability('block/courseimport:manage', $systemcontext)
            || has_capability('moodle/site:config', $systemcontext);
        $settingsurl = $cansettings
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

        return [
            'heading' => $heading,
            'statusurl' => $statusurl,
            'formhtml' => $formhtml,
            'activebulknotice' => $activebulknotice,
            'enableditems' => $enableditems,
            'cansettings' => $cansettings,
            'settingsurl' => $settingsurl,
        ];
    }
}
