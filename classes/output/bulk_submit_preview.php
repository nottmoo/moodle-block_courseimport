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

namespace block_courseimport\output;

use block_courseimport\job;
use block_courseimport\local\bulk_submit_service;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;

/**
 * Data for the bulk_submit_preview Mustache template (CSV confirmation on bulk/submit.php).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_submit_preview implements renderable, templatable {
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data JSON-serialisable context for block_courseimport/bulk_submit_preview
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Build template context from one cached page of pairs/errors (not the full CSV result set).
     *
     * @param string $packid Confirmation cache pack id (isolates concurrent tabs).
     * @param array $summarycounts Keys: rows, resolved, unmatched, toimport, skipped.
     * @param array<int, array<string, mixed>> $pairslice Current page of resolved pairs.
     * @param int $pairtotal Total resolved pairs across all pages.
     * @param array<int, array<string, mixed>> $errorslice Current page of errors.
     * @param int $errortotal Total errors across all pages.
     * @param int $previewpage Zero-based page index for resolved pairs table.
     * @param int $errorpage Zero-based page index for errors table.
     * @param int $rowsperpage Max rows per preview table page.
     * @return self
     */
    public static function fetch(
        string $packid,
        array $summarycounts,
        array $pairslice,
        int $pairtotal,
        array $errorslice,
        int $errortotal,
        int $previewpage,
        int $errorpage,
        int $rowsperpage
    ): self {
        global $DB, $OUTPUT;

        $summarydisplay = (object) $summarycounts;
        $summarytext = get_string('bulkpreviewsummary', 'block_courseimport', $summarydisplay);

        $baseparams = ['packid' => $packid];
        $pairrows = [];
        $pairpaginationtop = '';
        $pairpaginationbottom = '';
        $haspairs = $pairtotal > 0;

        if ($haspairs) {
            $pairnavurl = new url('/blocks/courseimport/bulk/submit.php', $baseparams + ['errorpage' => $errorpage]);

            $sourceids = array_filter(array_column($pairslice, 'source_id'));
            $sourcenames = [];
            if ($sourceids) {
                [$insql, $inparams] = $DB->get_in_or_equal(array_values($sourceids), SQL_PARAMS_NAMED);
                $sourcenames = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, shortname, fullname');
            }

            if ($pairtotal > $rowsperpage) {
                $pairpaginationtop = self::build_pagination_html(
                    $OUTPUT,
                    $pairtotal,
                    $previewpage,
                    $rowsperpage,
                    $pairnavurl,
                    'previewpage'
                );
                $pairpaginationbottom = $pairpaginationtop;
            }

            foreach ($pairslice as $pair) {
                $sourceid = (int) ($pair['source_id'] ?? 0);
                $sourcecourse = $sourcenames[$sourceid] ?? null;
                $pairrows[] = [
                    'hassourcecourse' => $sourcecourse !== null,
                    'sourceshortname' => $sourcecourse ? s($sourcecourse->shortname) : '',
                    'sourcefullname' => $sourcecourse ? s($sourcecourse->fullname) : '',
                    'sourceid' => $sourceid,
                    'targetid' => (int) ($pair['target_id'] ?? 0),
                    'csvshortname' => s($pair['csv_shortname'] ?? ''),
                    'csvfullname' => s($pair['csv_fullname'] ?? ''),
                    'actionlabel' => s(self::format_pair_action($pair)),
                ];
            }
        }

        $errorrows = [];
        $errorpaginationtop = '';
        $errorpaginationbottom = '';
        $haserrors = $errortotal > 0;

        if ($haserrors) {
            $errnavurl = new url(
                '/blocks/courseimport/bulk/submit.php',
                $baseparams + ['previewpage' => $previewpage]
            );

            if ($errortotal > $rowsperpage) {
                $errorpaginationtop = self::build_pagination_html(
                    $OUTPUT,
                    $errortotal,
                    $errorpage,
                    $rowsperpage,
                    $errnavurl,
                    'errorpage'
                );
                $errorpaginationbottom = $errorpaginationtop;
            }

            foreach ($errorslice as $err) {
                $errorrows[] = [
                    'rowlabel' => isset($err['row']) ? (string) $err['row'] : '',
                    'errortype' => (string) ($err['errortype'] ?? 'bulkunknownerror'),
                    'errorparams' => !empty($err['params']) ? $err['params'] : null,
                    'errormessage' => bulk_submit_service::format_preview_error($err),
                ];
            }
        }

        $cancelurl = (new url('/blocks/courseimport/bulk/submit.php', [
            'cancel' => 1,
            'packid' => $packid,
            'sesskey' => sesskey(),
        ]))->out(false);

        return new self([
            'summarytext' => $summarytext,
            'haspairs' => $haspairs,
            'pairrows' => $pairrows,
            'pairpaginationtop' => $pairpaginationtop,
            'pairpaginationbottom' => $pairpaginationbottom,
            'haserrors' => $haserrors,
            'errorrows' => $errorrows,
            'errorpaginationtop' => $errorpaginationtop,
            'errorpaginationbottom' => $errorpaginationbottom,
            'cancelurl' => $cancelurl,
            'confirmbaseurl' => (new url('/blocks/courseimport/bulk/submit.php'))->out(false),
            'packid' => $packid,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Exports Mustache context for the bulk submit preview template.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        return $this->data;
    }

    /**
     * Pagination summary + paging bar HTML for a preview table.
     *
     * @param \renderer_base $output Page renderer.
     * @param int $total Total rows.
     * @param int $page Zero-based page index.
     * @param int $perpage Rows per page.
     * @param url $baseurl Paging bar base URL.
     * @param string $pageparam URL param name for this table's page index.
     * @return string
     */
    protected static function build_pagination_html(
        \renderer_base $output,
        int $total,
        int $page,
        int $perpage,
        url $baseurl,
        string $pageparam
    ): string {
        $from = $page * $perpage + 1;
        $to = min($page * $perpage + $perpage, $total);
        $html = \html_writer::div(
            get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $total]),
            'mb-2 text-muted'
        );
        $html .= $output->paging_bar($total, $page, $perpage, $baseurl, $pageparam);
        return $html;
    }

    /**
     * Lang string key when this pair should be skipped on confirm, or null to queue.
     *
     * @param array<string, mixed> $pair
     * @return string|null
     */
    protected static function pair_skip_reason(array $pair): ?string {
        $targetid = (int) ($pair['target_id'] ?? 0);
        $sourceid = (int) ($pair['source_id'] ?? 0);
        if ($targetid < 1 || $sourceid < 1) {
            return null;
        }
        return job::bulk_skip_reason($targetid, $sourceid);
    }

    /**
     * Localised action label for a preview row.
     *
     * @param array<string, mixed> $pair
     * @return string
     */
    protected static function format_pair_action(array $pair): string {
        $skipreason = self::pair_skip_reason($pair);
        if ($skipreason !== null) {
            return get_string($skipreason, 'block_courseimport');
        }
        return get_string('bulkpreviewactionimport', 'block_courseimport');
    }
}
