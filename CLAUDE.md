# Claude instructions for `certificateelement_enrolapply`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a **certificateelement** subplugin of `tool_certificate` ("Enrolment application answer") that
prints what the learner answered to one question of their enrolment application (`enrol_apply`),
named by the question's short name. It owns no table: the answers a template asks for are frozen
into `tool_certificate_issues.data` when a certificate is issued. Supports Moodle **5.3 only**
(`$plugin->requires = 2026100501`, `$plugin->supported = [503, 503]`), because the certificate
fork is mounted on the 5.3 stacks only and `enrol_apply` is Moodle 5.3.1+. CI is `uaiblaine/moodle-workflows`, one job in
`.github/workflows/ci.yml` — **update it when `supported` changes**. The plugin installs at
`admin/tool/certificate/element/enrolapply`, inside the certificate tool's own tree.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-certificateelement_enrolapply --branch MOODLE_503_STABLE --php 8.4            # one leg
mdl ci moodle-certificateelement_enrolapply --branch MOODLE_503_STABLE --php 8.4 --db mariadb
mdl phpunit m503 certificateelement_enrolapply    # once the plugin is mounted on the stack
```

## Code layout

```
classes/element.php        the element: its one field (the question's short name), the PDF and editor rendering
classes/local/answers.php  the answers of a learner's approved applications in a course, by short name
classes/local/snapshot.php the answers frozen into an issue: capture(), read(), answer_of()
classes/observer.php       certificate_issued -> snapshot::capture()
db/events.php              the one observer
```

## Versions

`$plugin->version` counts in the Moodle 5.3 namespace (`20261005XX`), as every branch-per-version
plugin of the fleet does (`~/dev/CLAUDE.md`, "XMLDB, upgrades, privacy, versioning").

## Architecture gotchas

- **A question is named by its short name, never by its id.** A template lives in the system or a
  category context and serves many courses; each enrolment method has its own questions, with ids
  of their own, and a shared question has one id site-wide. The short name is the only key a
  template can carry across courses. `enrol_apply` freezes answers under `a_<fieldid>`, so the id
  is translated to the short name the field has NOW: a question deleted since, or renamed, no
  longer answers to its old short name.
- **An answer counts only for a question of the record's own method, or of a shared category**
  (`answers::questions()`: the category's `itemid` is the record's `enrolid`). It is the rule
  `enrol_apply`'s restore keeps (an answer it cannot map stays only as a shared question's), so an
  archive cannot make an application answer to another method's short name. A shared category
  counts whether or not the method still has it switched on: the answer row is the record that the
  question was asked. What an archive CAN do is carry any text under the course's own questions:
  restoring with user data is the trust boundary, here as in `enrol_apply`.
- **The answers are read through `enrol_apply`'s own readers** (`submission::read_snapshots()`,
  `answers::is_answer()`); the one inlined query lists the learner's approved records of the course
  by `courseid`, `userid` and `status`, the record's natural key. Keep it that narrow: every other
  column of that table has a rule in `enrol_apply`'s `CLAUDE.md`.
- **Values are printed as `enrol_apply` froze them**, which is display text in the applicant's
  language: "Yes"/"No" for a checkbox, the option's label for a menu, a long date for a date. That
  is the automatic detection: there is nothing to configure per type, and nothing to reformat,
  because the raw value is not kept. A date format chooser needs `enrol_apply` to keep it.
- **They are PLAIN text and `render_content()` writes HTML**: every value goes through `s()`.
- **Only the short names the template asks for are frozen, each present even when empty.** Free
  text is personal data, so nothing the certificate does not print is copied. A present key is
  final (empty means "no answer when issued"); an absent one (an older issue, an element added
  later) is read live from the applications, which the retention sweep of `enrol_apply` may have
  deleted by then.
- **Nothing exports the copy, and two cleanups do not reach it** (README, "Data stored"):
  `tool_certificate`'s provider exports custom fields, never `issues.data`; `enrol_apply`'s
  retention and a user account deletion leave the issue (a privacy deletion request removes it
  through `tool_certificate`'s provider). A provider of this plugin's own that
  exported and cleared the key would close it; the cleaner place is the certificate fork's provider.
  Open, the owner's call.
- **The first PDF is not always built from the frozen copy.** `issue_certificate()` releases the
  issuer's lock before the event, so a concurrent view of the activity can build the file first,
  and an issuer may build the file before it triggers the event: `render()` then reads live,
  which is the same answer at that moment. A regeneration that loaded the row before the observer
  wrote and saves after it drops the key (`create_issue_file()` writes its in-memory `data` back).
- **With several approved applications in a course the newest one that answered wins**, per short
  name: two methods are two intakes with questions of their own.
- **The newest approved record is used whatever the enrolment it led to became.** Its
  `userenrolmentid` is a reference that dangles by design (a restore writes 0), so it cannot say
  which record is behind the enrolment in force.

## Testing notes

- Applications are built with `enrol_apply`'s own writers (`submission::create()` is typed on a
  form object; the tests insert the record and call `submission::write_answers()`), and the
  questions with core's custom field generator on the `enrol_apply` / `application` area.
- Issue through the certificate tool's generator with a course id, after `redirectMessages()`.
- The PDF is asserted through a mock of `\pdf::writeHTMLCell()`, fifth argument.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
