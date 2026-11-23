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

namespace certificateelement_enrolapply;

use certificateelement_enrolapply\tests\application_fixture;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Tests of the enrolment application answer element.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(element::class)]
final class element_test extends \advanced_testcase {
    use application_fixture;

    /**
     * The certificate tool's generator.
     *
     * @return \tool_certificate_generator
     */
    private function generator(): \tool_certificate_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate');
    }

    /**
     * A template with one page holding one enrolment application answer element.
     *
     * @param array $data The element's form data.
     * @return array The template, then the element.
     */
    private function template_with_element(array $data = ['name' => '', 'shortname' => 'unit']): array {
        $template = $this->generator()->create_template((object) ['name' => 'Certificate']);
        $pageid = $this->generator()->create_page($template)->get_id();
        /** @var element $element */
        $element = $this->generator()->create_element($pageid, 'enrolapply', $data);
        return [$template, $element];
    }

    /**
     * A PDF that expects one text to be written, or none.
     *
     * @param string|null $text The text expected, null when nothing may be written.
     * @return \pdf
     */
    private function pdf_expecting(?string $text): \pdf {
        $pdf = $this->getMockBuilder(\pdf::class)->onlyMethods(['writeHTMLCell'])->getMock();
        if ($text === null) {
            $pdf->expects($this->never())->method('writeHTMLCell');
        } else {
            $pdf->expects($this->once())
                ->method('writeHTMLCell')
                ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), $text);
        }
        $pdf->AddPage();
        return $pdf;
    }

    /**
     * The PDF of an issue prints the learner's answer, escaped once.
     */
    public function test_render_prints_the_answer_escaped(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $enrolid = $this->add_method((int) $course->id);
        $unit = (int) $this->add_method_question($enrolid, 'unit')->get('id');
        $this->add_application((int) $course->id, (int) $user->id, $enrolid, [$unit => 'R&D < Team']);
        [$template, $element] = $this->template_with_element();
        $this->redirectMessages();
        $issue = $this->generator()->issue($template, $user, null, [], 'mod_coursecertificate', (int) $course->id);

        $element->render($this->pdf_expecting('R&amp;D &lt; Team'), false, $user, $issue);
    }

    /**
     * A learner with no answer to the question gets nothing printed.
     */
    public function test_render_prints_nothing_without_an_answer(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        [$template, $element] = $this->template_with_element();
        $this->redirectMessages();
        $issue = $this->generator()->issue($template, $user, null, [], 'mod_coursecertificate', (int) $course->id);

        $element->render($this->pdf_expecting(null), false, $user, $issue);
    }

    /**
     * The template preview and the editor show the short name between brackets.
     */
    public function test_the_preview_shows_the_short_name(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        [, $element] = $this->template_with_element();

        $element->render($this->pdf_expecting('[unit]'), true, $user, null);
        $this->assertStringContainsString('[unit]', $element->render_html());
    }

    /**
     * An unnamed element takes its question's short name, and a rename leaves the question alone.
     */
    public function test_the_short_name_survives_the_form_and_a_partial_update(): void {
        $this->resetAfterTest();
        [, $element] = $this->template_with_element();

        $this->assertSame('unit', $element->get_name());
        $this->assertSame('unit', $element->prepare_data_for_form()->shortname);

        $element->save_form_data((object) ['name' => 'Renamed']);
        $reloaded = \tool_certificate\element::instance($element->get_id());
        $this->assertSame('Renamed', $reloaded->get_name());
        $this->assertSame('unit', $reloaded->get_shortname());
    }

    /**
     * The form refuses what is not a short name, and one stored past the form reads as none.
     */
    public function test_only_a_short_name_is_accepted(): void {
        $this->resetAfterTest();
        [, $element] = $this->template_with_element(['name' => 'Answer', 'shortname' => 'Unit name']);

        $this->assertSame('', $element->get_shortname());
        $this->assertSame('', trim(strip_tags($element->render_html())));

        $this->assertArrayHasKey('shortname', $element->validate_form_elements(['shortname' => 'Unit name'], []));
        $this->assertArrayHasKey('shortname', $element->validate_form_elements(['shortname' => ''], []));
        $this->assertArrayHasKey('shortname', $element->validate_form_elements([], []));
        $this->assertArrayNotHasKey('shortname', $element->validate_form_elements(['shortname' => 'unit_1'], []));
    }
}
