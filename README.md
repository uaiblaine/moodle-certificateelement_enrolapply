moodle-certificateelement_enrolapply
====================================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-certificateelement_enrolapply/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-certificateelement_enrolapply/actions/workflows/ci.yml?query=branch%3Amain)

A certificate element for the Workplace certificate tool (`tool_certificate`) that prints what the
learner answered to one question of their enrolment application (`enrol_apply`).

`enrol_apply` lets each enrolment method ask its own questions when somebody applies for a course,
and keeps the answers with the application. This element puts one of those answers on the course
certificate: the unit somebody serves in, a registration number, the class they chose. The question
is named by its short name, so one certificate template serves every course whose method asks a
question with that short name.


How the answer is found
-----------------------

- The certificate must be issued for a course (the course certificate activity does that). The
  answer comes from the learner's **approved** application to that course. A pending, deferred or
  cancelled application answers nothing.
- The question is found by its **short name**, as set in the question's own settings. Questions of
  different courses answer to the same element when they have the same short name; a question of a
  shared category has one short name for the whole site. An answer counts only for a question of
  the enrolment method the application was made to, or of a shared category.
- When a learner has more than one approved application in the course (two enrolment methods are
  two intakes), the newest application that answered the question wins.
- The answer is printed as the application recorded it, with nothing to configure per type: "Yes"
  or "No" for a checkbox, the chosen option for a menu, the formatted number for a number, and the
  date in full for a date, in the language the applicant used. The raw value is not kept by
  `enrol_apply`, so a date cannot be written in another format.
- The answers a template prints are stored with the certificate when it is issued. A profile or an
  application changed afterwards changes no certificate, and the certificate keeps the answer after
  `enrol_apply` deletes the application record at the end of its retention period.

Nothing is printed for a learner who was enrolled another way, whose course does not ask the
question, or who left it unanswered.


Requirements
------------

- Moodle 5.3.1 or later (tested up to Moodle 5.3)
- `tool_certificate` 2026100601 or later
- `enrol_apply` 2026112109 or later (the fork with application questions)


Installation
------------

Install the plugin like any other plugin to folder `/admin/tool/certificate/element/enrolapply`.

See http://docs.moodle.org/en/Installing_plugins for details on installing
Moodle plugins.

It works without configuration.


Usage
-----

In Site administration > Certificates > Manage certificate templates, edit a template and add the
"Enrolment application answer" element. Type the short name of the question; font, colour, position
and width are the certificate tool's usual fields. Under the field, the form lists the short names
in use: shared questions for everybody, and the questions of every enrolment method for whoever
manages certificate templates site-wide. The template preview shows the short name between
brackets.

An element added to a template after certificates were issued finds no stored answer in them. For
those the element reads the applications as they stand when the PDF is generated; issue the
certificate again to fix the answer.


Data stored
-----------

The plugin has no table, no setting, no capability and no user preference.

It adds one value to a row the certificate tool owns: the key `certificateelement_enrolapply` in
the JSON of `tool_certificate_issues.data`, holding the answers the template prints, by question
short name. Only the answers the template prints are copied; an empty text means the learner had no
answer when the certificate was issued. The value is kept as long as the certificate issue and is
removed with it: a deletion request for the learner deletes the issue through the privacy provider
of `tool_certificate`. Three things an administrator should know before printing an answer:

- **A data request does not export the copy.** The certificate tool's provider exports the issue's
  code, dates and custom fields, not the `data` column. The answer reaches the learner as printed on
  the certificate file, and in the export of `enrol_apply` while the application record exists.
- **The copy outlives the application.** The retention period of `enrol_apply` and the deletion of
  a user account remove the application's answers and leave the certificate issue, with the copy,
  as they leave the certificate itself. A privacy deletion request for the learner removes both.
- **A certificate is read more widely than an application.** `enrol_apply` shows an answer only to
  whoever decides or reviews applications, under its own masking. On a certificate the answer is
  read by the learner, by whoever manages or issues certificates of the template, and by anybody who
  holds the certificate's code and may use the verification page (every logged-in user by default).
  An answer can be free text the applicant typed: print only the questions meant for a certificate.


Troubleshooting
---------------

- **The element prints nothing.** In likely order: the certificate was issued outside a course; the
  learner was not enrolled through an approved application; the course's method asks no question
  with that short name; the learner left it unanswered; the question was deleted or its short name
  changed after the application (answers are kept by question, and the short name is read from the
  question as it is now); the enrolment method was deleted, which deletes its questions.
- **The date reads in another language or format than the rest of the certificate.** It is printed
  as the application recorded it, in the applicant's language.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation
is maintained in this repository (`lang/pt_br`) and reaches sites through
[AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left
out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
