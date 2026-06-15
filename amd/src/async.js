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
 * This module updates the UI during an course import process.
 *
 * @module     block_courseimport/async
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import {get_string} from 'core/str';
import Notification from 'core/notification';

let checkdelay = 15000; // The delay (in milliseconds) between requests.
let timeout = 2000; // The timeout (in milliseconds) for AJAX requests.
let checkid; // The id of the interval timer.
let jobid = 0; // The database id of the job we are monitoring progress for.
let bar; // The progress bar element.

/**
 * Stores the class and string of the progress bar for each state.
 */
const status = {
    started: {
        'class': 'bg-success',
        'string': 'inprogress',
    },
    finished: {
        'class': 'bg-success',
        'string': 'finished',
    },
    failed: {
        'class': 'bg-danger',
        'string': 'failed',
    }
};

/**
 * Starts checking progress of the job.
 */
const start = () => {
    checkid = setInterval(getProgress, checkdelay);
};

/**
 * Stops checking of progress for the job.
 */
const stop = () => {
    clearInterval(checkid);
};

/**
 * Gets progress for the job, then updates the UI.
 */
const getProgress = () => {
    let params = [{
        // Get the backup progress via webservice.
        methodname: 'block_courseimport_get_job_progress',
        args: {'id': jobid},
    }];
    let promises = Ajax.call(params, true, true, false, timeout);
    promises[0].then(updateProgress).catch(Notification.exception);
};

/**
 * Updates the progress of the import.
 *
 * @param response Data returned from the progress web service.
 */
const updateProgress = (response) => {
    let progress = Math.round(response.progress * 100);
    let removeClass = 'doesnotexist'; // Use a value that should not exist.
    let addClass = '';
    let string;

    if (response.failed) {
        // The job has failed to complete.
        removeClass = status.started.class;
        addClass = status.failed.class;
        string = status.failed.string;
        stop();
    } else if (response.finished) {
        // The import has completed successfully.
        removeClass = status.started.class;
        addClass = status.finished.class;
        string = status.finished.string;
        stop();
    } else if (response.started) {
        // The job is processing.
        addClass = status.started.class;
        string = status.started.string;
    } else {
        // The job is waiting to start.
        return;
    }

    // Update the text of the status bar.
    get_string(string, 'block_courseimport').then(updateBarText).catch(Notification.exception);
    // Ensure the correct class is present.
    bar.classList.remove(removeClass);
    bar.classList.add(addClass);
    // Set the progress for the bar.
    bar.setAttribute('aria-valuenow', progress);
    bar.setAttribute('style', 'width:' + progress + '%');
};

/**
 * Updates the lable of the progress bar.
 *
 * @param {String} label
 */
const updateBarText = (label) => {
    bar.innerHTML = label;
};

/**
 * Sets up the polling of the webservice.
 *
 * @param {Number} id
 */
export const init = (id) => {
    jobid = id;
    bar = document.getElementById(id + '_bar');
    start();
};
