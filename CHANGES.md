# Changelog

All notable changes to the Ascend Rewards plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.9] - 2026-02-12

### Fixed
- Rewards instructions link now opens `instructions.php` instead of removed legacy `html/index.html`
- Unlock journey modal copy finalized for hero -> pet -> villain -> storybook progression

### Changed
- Release metadata updated for Moodle/GitHub submission (`$plugin->release = 1.2.9`)

## [1.2.8] - 2026-02-10

### Fixed
- Rebuild badge cache task: PostgreSQL-compatible query ordering
- Rebuild badge cache task: use recordset to avoid duplicate-key debug warnings
- Admin dashboard rendering and navigation regressions
- Repeatable badge awards allowed for selected badge types
- Badge notification modal wiring and AMD usage

### Changed
- Migrated legacy HTML to Mustache templates / Output API
- Migrated legacy JS to AMD modules
- Added required GPL headers to all templates
- Standardized user-facing strings to `get_string()`

## [1.2.3] - 2026-02-02

### Fixed
- **CRITICAL**: Container width issue - plugin now uses standard Moodle `.container` class for responsive layout
- Removed custom width overrides that caused layout conflicts with theme
- Fixed position of congratulations banner video in card layout
- Verified CSRF protection on all POST requests

### Added
- Comprehensive Moodle submission checklist (`MOODLE_SUBMISSION_CHECKLIST.md`)
- Compliance fixes documentation (`COMPLIANCE_FIXES.md`)
- Submission readiness guide (`SUBMISSION_READY.md`)
- Copyright and license metadata to version.php

### Changed
- Updated version format to YYYYMMDDNN standard
- Documented unused legacy language strings (backwards compatible)
- Enhanced security validation documentation

### Security
- Verified all input validation with `optional_param()` / `required_param()`
- Confirmed `require_sesskey()` on all POST handlers
- Validated XSS prevention on all user-facing output
- Confirmed SQL injection prevention with prepared statements

## [1.2.2] - 2026-01-30

### Fixed
- Code standards compliance issues
- Missing docblock documentation
- Trailing whitespace removal

### Changed
- Improved badge notification modal animations
- Enhanced mystery box visual presentation

## [1.2.1] - 2025-12-10

### Changed
- Streamlined plugin to DEMO version with 7 active badges
- Reduced avatars to 2 complete sets (Elf/Lynx/Dryad and Imp/Hamster/Mole)
- Removed inactive badge awarding logic (On a Roll, Level Up, Early Bird, Deadline Burner, Sharp Shooter, Activity Ace, Mission Complete, High Flyer, Learning Legend)
- Optimized pet catalog to 2 active pets (Lynx, Hamster)
- Optimized villain catalog to 2 active villains (Dryad, Mole)
- Prepared plugin for Moodle plugins directory submission

### Added
- README.md with comprehensive documentation
- CHANGES.md changelog file
- Proper GPL license headers on all files
- Enhanced privacy API implementation
- thirdpartylibs.xml for vendor dependencies

### Fixed
- Code standards compliance with Moodle coding style
- PHPDoc blocks on all classes and methods
- Proper namespace declarations
- Database schema documentation

## [1.2.0] - 2025-12-01

### Added
- Weekly gameboard tracking system
- Badge cache warming for improved performance
- Performance caching layer for badge checks
- Hook callbacks for output customization
- Task for rebuilding badge activity cache

### Changed
- Improved badge awarding logic efficiency
- Enhanced database indexing for better performance
- Updated privacy provider for GDPR compliance

## [1.1.0] - 2025-11-15

### Added
- Mystery box reward system
- Villain unlock functionality
- Pet adoption system
- Avatar selection and unlocking
- Store interface for purchasing rewards

### Changed
- Refactored coin and XP awarding logic
- Improved leaderboard calculations
- Enhanced navigation integration

## [1.0.0] - 2025-11-01

### Added
- Initial release
- Seven core badge types (Getting Started, Halfway Hero, Master Navigator, Feedback Follower, Steady Improver, Tenacious Tiger, Glory Guide)
- Coin and XP economy system
- Basic leaderboard functionality
- Admin dashboard and badge management
- Privacy API implementation
- Moodle 4.0+ compatibility
