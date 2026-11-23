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

use certificateelement_enrolapply\tests\application_fixture;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the application answers frozen into a certificate issue.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(snapshot::class)]
#[CoversClass(\certificateelement_enrolapply\observer::class)]
final class snapshot_test extends \advanced_testcase {
    use application_fixture;

    /** @var int The course applied for. */
    private int $courseid;

    /** @var int The applicant. */
    private int $userid;

    /** @var int The course's enrol_apply method. */
    private int $enrolid;

    /** @var int The "unit" question of the method. */
    private int $unit;

    /** @var int The "rank" question of the method. */
    private int $rank;

    /**
     * A course whose method asks two questions, and a user.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->enrolid = $this->add_method($this->courseid);
        $this->unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $this->rank = (int) $this->add_method_question($this->enrolid, 'rank')->get('id');
    }

    /**
     * Issue a certificate of a new template whose elements name the given questions.
     *
     * @param array $shortnames The short name of each enrolment application answer element.
     * @param int|null $courseid The course it is issued for, null for an issue outside a course.
     * @return \stdClass The row of tool_certificate_issues, read after the issue was made.
     */
    private function issue(array $shortnames, ?int $courseid): \stdClass {
        /** @var \tool_certificate_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');
        static $count = 0;
        $template = $generator->create_template((object) ['name' => 'Certificate ' . ++$count]);
        $pageid = $generator->create_page($template)->get_id();
        foreach ($shortnames as $shortname) {
            $generator->create_element($pageid, 'enrolapply', ['name' => '', 'shortname' => $shortname]);
        }
        $this->redirectMessages();
        $data = ['courseshortname' => 'C1'];
        return $generator->issue($template, $this->userid, null, $data, 'mod_coursecertificate', $courseid);
    }

    /**
     * An issue freezes the answers its template prints, and no other answer of the application.
     */
    public function test_an_issue_freezes_only_the_answers_its_template_prints(): void {
        $answers = [$this->unit => 'North', $this->rank => 'Sergeant'];
        $this->add_application($this->courseid, $this->userid, $this->enrolid, $answers);

        // Another template's elements are not this issue's.
        /** @var \tool_certificate_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');
        $other = $generator->create_template((object) ['name' => 'Another certificate']);
        $generator->create_element($generator->create_page($other)->get_id(), 'enrolapply', ['name' => '', 'shortname' => 'rank']);

        $issue = $this->issue(['unit'], $this->courseid);

        $data = json_decode($issue->data, true);
        $this->assertSame(['unit' => 'North'], $data[snapshot::KEY]);
        $this->assertSame('North', snapshot::read($issue, 'unit'));
        $this->assertNull(snapshot::read($issue, 'rank'));
        // What the issuer and the certificate tool stored is still there.
        $this->assertSame('C1', $data['courseshortname']);
        $this->assertArrayHasKey('userfullname', $data);
    }

    /**
     * A template with no such element, and an issue outside a course, freeze nothing.
     */
    public function test_nothing_is_frozen_without_an_element_or_a_course(): void {
        $this->add_application($this->courseid, $this->userid, $this->enrolid, [$this->unit => 'North']);

        $without = $this->issue([], $this->courseid);
        $outside = $this->issue(['unit'], null);

        $this->assertArrayNotHasKey(snapshot::KEY, json_decode($without->data, true));
        $this->assertArrayNotHasKey(snapshot::KEY, json_decode($outside->data, true));
        $this->assertSame('', snapshot::answer_of($outside, 'unit'));
    }

    /**
     * The frozen answer outlives the application record; without it the certificate would lose it.
     */
    public function test_the_frozen_answer_outlives_the_application(): void {
        $recordid = $this->add_application($this->courseid, $this->userid, $this->enrolid, [$this->unit => 'North']);
        $issue = $this->issue(['unit'], $this->courseid);

        submission::delete($recordid);

        $this->assertSame('North', snapshot::answer_of($issue, 'unit'));

        // The control: an issue with nothing frozen reads the applications as they stand now.
        $data = json_decode($issue->data, true);
        unset($data[snapshot::KEY]);
        $issue->data = json_encode($data);
        $this->assertSame('', snapshot::answer_of($issue, 'unit'));
    }

    /**
     * Regenerating the certificate keeps what was frozen, and prints from it.
     */
    public function test_a_regeneration_keeps_the_frozen_answers(): void {
        global $DB;

        $recordid = $this->add_application($this->courseid, $this->userid, $this->enrolid, [$this->unit => 'North']);
        $issue = $this->issue(['unit'], $this->courseid);
        submission::delete($recordid);

        \tool_certificate\template::instance($issue->templateid)->create_issue_file($issue, true);

        $stored = $DB->get_record('tool_certificate_issues', ['id' => $issue->id], '*', MUST_EXIST);
        $this->assertSame('North', snapshot::read($stored, 'unit'));
    }

    /**
     * No answer at the time of issue is frozen too, so a later application does not fill the certificate.
     */
    public function test_no_answer_at_the_time_of_issue_is_frozen_as_none(): void {
        $issue = $this->issue(['unit'], $this->courseid);

        $this->add_application($this->courseid, $this->userid, $this->enrolid, [$this->unit => 'North']);

        $this->assertSame('', snapshot::read($issue, 'unit'));
        $this->assertSame('', snapshot::answer_of($issue, 'unit'));
        // The control: the application made afterwards is one the reader would find.
        $this->assertSame(['unit' => 'North'], answers::for_learner($this->courseid, $this->userid));
    }

    /**
     * A question the issue froze nothing for (its element came later) is read from the applications.
     */
    public function test_a_question_added_to_the_template_later_is_read_live(): void {
        $answers = [$this->unit => 'North', $this->rank => 'Sergeant'];
        $this->add_application($this->courseid, $this->userid, $this->enrolid, $answers);
        $issue = $this->issue(['unit'], $this->courseid);

        $this->assertSame('Sergeant', snapshot::answer_of($issue, 'rank'));
        $this->assertSame('', snapshot::answer_of($issue, ''));
    }

    /**
     * A short name is read from an element's data only when it is one.
     */
    public function test_the_short_name_of_an_element_is_validated_on_read(): void {
        $this->assertSame('unit_1', snapshot::shortname_in(json_encode(['shortname' => 'unit_1'])));
        $this->assertSame('', snapshot::shortname_in(json_encode(['shortname' => 'Unit name'])));
        $this->assertSame('', snapshot::shortname_in(json_encode(['shortname' => "unit\n"])));
        $this->assertSame('', snapshot::shortname_in(json_encode(['shortname' => ['unit']])));
        $this->assertSame('', snapshot::shortname_in('not json'));
        $this->assertSame('', snapshot::shortname_in(null));
    }
}
