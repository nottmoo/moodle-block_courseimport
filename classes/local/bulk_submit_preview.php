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

/**
 * HTML preview for bulk CSV confirmation (bulk/submit.php).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\local;

use core\url;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds preview markup for the confirmation step.
 */
final class bulk_submit_preview {

    /**
     * Renders the preview/confirm screen for a session confirmation pack.
     *
     * @param \renderer_base $output Page renderer.
     * @param \moodle_database $database Database (used to resolve source course names).
     * @param array<int, array<string, mixed>> $resolvedpairs
     * @param array<int, array<string, mixed>> $resolutionerrors
     * @param array $summarycounts Keys: rows, resolved, unmatched.
     * @param int $previewpage Zero-based page index for resolved pairs table.
     * @param int $errorpage Zero-based page index for errors table.
     * @param int $rowsperpage Max rows per preview table page.
     * @return string HTML (excluding page header/footer).
     */
    public static function render_confirmation_preview(
        \renderer_base $output,
        \moodle_database $database,
        array $resolvedpairs,
        array $resolutionerrors,
        array $summarycounts,
        int $previewpage,
        int $errorpage,
        int $rowsperpage
    ): string {
        $html = '';
        $html .= $output->heading(get_string('bulkpreviewheading', 'block_courseimport'), 2);
        $html .= \html_writer::tag('p', get_string('bulkpreviewsummary', 'block_courseimport', (object) $summarycounts));

        if ($resolvedpairs) {
            $html .= $output->heading(get_string('bulkpreviewresolved', 'block_courseimport'), 4);
            $pairtotal = count($resolvedpairs);
            $pairslice = array_slice($resolvedpairs, $previewpage * $rowsperpage, $rowsperpage);
            $pairnavurl = new url('/blocks/courseimport/bulk/submit.php', ['errorpage' => $errorpage]);

            $sourceids = array_filter(array_column($pairslice, 'source_id'));
            $sourcenames = [];
            if ($sourceids) {
                [$insql, $inparams] = $database->get_in_or_equal(array_values($sourceids), SQL_PARAMS_NAMED);
                $sourcenames = $database->get_records_select('course', "id $insql", $inparams, '', 'id, shortname, fullname');
            }

            if ($pairtotal > $rowsperpage) {
                $from = $previewpage * $rowsperpage + 1;
                $to = min($previewpage * $rowsperpage + $rowsperpage, $pairtotal);
                $html .= \html_writer::div(
                    get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $pairtotal]),
                    'mb-2 text-muted'
                );
                $html .= $output->paging_bar($pairtotal, $previewpage, $rowsperpage, $pairnavurl, 'previewpage');
            }
            $table = new \html_table();
            $table->head = [
                get_string('bulkstatussource', 'block_courseimport'),
                get_string('bulkpreviewnewtargetheading', 'block_courseimport'),
                get_string('shortname'),
                get_string('fullname'),
            ];
            foreach ($pairslice as $pair) {
                $sourceid = (int) ($pair['source_id'] ?? 0);
                $sourcecourse = $sourcenames[$sourceid] ?? null;
                if ($sourcecourse) {
                    $sourcecol = \html_writer::tag('span', s($sourcecourse->shortname), ['class' => 'font-weight-bold'])
                        . \html_writer::tag('br', '')
                        . \html_writer::tag('small', s($sourcecourse->fullname), ['class' => 'text-muted']);
                } else {
                    $sourcecol = \html_writer::tag('span', '#' . $sourceid, ['class' => 'text-muted']);
                }

                if (!empty($pair['pending_create'])) {
                    $targetcol = \html_writer::tag(
                        'span',
                        get_string('bulkpreviewtargetnew', 'block_courseimport'),
                        ['class' => 'badge badge-info']
                    );
                } else {
                    $targetid = (int) ($pair['target_id'] ?? 0);
                    $targetcol = \html_writer::tag('span', '#' . $targetid, ['class' => 'text-muted']);
                }

                $table->data[] = [
                    $sourcecol,
                    $targetcol,
                    s($pair['csv_shortname'] ?? ''),
                    s($pair['csv_fullname'] ?? ''),
                ];
            }
            $html .= \html_writer::table($table);
            if ($pairtotal > $rowsperpage) {
                $html .= $output->paging_bar($pairtotal, $previewpage, $rowsperpage, $pairnavurl, 'previewpage');
            }
        }

        if ($resolutionerrors) {
            $html .= $output->heading(get_string('bulkpreviewerrors', 'block_courseimport'), 4);
            $errtotal = count($resolutionerrors);
            $errslice = array_slice($resolutionerrors, $errorpage * $rowsperpage, $rowsperpage);
            $errnavurl = new url('/blocks/courseimport/bulk/submit.php', ['previewpage' => $previewpage]);
            if ($errtotal > $rowsperpage) {
                $from = $errorpage * $rowsperpage + 1;
                $to = min($errorpage * $rowsperpage + $rowsperpage, $errtotal);
                $html .= \html_writer::div(
                    get_string('bulkpagination', 'block_courseimport', (object) ['from' => $from, 'to' => $to, 'total' => $errtotal]),
                    'mb-2 text-muted'
                );
                $html .= $output->paging_bar($errtotal, $errorpage, $rowsperpage, $errnavurl, 'errorpage');
            }
            $etable = new \html_table();
            $etable->head = [get_string('bulkpreviewcolrow', 'block_courseimport'), get_string('error')];
            foreach ($errslice as $err) {
                $rowlabel = isset($err['row']) ? (string) $err['row'] : '';
                $etable->data[] = [$rowlabel, s(bulk_submit_service::format_preview_error($err))];
            }
            $html .= \html_writer::table($etable);
            if ($errtotal > $rowsperpage) {
                $html .= $output->paging_bar($errtotal, $errorpage, $rowsperpage, $errnavurl, 'errorpage');
            }
        } else {
            $html .= \html_writer::tag('p', get_string('bulkpreviewnoerrors', 'block_courseimport'));
        }

        if (!$resolvedpairs) {
            $html .= $output->continue_button(new url('/blocks/courseimport/bulk/index.php'));
            return $html;
        }

        $confirmurl = new url('/blocks/courseimport/bulk/submit.php', ['confirm' => 1]);
        $html .= $output->single_button($confirmurl, get_string('bulkconfirmsubmit', 'block_courseimport'));
        $html .= \html_writer::div(
            \html_writer::link(
                new url('/blocks/courseimport/bulk/index.php'),
                get_string('bulkbacktoupload', 'block_courseimport'),
                ['class' => 'badge badge-info']
            ),
            'mt-2'
        );

        return $html;
    }
}
