# Claude Moodle Dev — Combined Prompt Bundle

Source: `SaadRahman01/claude-moodle-dev` (`adapters/generic/PROMPTS.md`)

---

## 1) Scaffold a new plugin

You are a senior Moodle architect.
Create a production-ready plugin skeleton for:
- Type: [mod|block|local|tool|auth|enrol|theme|report|availability|qtype|etc]
- Frankenstyle name: [type_name]
- Moodle support: [4.1–4.5/5.x]
- PHP support: [8.1/8.2/8.3]

Requirements:
- Valid version.php (component, version, requires, maturity, release)
- db/access.php capabilities following least privilege
- lang/en/[component].php with get_string keys
- privacy/provider.php baseline with meaningful metadata
- classes/ structure using autoloaded namespaces
- settings.php/admin integration when applicable
- events.php or hooks where appropriate
- PHPUnit + Behat starter tests
- AMD module scaffold if UI requires JS

Constraints:
- Follow Moodle coding style and security patterns
- Add GPL header blocks
- Use placeholders where environment-specific values are needed

Output:
1) file tree
2) complete file contents
3) post-create checklist (install/upgrade/test)

---

## 2) Write/upgrade XMLDB safely

Act as a Moodle DB schema reviewer and migration author.

Task:
- Given current install.xml and target schema intent, generate:
  1) updated install.xml
  2) db/upgrade.php steps with savepoints
  3) rollback/risk notes

Rules:
- Keep cross-DB compatibility (MySQL/MariaDB/PostgreSQL)
- Prefer additive changes; protect existing data
- Add indexes/keys thoughtfully
- Include guards for existing fields/tables/indexes
- Never drop data without explicit migration strategy
- Bump plugin version correctly in version.php

Also provide:
- dry-run checklist
- SQL verification queries
- PHPUnit/Behat assertions for migrated behavior

---

## 3) Security + capability audit

Perform a strict security audit for this Moodle plugin.

Check:
- require_login/context usage
- capability checks at every action boundary
- sesskey validation for state-changing requests
- SQL injection risks ($DB placeholders, no string concat)
- XSS risks (format_text/s(), mustache escaping)
- CSRF/file upload validation
- webservice external_api parameter/return validation
- data exposure in events/logging/privacy APIs

Output:
- Findings grouped by severity (Critical/High/Medium/Low)
- Exact file:line references
- Minimal patch suggestions for each finding
- “Must-fix before release” summary

---

## 4) Privacy/GDPR implementation

You are a Moodle privacy API expert.

Implement or review privacy/provider.php for this plugin:
- Identify all stored personal data
- Map purposes and retention
- Implement metadata provider with precise fields
- Implement userlist/provider/export/delete APIs as needed
- Cover context-level and user-level data correctly
- Include tests for export/delete paths

Output:
- Completed provider class
- Any required supporting classes/helpers
- Test plan + edge cases
- Compliance notes for admins

---

## 5) PHPUnit test generation (Moodle style)

Generate robust PHPUnit tests for [target class/function].

Requirements:
- Use advanced_testcase or appropriate base class
- resetAfterTest(true) and fixtures/factories
- Cover happy path, edge cases, failure/permission paths
- Assert DB side effects and emitted events
- Keep tests deterministic and isolated
- Include data providers where useful

Output:
- Complete *_test.php
- Notes on required fixtures and factory setup
- Command(s) to run this test subset

---

## 6) Behat scenario generation

Create Behat coverage for [feature/user story].

Include:
- Feature + scenario outlines where beneficial
- Realistic roles/enrolments/data setup
- Accessibility-visible outcomes (not brittle selectors)
- Negative/permission scenarios
- Tagging strategy (@javascript only when needed)

Output:
- .feature file content
- Any required step definitions (only if unavoidable)
- Reliability notes to avoid flaky CI

---

## 7) AMD/JS module review + rewrite

Review and improve this Moodle AMD module for quality and accessibility.

Check:
- define() dependencies and module boundaries
- async flows, error handling, cancellation
- event listener cleanup/memory leaks
- i18n strings via core/str
- keyboard and screen-reader behavior
- performance (debounce/throttle/batching)
- compatibility with Moodle-supported browsers

Output:
- Refactored JS module
- Before/after rationale
- Manual test checklist
- Optional Behat JS scenarios

---

## 8) Web service (external_api) design

Design/implement a Moodle external function for:
- Use case: [describe]

Requirements:
- Define execute_parameters with strict validation
- Implement execute() with capability/context checks
- Define clean execute_returns schema
- Add service declarations and access controls
- Provide example client call payloads
- Include PHPUnit tests for validation + permissions

Output:
- externallib.php additions
- services.php updates
- Tests and usage examples

---

## 9) Performance profiling + optimization

Act as a Moodle performance engineer.

Given this code path, identify bottlenecks and optimize:
- DB query count and index usage
- N+1 patterns
- cache definitions/usages (MUC)
- expensive render loops
- adhoc/task offloading opportunities

Output:
- Measured baseline plan (what to measure + how)
- Prioritized optimization list with expected impact
- Patch proposals
- Regression guard tests

---

## 10) Accessibility review (WCAG-minded)

Audit this plugin UI for accessibility risks.

Evaluate:
- semantic structure and labels
- keyboard-only navigation
- focus management in dynamic UIs
- contrast and status messaging
- form errors and inline help
- screen-reader announcements for async updates

Output:
- Issue list by severity
- Code-level remediations
- Acceptance checklist for QA
- Suggested Behat a11y assertions

---

## 11) Code review for PRs (maintainer mode)

Review this Moodle plugin pull request as a strict maintainer.

Focus:
- correctness and backward compatibility
- security/capabilities/privacy
- upgrade safety (db/versioning)
- coding style and API usage
- test completeness and CI reliability

Output format:
1) Blocking issues
2) Non-blocking improvements
3) Suggested patch hunks
4) Final verdict: Request changes / Approve with follow-ups

---

## 12) Release readiness checklist

Prepare this plugin for release.

Produce:
- version bump/release notes draft
- upgrade path verification checklist
- security/privacy/accessibility sign-off checklist
- test matrix (PHP/Moodle/DB)
- rollback plan
- marketplace submission readiness notes
