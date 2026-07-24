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

namespace block_courseimport\local\form;

use block_courseimport\bulk_config;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * CSV upload for bulk rollover (filepicker → user draft filestore).
 *
 * Used only on bulk/index.php. After submit, the draft item id is passed to bulk/submit.php
 * so the CSV is streamed from filestore rather than loaded as a whole string.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_upload_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('bulkcsvfile', 'block_courseimport'), null, [
            'maxbytes' => bulk_config::max_csv_bytes(),
            'accepted_types' => ['.csv'],
        ]);
        $mform->addRule('csvfile', get_string('bulkcsvrequired', 'block_courseimport'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('bulkrolloversubmit', 'block_courseimport'));
    }
}
