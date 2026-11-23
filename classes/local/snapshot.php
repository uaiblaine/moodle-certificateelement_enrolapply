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

namespace certificateelement_enrolapply\local;

use stdClass;

/**
 * The application answers frozen into a certificate issue.
 *
 * A certificate template knows no course; the issue does. tool_certificate keeps what an issue
 * was made from as JSON in tool_certificate_issues.data, and every later rendering of the PDF
 * reads it, so the answers a template prints are written there once, when the certificate is
 * issued. They then outlive the application record, which enrol_apply's retention sweep deletes.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot {
    /** @var string Key of the frozen answers in the issue's data; the plugin's name, so no issuer's own key collides. */
    public const KEY = 'certificateelement_enrolapply';

    /** @var string The name tool_certificate stores this element type under. */
    private const ELEMENT = 'enrolapply';

    /**
     * Freeze the answers the issue's template prints.
     *
     * Only the short names an element of the template names are copied: an answer can be free
     * text, and nothing the certificate does not print is duplicated here. Each of them is
     * written even when there is no answer, as an empty string: the key then says "looked, found
     * none", and {@see self::answer_of()} does not look again.
     *
     * @param int $issueid Row id in tool_certificate_issues.
     * @return void
     */
    public static function capture(int $issueid): void {
        global $DB;

        $issue = $DB->get_record('tool_certificate_issues', ['id' => $issueid], 'id, userid, courseid, templateid, data');
        if (!$issue || empty($issue->courseid)) {
            return;
        }
        $wanted = self::template_shortnames((int) $issue->templateid);
        if (!$wanted) {
            return;
        }

        $answers = answers::for_learner((int) $issue->courseid, (int) $issue->userid);
        $frozen = [];
        foreach ($wanted as $shortname) {
            $frozen[$shortname] = (string) ($answers[$shortname] ?? '');
        }

        $data = json_decode((string) $issue->data, true);
        if (!is_array($data)) {
            $data = [];
        }
        $data[self::KEY] = $frozen;
        $DB->set_field('tool_certificate_issues', 'data', json_encode($data), ['id' => $issue->id]);
    }

    /**
     * The question short names the elements of a template name.
     *
     * @param int $templateid Row id in tool_certificate_templates.
     * @return array List of short names, each once.
     */
    private static function template_shortnames(int $templateid): array {
        global $DB;

        $sql = "SELECT e.id, e.data
                  FROM {tool_certificate_elements} e
                  JOIN {tool_certificate_pages} p ON p.id = e.pageid
                 WHERE p.templateid = :templateid AND e.element = :element";
        $elements = $DB->get_records_sql($sql, ['templateid' => $templateid, 'element' => self::ELEMENT]);

        $shortnames = [];
        foreach ($elements as $element) {
            $shortname = self::shortname_in($element->data);
            if ($shortname !== '') {
                $shortnames[$shortname] = $shortname;
            }
        }
        return array_values($shortnames);
    }

    /**
     * The question short name stored in an element's data.
     *
     * @param string|null $elementdata The data column of tool_certificate_elements.
     * @return string The short name, empty when there is none or it is not one.
     */
    public static function shortname_in(?string $elementdata): string {
        $stored = json_decode((string) $elementdata, true);
        $shortname = is_array($stored) ? ($stored['shortname'] ?? '') : '';
        return is_string($shortname) && preg_match(answers::SHORTNAME_PATTERN, $shortname) ? $shortname : '';
    }

    /**
     * The answer frozen in an issue for one question.
     *
     * @param stdClass|null $issue Row of tool_certificate_issues.
     * @param string $shortname The question's short name.
     * @return string|null The answer, empty for "no answer when it was issued", null when the issue froze none for it.
     */
    public static function read(?stdClass $issue, string $shortname): ?string {
        if (!$issue || empty($issue->data)) {
            return null;
        }
        $data = json_decode((string) $issue->data, true);
        $frozen = is_array($data) ? ($data[self::KEY] ?? null) : null;
        if (!is_array($frozen) || !array_key_exists($shortname, $frozen) || !is_scalar($frozen[$shortname])) {
            return null;
        }
        return (string) $frozen[$shortname];
    }

    /**
     * The answer an issue prints for one question.
     *
     * The frozen answer when the issue has one for that short name. An issue older than the
     * plugin, or than the element on its template, has none: the applications are then read as
     * they stand now, which is the best answer left and can change until the certificate is issued
     * again.
     *
     * @param stdClass|null $issue Row of tool_certificate_issues.
     * @param string $shortname The question's short name.
     * @return string The answer in its plain spelling, empty when there is nothing to print.
     */
    public static function answer_of(?stdClass $issue, string $shortname): string {
        if ($shortname === '') {
            return '';
        }
        $frozen = self::read($issue, $shortname);
        if ($frozen !== null) {
            return $frozen;
        }
        if (!$issue || empty($issue->courseid) || empty($issue->userid)) {
            return '';
        }
        return (string) (answers::for_learner((int) $issue->courseid, (int) $issue->userid)[$shortname] ?? '');
    }
}
