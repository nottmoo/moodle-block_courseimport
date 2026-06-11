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
 * Defines class for course import block
 *
 * @package block_courseimport
 * @author      Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @copyright   University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\url;
use core\output\html_writer;

/**
 *  Course Import block class
 *
 *  Extends standard block methods, and defines methods for display,
 *  validation and processing of the form.
 *
 */
class block_courseimport extends block_base {
    /**
     * Standard block init method, defines the title
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_courseimport');
    }

    /**
     * Restricts block to course pages
     *
     * @see blocks/block_base#applicable_formats()
     * @return array
     */
    public function applicable_formats() {
        return [
            'all' => true,
            'mod' => false,
            'my' => false,
            'admin' => false,
            'tag' => false,
        ];
    }

    /**
     * Prevent multiple instances of the block on a page
     * @return boolean
     */
    public function allow_multiple() {
        return false;
    }

    /**
     * Displays the block
     *
     * Checks that the user has permission to use the block
     */
    public function get_content() {
        global $COURSE;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        // Don't display content on the Site Home page.
        if ($this->page->category) {
            $coursecontext = context_course::instance($COURSE->id);
            $links = [];

            if (has_capability('block/courseimport:view', $coursecontext)
                && has_capability('moodle/course:update', $coursecontext)
            ) {
                $importpageurl = new url('/blocks/courseimport/import.php', ['id' => $COURSE->id]);
                $links[] = html_writer::link($importpageurl, get_string('importlink', 'block_courseimport'));
            }

            $systemcontext = context_system::instance();
            if (has_capability('block/courseimport:bulkrollover', $systemcontext)) {
                $bulkurl = new url('/blocks/courseimport/bulk/index.php');
                $links[] = html_writer::link($bulkurl, get_string('bulkrollover', 'block_courseimport'));
            }

            if (!empty($links)) {
                $this->content->text = implode(html_writer::empty_tag('br'), $links);
            }
        }

        return $this->content;
    }

    /**
     * Site-level settings (import profile, bulk limits) are defined in settings.php.
     */
    public function has_config() {
        return true;
    }
}
