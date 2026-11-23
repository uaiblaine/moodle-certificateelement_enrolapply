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
use enrol_apply\customfield\application_handler;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the application answers read by question short name.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(answers::class)]
final class answers_test extends \advanced_testcase {
    use application_fixture;

    /** @var int The course applied for. */
    private int $courseid;

    /** @var int The applicant. */
    private int $userid;

    /** @var int The course's enrol_apply method. */
    private int $enrolid;

    /**
     * A course with one enrol_apply method, and a user.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->enrolid = $this->add_method($this->courseid);
    }

    /**
     * An approved application answers by the short name of each question.
     */
    public function test_an_approved_application_answers_by_short_name(): void {
        $unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $rank = (int) $this->add_method_question($this->enrolid, 'rank')->get('id');
        $answers = [$unit => '3rd Battalion', $rank => 'Sergeant'];
        $this->add_application($this->courseid, $this->userid, $this->enrolid, $answers);

        $this->assertSame(
            ['unit' => '3rd Battalion', 'rank' => 'Sergeant'],
            answers::for_learner($this->courseid, $this->userid)
        );
    }

    /**
     * An application that is pending, deferred or cancelled answers nothing; approved, it does.
     */
    public function test_only_an_approved_application_answers(): void {
        global $DB;

        $unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $undecided = [submission::STATUS_PENDING, submission::STATUS_WAITING, submission::STATUS_CANCELLED];
        $ids = [];
        foreach ($undecided as $status) {
            $ids[] = $this->add_application($this->courseid, $this->userid, $this->enrolid, [$unit => 'North'], $status);
        }

        $this->assertSame([], answers::for_learner($this->courseid, $this->userid));

        // The control: the same record answers once it is approved.
        $DB->set_field('enrol_apply_submission', 'status', submission::STATUS_APPROVED, ['id' => $ids[0]]);
        $this->assertSame(['unit' => 'North'], answers::for_learner($this->courseid, $this->userid));
    }

    /**
     * With two approved applications the newest that answered a short name wins, short name by short name.
     */
    public function test_the_newest_approved_application_that_answered_wins(): void {
        $second = $this->add_method($this->courseid);
        $oldunit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $oldrank = (int) $this->add_method_question($this->enrolid, 'rank')->get('id');
        $newunit = (int) $this->add_method_question($second, 'unit')->get('id');
        $this->add_application(
            $this->courseid,
            $this->userid,
            $this->enrolid,
            [$oldunit => 'Old unit', $oldrank => 'Sergeant'],
            submission::STATUS_APPROVED,
            1700000000
        );
        $this->add_application(
            $this->courseid,
            $this->userid,
            $second,
            [$newunit => 'New unit'],
            submission::STATUS_APPROVED,
            1710000000
        );

        $this->assertEquals(
            ['unit' => 'New unit', 'rank' => 'Sergeant'],
            answers::for_learner($this->courseid, $this->userid)
        );
    }

    /**
     * Profile fields in the same snapshot, and an id that names no application question, answer nothing.
     */
    public function test_only_application_questions_answer(): void {
        $unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        // A course custom field: a restored answer can carry the id of any custom field.
        $foreign = (int) $this->add_question('codename', 'core_course', 'course', 0)->get('id');
        $this->add_application($this->courseid, $this->userid, $this->enrolid, [
            $unit => 'North',
            's_city' => 'Fortaleza',
            // The same number under the profile field prefix, after the answer: read as an answer, it would replace it.
            'c_' . $unit => 'A profile field',
            $foreign => 'Not an application answer',
        ]);

        $this->assertSame(['unit' => 'North'], answers::for_learner($this->courseid, $this->userid));
    }

    /**
     * A question answers only for an application to its own method, whatever key the record carries.
     */
    public function test_a_question_of_another_method_does_not_answer(): void {
        $second = $this->add_method($this->courseid);
        $unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $other = (int) $this->add_method_question($second, 'codename')->get('id');
        // An application to the first method carrying an answer keyed by the second method's question.
        $this->add_application($this->courseid, $this->userid, $this->enrolid, [$unit => 'North', $other => 'Forged']);

        $this->assertSame(['unit' => 'North'], answers::for_learner($this->courseid, $this->userid));

        // The control: the same question answers for an application to its own method.
        $this->add_application($this->courseid, $this->userid, $second, [$other => 'Given']);
        $this->assertEquals(
            ['unit' => 'North', 'codename' => 'Given'],
            answers::for_learner($this->courseid, $this->userid)
        );
    }

    /**
     * A question of a site-wide shared category answers by its short name too.
     */
    public function test_a_shared_question_answers(): void {
        $badge = (int) $this->add_question('badge', 'core_customfield', 'shared', 0)->get('id');
        $this->add_application($this->courseid, $this->userid, $this->enrolid, [$badge => '4711']);

        $this->assertSame(['badge' => '4711'], answers::for_learner($this->courseid, $this->userid));
    }

    /**
     * Only the asked learner's applications to the asked course are read.
     */
    public function test_other_courses_and_other_users_are_not_read(): void {
        $unit = (int) $this->add_method_question($this->enrolid, 'unit')->get('id');
        $othercourse = (int) $this->getDataGenerator()->create_course()->id;
        $otheruser = (int) $this->getDataGenerator()->create_user()->id;
        // The record's course decides, as in enrol_apply: the same method stands in for the other course's.
        $this->add_application($othercourse, $this->userid, $this->enrolid, [$unit => 'Elsewhere']);
        $this->add_application($this->courseid, $otheruser, $this->enrolid, [$unit => 'Somebody else']);

        $this->assertSame([], answers::for_learner($this->courseid, $this->userid));
        // The control: the same records answer for the course and the learner they belong to.
        $this->assertSame(['unit' => 'Elsewhere'], answers::for_learner($othercourse, $this->userid));
        $this->assertSame(['unit' => 'Somebody else'], answers::for_learner($this->courseid, $otheruser));
    }

    /**
     * The hint lists shared questions in use for everybody, and each method's own only on request.
     */
    public function test_the_known_short_names_follow_the_reader(): void {
        $this->add_method_question($this->enrolid, 'unit');
        $this->add_method_question($this->enrolid, 'rank');
        $badge = $this->add_question('badge', 'core_customfield', 'shared', 0);
        $this->add_question('unused', 'core_customfield', 'shared', 0);
        (new \core_customfield\shared(0, (object) [
            'categoryid' => $badge->get('categoryid'),
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $this->enrolid,
        ]))->create();

        $this->assertSame(['badge'], answers::known_shortnames(false, 40));
        $this->assertSame(['badge', 'rank', 'unit'], answers::known_shortnames(true, 40));
        $this->assertSame(['badge', 'rank'], answers::known_shortnames(true, 2));
    }
}
