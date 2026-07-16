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
     * Build template context for the bulk CSV confirmation preview.
     *
     * @param array<int, array<string, mixed>> $resolvedpairs
     * @param array<int, array<string, mixed>> $resolutionerrors
     * @param array $summarycounts Keys: rows, resolved, unmatched.
     * @param int $previewpage Zero-based page index for resolved pairs table.
     * @param int $errorpage Zero-based page index for errors table.
     * @param int $rowsperpage Max rows per preview table page.
     * @return self
     */
    public static function fetch(
        array $resolvedpairs,
        array $resolutionerrors,
        array $summarycounts,
        int $previewpage,
        int $errorpage,
        int $rowsperpage
    ): self {
        global $DB, $OUTPUT;

        $skipcounts = self::count_skip_and_import($resolvedpairs);
        $summarydisplay = (object) array_merge($summarycounts, $skipcounts);
        $summarytext = get_string('bulkpreviewsummary', 'block_courseimport', $summarydisplay);

        $pairrows = [];
        $pairpaginationtop = '';
        $pairpaginationbottom = '';
        $haspairs = !empty($resolvedpairs);

        if ($haspairs) {
            $pairtotal = count($resolvedpairs);
            $pairslice = array_slice($resolvedpairs, $previewpage * $rowsperpage, $rowsperpage);
            $pairnavurl = new url('/blocks/courseimport/bulk/submit.php', ['errorpage' => $errorpage]);

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
        $haserrors = !empty($resolutionerrors);

        if ($haserrors) {
            $errtotal = count($resolutionerrors);
            $errslice = array_slice($resolutionerrors, $errorpage * $rowsperpage, $rowsperpage);
            $errnavurl = new url('/blocks/courseimport/bulk/submit.php', ['previewpage' => $previewpage]);

            if ($errtotal > $rowsperpage) {
                $errorpaginationtop = self::build_pagination_html(
                    $OUTPUT,
                    $errtotal,
                    $errorpage,
                    $rowsperpage,
                    $errnavurl,
                    'errorpage'
                );
                $errorpaginationbottom = $errorpaginationtop;
            }

            foreach ($errslice as $err) {
                $errorrows[] = [
                    'rowlabel' => isset($err['row']) ? (string) $err['row'] : '',
                    'errortype' => (string) ($err['errortype'] ?? 'bulkunknownerror'),
                    'errorparams' => !empty($err['params']) ? $err['params'] : null,
                    'errormessage' => bulk_submit_service::format_preview_error($err),
                ];
            }
        }

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
            'cancelurl' => (new url('/blocks/courseimport/bulk/index.php'))->out(false),
            'confirmbaseurl' => (new url('/blocks/courseimport/bulk/submit.php'))->out(false),
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
     * Counts pairs that will be queued vs skipped on confirm.
     *
     * @param array<int, array<string, mixed>> $resolvedpairs
     * @return array{toimport: int, skipped: int}
     */
    protected static function count_skip_and_import(array $resolvedpairs): array {
        $toimport = 0;
        $skipped = 0;
        foreach ($resolvedpairs as $pair) {
            if (self::pair_skip_reason($pair) !== null) {
                $skipped++;
            } else {
                $toimport++;
            }
        }
        return ['toimport' => $toimport, 'skipped' => $skipped];
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
