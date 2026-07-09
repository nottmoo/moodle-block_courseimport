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
 * Polls bulk parent job progress via web service (no periodic full page reload).
 *
 * While the parent bulk job is still running, the progress card, filter counts, and
 * title are updated from the web service. When the job finishes, the page reloads once
 * so the child-jobs table is rendered from the server.
 *
 * @module     block_courseimport/bulk_status_progress
 * @author     Nisha Sarala <nisha.sarala@gmail.com>
 * @copyright  2026 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import {get_string} from 'core/str';
import Notification from 'core/notification';

const checkdelay = 2000;
const reloadDelay = 1500;

/**
 * Stops polling for this bulk job.
 *
 * @param {{checkid: ?number}} state Polling state for the current bulk job.
 */
const stop = (state) => {
    if (state.checkid !== null) {
        clearInterval(state.checkid);
        state.checkid = null;
    }
};

/**
 * Handles a failed progress poll without surfacing benign navigation/transport errors.
 *
 * @param {*} error Rejected value from the AJAX call.
 * @param {{finishing: boolean}} state Polling state for the current bulk job.
 */
const handleFetchError = (error, state) => {
    if (state.finishing) {
        return;
    }
    if (error === 'abort' || error === 'timeout') {
        return;
    }
    const exception = error?.exception ?? error;
    if (exception?.errorcode) {
        Notification.exception(exception);
    }
};

/**
 * Updates the counts summary line from a localised string.
 *
 * @param {HTMLElement} counts Counts element.
 * @param {number} completed Successful child jobs.
 * @param {number} total Total child jobs.
 * @param {number} failed Failed child jobs.
 * @param {{finishing: boolean}} state Polling state for the current bulk job.
 */
const updateCountsText = (counts, completed, total, failed, state) => {
    get_string('bulkstatusajaxcounts', 'block_courseimport', {
        completed: completed,
        total: total,
        failed: failed,
    }).then((text) => {
        counts.textContent = text;
    }).catch((error) => {
        handleFetchError(error, state);
    });
};

/**
 * Updates the progress bar label from a localised string.
 *
 * @param {HTMLElement} bar Progress bar element.
 * @param {number} doneunits Number of terminal child imports.
 * @param {number} total Total number of child imports.
 * @param {{finishing: boolean}} state Polling state for the current bulk job.
 */
const updateBarLabel = (bar, doneunits, total, state) => {
    get_string('bulkstatusbarlabel', 'block_courseimport', {
        done: doneunits,
        total: total,
    }).then((label) => {
        bar.textContent = label;
    }).catch((error) => {
        handleFetchError(error, state);
    });
};

/**
 * Updates child-job filter count badges from the latest poll response.
 *
 * @param {HTMLElement} root Bulk status root element.
 * @param {*} response Web service response for the current bulk job.
 */
const updateFilterCounts = (root, response) => {
    const mappings = [
        ['all', response.childcountall],
        ['finished', response.childcountfinished],
        ['failed', response.childcountfailed],
        ['incomplete', response.childcountincomplete],
    ];
    mappings.forEach(([filterkey, count]) => {
        const el = root.querySelector('[data-region="bulk-filter-count"][data-filter="' + filterkey + '"]');
        if (el && typeof count !== 'undefined') {
            el.textContent = String(count);
        }
    });
};

/**
 * Requests the latest bulk parent progress from the web service.
 *
 * @param {number} bulkid Parent bulk job id.
 * @param {{checkid: ?number, root: ?HTMLElement, bar: ?HTMLElement, counts: ?HTMLElement, title: ?HTMLElement, inflight: boolean, finishing: boolean}} state
 *      Polling state for the current bulk job.
 */
const fetchProgress = (bulkid, state) => {
    if (state.finishing || state.inflight) {
        return;
    }
    state.inflight = true;
    const requests = [{
        methodname: 'block_courseimport_get_bulk_job_progress',
        args: {bulkid: bulkid},
    }];
    const promises = Ajax.call(requests, true, true, false);
    promises[0].then((response) => {
        state.inflight = false;
        applyResponse(response, state);
    }).catch((error) => {
        state.inflight = false;
        handleFetchError(error, state);
    });
};

/**
 * Applies a bulk progress response to the progress card and filter counts.
 *
 * @param {*} response Web service response for the current bulk job.
 * @param {{checkid: ?number, root: ?HTMLElement, bar: ?HTMLElement, counts: ?HTMLElement, title: ?HTMLElement, finishing: boolean}} state
 *      Polling state for the current bulk job.
 */
const applyResponse = (response, state) => {
    const pct = Math.round(Number(response.progresspct));
    const doneunits = Number(response.completed) + Number(response.failed);
    if (state.bar) {
        state.bar.style.width = pct + '%';
        state.bar.setAttribute('aria-valuenow', String(pct));
        updateBarLabel(state.bar, doneunits, Number(response.total), state);
    }
    if (state.counts) {
        updateCountsText(
            state.counts,
            Number(response.completed),
            Number(response.total),
            Number(response.failed),
            state
        );
    }
    if (state.title && response.progresstitle) {
        state.title.textContent = response.progresstitle;
    }
    if (state.root) {
        updateFilterCounts(state.root, response);
    }

    if (!response.isrunning) {
        state.finishing = true;
        stop(state);
        setTimeout(() => {
            window.location.reload();
        }, reloadDelay);
    }
};

/**
 * Starts polling progress for a bulk parent job.
 *
 * @param {number} bulkid Parent bulk job id.
 */
export const init = (bulkid) => {
    const root = document.getElementById('block-courseimport-bulk-root-' + bulkid);
    if (!root) {
        return;
    }
    const bar = root.querySelector('[data-region="bulk-progress-bar"]');
    const counts = root.querySelector('[data-region="bulk-counts"]');
    const title = root.querySelector('[data-region="bulk-progress-title"]');
    const state = {
        checkid: null,
        root: root,
        bar: bar,
        counts: counts,
        title: title,
        inflight: false,
        finishing: false,
    };
    state.checkid = setInterval(() => {
        fetchProgress(bulkid, state);
    }, checkdelay);
    fetchProgress(bulkid, state);
};
