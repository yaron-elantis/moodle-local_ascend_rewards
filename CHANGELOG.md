# Changelog

## 1.2.11 - 2026-02-13

- Fixed scheduled task `\local_ascend_rewards\task\rebuild_badge_cache` to be DB-agnostic.
- Removed PostgreSQL/MariaDB cron failures caused by `ORDER BY RAND()` and invalid random SQL handling.
- Added resilient fallback ordering and per-entry exception isolation during cache verification.
- Added Moodle GPL header/license blocks to plugin JS source files for compliance feedback.
- Revalidated plugin with Moodle codechecker and successful scheduled task execution.

## 1.2.10 - 2026-02-12

- Moodle codechecker compliance fixes validated in host and container.
- Repaired corrupted AMD JS source patterns that caused Grunt/JS parser failures.
- Rebuilt AMD build artifacts from corrected sources.
- Added unlock journey messaging continuity:
  - Pet unlock success now prompts villain reveal.
  - Villain unlock success now prompts storybook reveal.
- Set this release as the current Moodle evaluation baseline.
