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

use enrol_apply\customfield\application_handler;
use enrol_apply\local\answers as application_answers;
use enrol_apply\local\submission;

/**
 * What a learner answered to the questions of their enrolment application, by question short name.
 *
 * enrol_apply freezes each answer under the id of its question (a_<fieldid>), because a short
 * name can be edited. A certificate template serves many courses, each with questions and ids of
 * its own, so here the id is translated to the short name the question has now.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class answers {
    /** @var string The table of enrol_apply's durable application records. */
    private const SUBMISSION_TABLE = 'enrol_apply_submission';

    /** @var string Pattern a custom field short name follows, as core's field form validates it. */
    public const SHORTNAME_PATTERN = '/^[a-z0-9_]{1,100}\z/';

    /**
     * The answers of a learner's approved applications in a course.
     *
     * A course can carry several enrolment methods, each an intake with questions of its own, and
     * a learner can be approved through more than one. For each short name the newest approved
     * application that answered it wins. An answer is the display text enrol_apply froze at
     * submission, in its plain spelling: the caller escapes it.
     *
     * @param int $courseid The course.
     * @param int $userid The learner.
     * @return array Question short name => answer.
     */
    public static function for_learner(int $courseid, int $userid): array {
        global $DB;

        $records = $DB->get_records(
            self::SUBMISSION_TABLE,
            ['courseid' => $courseid, 'userid' => $userid, 'status' => submission::STATUS_APPROVED],
            'timecreated DESC, id DESC',
            'id, enrolid'
        );
        $byrecord = self::by_question($records);
        if (!$byrecord) {
            return [];
        }

        $questions = self::questions(array_unique(array_merge(...array_map('array_keys', array_column($byrecord, 'values')))));
        $answers = [];
        foreach ($byrecord as $record) {
            foreach ($record['values'] as $fieldid => $value) {
                $question = $questions[$fieldid] ?? null;
                if ($question === null || !in_array($question['enrolid'], [null, $record['enrolid']], true)) {
                    continue;
                }
                if (!array_key_exists($question['shortname'], $answers)) {
                    $answers[$question['shortname']] = $value;
                }
            }
        }
        return $answers;
    }

    /**
     * The answers of application records, by question id.
     *
     * Read through enrol_apply's own snapshot reader; the profile fields a snapshot also holds
     * are left out.
     *
     * @param array $records Rows of enrol_apply_submission carrying id and enrolid, in the order the result keeps.
     * @return array One entry per record that answered a question: 'enrolid', and 'values' as field id => answer.
     */
    private static function by_question(array $records): array {
        $snapshots = submission::read_snapshots(array_keys($records));
        $prefix = strlen(application_answers::SOURCE_ANSWER);

        $byrecord = [];
        foreach ($records as $record) {
            $values = [];
            foreach ($snapshots[$record->id] ?? [] as $entry) {
                $fieldid = application_answers::is_answer($entry['key']) ? (int) substr($entry['key'], $prefix) : 0;
                if ($fieldid > 0) {
                    $values[$fieldid] = $entry['value'];
                }
            }
            if ($values) {
                $byrecord[] = ['enrolid' => (int) $record->enrolid, 'values' => $values];
            }
        }
        return $byrecord;
    }

    /**
     * The application questions behind field ids: each one's short name and the method it belongs to.
     *
     * Only fields an application can ask: a question of an enrolment method, which answers for
     * applications to that method alone, and a question of a site-wide shared category, which
     * answers for any. That is what enrol_apply's restore keeps too: an answer whose question it
     * cannot map stays only as a shared question's. An id that names anything else resolves to
     * nothing. A shared category is accepted whether or not the method still has it switched on:
     * the answer row is the record that the question was asked.
     *
     * @param array $fieldids Row ids in customfield_field.
     * @return array Field id => array with 'shortname' and 'enrolid' (null for a shared question).
     */
    private static function questions(array $fieldids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(array_values($fieldids), SQL_PARAMS_NAMED, 'field');
        $sql = "SELECT f.id, f.shortname, c.component, c.itemid
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE f.id {$insql}
                       AND ((c.component = :owncomponent AND c.area = :ownarea)
                            OR (c.component = :sharedcomponent AND c.area = :sharedarea))";
        $params += [
            'owncomponent' => 'enrol_apply',
            'ownarea' => application_handler::AREA,
            'sharedcomponent' => 'core_customfield',
            'sharedarea' => 'shared',
        ];

        $questions = [];
        foreach ($DB->get_records_sql($sql, $params) as $field) {
            $questions[(int) $field->id] = [
                'shortname' => (string) $field->shortname,
                'enrolid' => $field->component === 'enrol_apply' ? (int) $field->itemid : null,
            ];
        }
        return $questions;
    }

    /**
     * Short names an element could name, for the hint under the element's field.
     *
     * The shared questions any enrolment method has switched on, and, for whoever manages
     * certificate templates site-wide, the questions of every method: a manager of one category's
     * templates is not shown how other categories' courses word their forms.
     *
     * @param bool $everymethod Whether the questions of every enrolment method are listed too.
     * @param int $limit The most short names returned.
     * @return array List of short names, sorted.
     */
    public static function known_shortnames(bool $everymethod, int $limit): array {
        global $DB;

        $shortnames = [];
        foreach (application_answers::shared_fields_in_use() as $field) {
            $shortnames[(string) $field->get('shortname')] = true;
        }
        if ($everymethod) {
            $sql = "SELECT DISTINCT f.shortname
                      FROM {customfield_field} f
                      JOIN {customfield_category} c ON c.id = f.categoryid
                     WHERE c.component = :component AND c.area = :area
                  ORDER BY f.shortname";
            $params = ['component' => 'enrol_apply', 'area' => application_handler::AREA];
            foreach ($DB->get_records_sql($sql, $params, 0, $limit) as $field) {
                $shortnames[(string) $field->shortname] = true;
            }
        }

        $shortnames = array_keys($shortnames);
        sort($shortnames, SORT_STRING);
        return array_slice($shortnames, 0, $limit);
    }
}
