# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- The "Enrolment application answer" certificate element: the learner's answer to one question of
  their approved `enrol_apply` application, named by the question's short name. The answers a
  template prints are stored with the certificate issue when it is created (version 2026100500).

### Changed

- A real privacy provider replaces the null provider (version 2026100501): a data request exports
  the copy of the answers stored with the certificate issue, and a deletion request removes it from
  the issue's data, leaving the rest of the data in place.
- The help of the "Question short name" field says the printed answer is read by everybody who can
  see the certificate or verify it with its code.
