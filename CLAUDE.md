# Project rules

## Workflow for work items

Each work item (one TODO entry or one coherent change) goes through this loop:

1. **Build**: delegate to an `opus-builder` agent. The builder:
   - makes the code changes,
   - checks off or updates the entry in `TODO.md`,
   - adds an entry under `## [Nevydáno]` in `CHANGELOG.md`.
2. **Review**: run a read-only review with a Fable agent (`sonnet-checker` with `model: fable`). It reads the diff and runs checks, and changes nothing.
3. **Repeat**: if the review is not clean, send its findings back to the same builder (SendMessage) and review again. A work item is done only when the review comes back clean.
4. **Release**: once the review is clean, the work item gets its own version:
   - bump `version` in `composer.json` (semver: bug fix or compatibility fix → patch, new feature → minor, breaking change → major) and set `time` to the release date (`YYYY-MM-DD`),
   - set `APP_VERSION` in `includes/config.php` to the same version,
   - in `TODO.md`, append the version and date to the checked-off item: `(vx.y.z, YYYY-MM-DD)`,
   - in `CHANGELOG.md`, rename `## [Nevydáno]` to `## [x.y.z] – YYYY-MM-DD` and start a new empty `## [Nevydáno]` above it,
   - commit the work item, then tag the commit `vx.y.z`.

Docs (`README.md`, `TODO.md`, `CHANGELOG.md`) are written in Czech.
