# Review & Suggested Fixes: amd/src/bulk_status_progress.js

This file contains a short, focused table listing the original code snippets from `amd/src/bulk_status_progress.js`, the suggested fix for each snippet, and a brief explanation of why the change is recommended. Apply the fixes to improve robustness (error handling and lifecycle cleanup).

<table>
  <thead>
    <tr>
      <th>Original code</th>
      <th>Suggested fix</th>
      <th>Explanation</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <pre><code>const handleFetchError = (error, state) =&gt; {
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
};</code></pre>
      </td>
      <td>
        <pre><code>const handleFetchError = (error, state) =&gt; {
    // If we're finishing, do not surface errors.
    if (state.finishing) {
        return;
    }

    // Ignore benign abort/timeout from navigation/transport.
    if (error === 'abort' || error === 'timeout') {
        return;
    }

    // Moodle AJAX rejections sometimes come as { exception: {...} } and sometimes as plain Error/string.
    const exception = error?.exception ?? error;

    // If this looks like a Moodle exception (has errorcode or an exception string), show full exception UI.
    if (exception && (exception.errorcode || exception.exception)) {
        Notification.exception(exception);
        return;
    }

    // Fallback: show any message we can extract (Error.message or plain string).
    const message = exception?.message ?? (typeof exception === 'string' ? exception : null);
    if (message) {
        Notification.error(String(message));
        return;
    }

    // If we reach here, we have an unexpected shape — log for diagnostics.
    // eslint-disable-next-line no-console
    console.error('Unhandled AJAX error shape in block_courseimport bulk progress:', error);
};</code></pre>
      </td>
      <td>
        <p>Original code only called <code>Notification.exception</code> when the error object had an <code>errorcode</code>, which hides other useful error shapes (plain <code>Error</code> objects or string messages). The suggested fix preserves the original benign filters (<code>abort</code>/<code>timeout</code> and <code>state.finishing</code>), continues to display Moodle-style exceptions with <code>Notification.exception</code>, and adds a sensible fallback: show the error via <code>Notification.error</code> if a message is available, otherwise log the raw value to the console for diagnostics. This improves observability for administrators and developers without changing existing behavior for Moodle WS exceptions.</p>
      </td>
    </tr>

    <tr>
      <td>
        <pre><code>export const init = (bulkid) =&gt; {
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
    state.checkid = setInterval(() =&gt; {
        fetchProgress(bulkid, state);
    }, checkdelay);
    fetchProgress(bulkid, state);
};</code></pre>
      </td>
      <td>
        <pre><code>export const init = (bulkid) =&gt; {
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

    // Ensure polling stops when the page is being unloaded to avoid orphaned intervals.
    const unloadHandler = () =&gt; stop(state);
    window.addEventListener('beforeunload', unloadHandler);

    // Also stop polling if the root element is removed from the DOM (optional but helpful).
    let observer = null;
    if (typeof MutationObserver !== 'undefined') {
        observer = new MutationObserver((mutations) =&gt; {
            if (!document.body.contains(state.root)) {
                stop(state);
                if (observer) {
                    observer.disconnect();
                }
                window.removeEventListener('beforeunload', unloadHandler);
            }
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    state.checkid = setInterval(() =&gt; {
        fetchProgress(bulkid, state);
    }, checkdelay);
    fetchProgress(bulkid, state);
};</code></pre>
      </td>
      <td>
        <p>Original <code>init()</code> started a periodic poll with <code>setInterval</code> but did not remove the interval when the user navigated away or when the block element was removed. This can leave orphaned timers running and cause background activity, which is wasteful and may cause errors if the page context is gone. The suggested fix adds a <code>beforeunload</code> handler to stop polling when the page unloads and optionally uses a <code>MutationObserver</code> to detect when the root element is removed from the DOM and clean up the interval and event listener. Both changes are defensive: they avoid orphaned timers and reduce noise for administrators.</p>
      </td>
    </tr>
  </tbody>
</table>

---

Notes

- These fixes are intentionally minimal and low-risk. They preserve existing behaviour for Moodle-style exceptions while improving robustness and observability for non-standard error shapes.
- If you want, I can open a PR that applies these changes, update other AMD modules (e.g. `amd/src/async.js`) to the same pattern, and add a small JS test.

Generated by GitHub Copilot assistant for review purposes.
