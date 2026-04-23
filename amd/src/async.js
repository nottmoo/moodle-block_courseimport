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
 * Polls import job progress (one independent timer per job). Removes the block when the job ends.
 *
 * @module     block_courseimport/async
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import {get_string} from 'core/str';
import Notification from 'core/notification';

const checkdelay = 15000;
const timeout = 2000;

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
 * @param {{jobid: number, bar: ?HTMLElement, container: ?HTMLElement, checkid: ?number}} state
 */
const stop = (state) => {
    if (state.checkid !== null) {
        clearInterval(state.checkid);
        state.checkid = null;
    }
};

/**
 * @param {{jobid: number, bar: ?HTMLElement, container: ?HTMLElement, checkid: ?number}} state
 */
const getProgress = (state) => {
    const params = [{
        methodname: 'block_courseimport_get_job_progress',
        args: {'id': state.jobid},
    }];
    const promises = Ajax.call(params, true, true, false, timeout);
    promises[0].then((response) => updateProgress(response, state)).catch(Notification.exception);
};

/**
 * @param {*} response
 * @param {{jobid: number, bar: ?HTMLElement, container: ?HTMLElement, checkid: ?number}} state
 */
const updateProgress = (response, state) => {
    const bar = state.bar;
    if (!bar) {
        stop(state);
        return;
    }
    const progress = Math.round(response.progress * 100);
    let removeClass = 'doesnotexist';
    let addClass = '';
    let stringKey;

    if (response.failed) {
        removeClass = status.started.class;
        addClass = status.failed.class;
        stringKey = status.failed.string;
        stop(state);
    } else if (response.finished) {
        removeClass = status.started.class;
        addClass = status.finished.class;
        stringKey = status.finished.string;
        stop(state);
    } else if (response.started) {
        addClass = status.started.class;
        stringKey = status.started.string;
    } else {
        return;
    }

    get_string(stringKey, 'block_courseimport').then((label) => {
        bar.innerHTML = label;
    }).catch(Notification.exception);
    bar.classList.remove(removeClass);
    bar.classList.add(addClass);
    bar.setAttribute('aria-valuenow', progress);
    bar.setAttribute('style', 'width:' + progress + '%');

    if (response.failed || response.finished) {
        const wrap = state.container;
        if (wrap && wrap.parentNode) {
            wrap.parentNode.removeChild(wrap);
        }
    }
};

/**
 * @param {string|number} id Job row id (same as block_courseimport.id).
 */
export const init = (id) => {
    const idstr = String(id);
    const jobid = parseInt(idstr, 10);
    const bar = document.getElementById(idstr + '_bar');
    const container = document.getElementById(idstr);
    const state = {
        jobid,
        bar,
        container,
        checkid: null,
    };
    state.checkid = setInterval(() => getProgress(state), checkdelay);
    getProgress(state);
};
