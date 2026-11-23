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

use certificateelement_enrolapply\local\answers;
use certificateelement_enrolapply\local\snapshot;
use tool_certificate\element_helper;

/**
 * Certificate element printing the learner's answer to one question of their enrolment application.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element extends \tool_certificate\element {
    /** @var int The most short names the hint under the field lists. */
    private const HINT_LIMIT = 40;

    /**
     * The short name of the question this element prints the answer to.
     *
     * @return string Empty when the element has none yet.
     */
    public function get_shortname(): string {
        return snapshot::shortname_in($this->get_data());
    }

    /**
     * Add this element's fields to the element form.
     *
     * @param \MoodleQuickForm $mform The edit form.
     * @return void
     */
    public function render_form_elements($mform) {
        $mform->addElement(
            'text',
            'shortname',
            get_string('shortname', 'certificateelement_enrolapply'),
            ['size' => 30, 'maxlength' => 100]
        );
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addHelpButton('shortname', 'shortname', 'certificateelement_enrolapply');

        $everymethod = has_capability('tool/certificate:manage', \context_system::instance());
        $known = answers::known_shortnames($everymethod, self::HINT_LIMIT);
        if ($known) {
            $mform->addElement(
                'static',
                'knownshortnames',
                '',
                get_string('knownshortnames', 'certificateelement_enrolapply', s(implode(', ', $known)))
            );
        }

        parent::render_form_elements($mform);
    }

    /**
     * Validate what the element form submitted.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Field name => error message.
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);
        if (!preg_match(answers::SHORTNAME_PATTERN, (string) ($data['shortname'] ?? ''))) {
            $errors['shortname'] = get_string('shortnameinvalid', 'certificateelement_enrolapply');
        }
        return $errors;
    }

    /**
     * The saved element as form data.
     *
     * @return \stdClass
     */
    public function prepare_data_for_form() {
        $record = parent::prepare_data_for_form();
        $record->shortname = $this->get_shortname();
        return $record;
    }

    /**
     * Save what the element form submitted.
     *
     * @param \stdClass $data The form data, or the part of it being updated.
     * @return void
     */
    public function save_form_data(\stdClass $data) {
        // A partial update (a rename, a move) carries no short name and must not reset the stored one.
        if (property_exists($data, 'shortname')) {
            // Stored as given: snapshot::shortname_in() is the one place that judges it.
            $data->data = json_encode(['shortname' => $data->shortname]);
            // An unnamed element is listed by its question, so two of them can be told apart.
            if (property_exists($data, 'name') && (string) $data->name === '') {
                $data->name = snapshot::shortname_in($data->data);
            }
        }
        parent::save_form_data($data);
    }

    /**
     * What stands in for the answer where there is no learner: the short name between brackets.
     *
     * @return string HTML.
     */
    private function placeholder(): string {
        $shortname = $this->get_shortname();
        return $shortname === '' ? '' : s('[' . $shortname . ']');
    }

    /**
     * Print the element on the PDF.
     *
     * Nothing is printed when the learner has no answer to the question: they were enrolled
     * another way, the course does not ask it, or they left it empty.
     *
     * @param \pdf $pdf The PDF.
     * @param bool $preview Whether this is the template preview.
     * @param \stdClass $user The user the certificate is for.
     * @param \stdClass|null $issue The issue being rendered, null on a preview.
     * @return void
     */
    public function render($pdf, $preview, $user, $issue) {
        if ($preview) {
            $content = $this->placeholder();
        } else {
            // The answer is plain text and the PDF cell takes HTML.
            $content = s(snapshot::answer_of($issue ?: null, $this->get_shortname()));
        }
        if ($content !== '') {
            element_helper::render_content($pdf, $this, $content);
        }
    }

    /**
     * The element as HTML, for the template editor's drag and drop page.
     *
     * @return string
     */
    public function render_html() {
        return element_helper::render_html_content($this, $this->placeholder());
    }
}
