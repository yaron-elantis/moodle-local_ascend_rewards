<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Preserve constant naming and inline annotation comments.
// phpcs:disable moodle.Commenting.MissingDocblock.Constant
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded

/**
 * Central badge registry & category mapping.
 *
 * IMPORTANT: Keep these IDs aligned with your site's badge IDs.
 * (You can still let admins re-bind "portable" codes to site badges in the UI if you use that pattern.)
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class badges {
    // Category constants.
    public const CAT_PROGRESS   = 'progress';
    public const CAT_TIMELINESS = 'timeliness';
    public const CAT_QUALITY    = 'quality';
    public const CAT_MASTERY    = 'mastery';

    // Meta badges (awarded when any 2 base badges in their category are earned).
    public const META_MASTER_NAVIGATOR = 8;   // Progress meta
    public const META_TIME_TAMER       = 12;  // Timeliness meta
    public const META_GLORY_GUIDE      = 16;  // Quality meta
    public const META_LEARNING_LEGEND  = 20;  // Mastery meta

    // DEMO VERSION: Only 7 allowed badges
    // Getting Started (6), Halfway Hero (5), Master Navigator (8),
    // Feedback Follower (13), Steady Improver (15), Tenacious Tiger (14), Glory Guide (16)

    private const PROGRESS_BASE = [6, 5];                // Getting Started, Halfway Hero
    private const TIMELINESS_BASE = [];                  // DISABLED IN DEMO
    private const QUALITY_BASE = [13, 15, 14];           // Feedback Follower, Steady Improver, Tenacious Tiger
    private const MASTERY_BASE = [];                     // DISABLED IN DEMO

    // Meta badges per category.
    private const META_BY_CATEGORY = [
        self::CAT_PROGRESS   => self::META_MASTER_NAVIGATOR,
        self::CAT_TIMELINESS => null, // DISABLED
        self::CAT_QUALITY    => self::META_GLORY_GUIDE,
        self::CAT_MASTERY    => null, // DISABLED
    ];

    // Base badges per category.
    private const BASE_BY_CATEGORY = [
        self::CAT_PROGRESS   => self::PROGRESS_BASE,
        self::CAT_TIMELINESS => self::TIMELINESS_BASE,
        self::CAT_QUALITY    => self::QUALITY_BASE,
        self::CAT_MASTERY    => self::MASTERY_BASE,
    ];

    /**
     * Return base badge ids for a category.
     * @return int[]
     */
    public static function base_for_category(string $category): array {
        return self::BASE_BY_CATEGORY[$category] ?? [];
    }

    /**
     * Return the meta badge id for a category.
     */
    public static function meta_for_category(string $category): ?int {
        return self::META_BY_CATEGORY[$category] ?? null;
    }

    /**
     * All categories.
     * @return string[]
     */
    public static function categories(): array {
        return [self::CAT_PROGRESS, self::CAT_TIMELINESS, self::CAT_QUALITY, self::CAT_MASTERY];
    }

    /**
     * Is this badge a meta.
     */
    public static function is_meta(int $badgeid): bool {
        return in_array($badgeid, [
            self::META_MASTER_NAVIGATOR,
            self::META_TIME_TAMER,
            self::META_GLORY_GUIDE,
            self::META_LEARNING_LEGEND,
        ], true);
    }

    /**
     * Badge catalogue used by installers, settings seeding, and admin mapping UI.
     * Keyed by portable code (also used as badge idnumber when we create badges).
     * Each entry: ['badgeid' => int, 'name' => string, 'defaultcoins' => int].
     *
     * @return array<string, array{badgeid:int,name:string,defaultcoins:int}>
     */
    public static function definitions(): array {
        // Default coin values come from the central coin map.
        $coins = coin_map::defaults();

        return [
            'getting_started' => [
                'badgeid' => 6,
                'name' => 'Getting Started',
                'defaultcoins' => (int)($coins[6] ?? 0),
            ],
            'on_a_roll' => [
                'badgeid' => 4,
                'name' => 'On a Roll',
                'defaultcoins' => (int)($coins[4] ?? 0),
            ],
            'halfway_hero' => [
                'badgeid' => 5,
                'name' => 'Halfway Hero',
                'defaultcoins' => (int)($coins[5] ?? 0),
            ],
            'master_navigator' => [
                'badgeid' => 8,
                'name' => 'Master Navigator',
                'defaultcoins' => (int)($coins[8] ?? 0),
            ],
            'early_bird' => [
                'badgeid' => 9,
                'name' => 'Early Bird',
                'defaultcoins' => (int)($coins[9] ?? 0),
            ],
            'sharp_shooter' => [
                'badgeid' => 11,
                'name' => 'Sharp Shooter',
                'defaultcoins' => (int)($coins[11] ?? 0),
            ],
            'deadline_burner' => [
                'badgeid' => 10,
                'name' => 'Deadline Burner',
                'defaultcoins' => (int)($coins[10] ?? 0),
            ],
            'time_tamer' => [
                'badgeid' => 12,
                'name' => 'Time Tamer',
                'defaultcoins' => (int)($coins[12] ?? 0),
            ],
            'feedback_follower' => [
                'badgeid' => 13,
                'name' => 'Feedback Follower',
                'defaultcoins' => (int)($coins[13] ?? 0),
            ],
            'steady_improver' => [
                'badgeid' => 15,
                'name' => 'Steady Improver',
                'defaultcoins' => (int)($coins[15] ?? 0),
            ],
            'tenacious_tiger' => [
                'badgeid' => 14,
                'name' => 'Tenacious Tiger',
                'defaultcoins' => (int)($coins[14] ?? 0),
            ],
            'glory_guide' => [
                'badgeid' => 16,
                'name' => 'Glory Guide',
                'defaultcoins' => (int)($coins[16] ?? 0),
            ],
            'high_flyer' => [
                'badgeid' => 19,
                'name' => 'High Flyer',
                'defaultcoins' => (int)($coins[19] ?? 0),
            ],
            'assessment_ace' => [
                'badgeid' => 17,
                'name' => 'Assessment Ace',
                'defaultcoins' => (int)($coins[17] ?? 0),
            ],
            'mission_complete' => [
                'badgeid' => 7,
                'name' => 'Mission Complete',
                'defaultcoins' => (int)($coins[7] ?? 0),
            ],
            'learning_legend' => [
                'badgeid' => 20,
                'name' => 'Learning Legend',
                'defaultcoins' => (int)($coins[20] ?? 0),
            ],
        ];
    }

    /**
     * Portable codes for all known badges.
     * @return string[]
     */
    public static function codes(): array {
        return array_keys(self::definitions());
    }

    /**
     * Resolve mapped badgeid for a code/course scope.
     */
    public static function get_badgeid_by_code(string $code, int $courseid = 0, bool $fallback = true): int {
        global $DB;

        $rec = $DB->get_record('local_ascend_rewards_badges', ['code' => $code, 'courseid' => $courseid], 'badgeid', IGNORE_MISSING);
        if ($rec && !empty($rec->badgeid)) {
            return (int)$rec->badgeid;
        }

        if ($fallback) {
            $any = $DB->get_record('local_ascend_rewards_badges', ['code' => $code], 'badgeid', IGNORE_MISSING);
            if ($any && !empty($any->badgeid)) {
                return (int)$any->badgeid;
            }
        }

        // As a last resort, try matching by badge idnumber (portable code).
        $badge = $DB->get_record('badge', ['idnumber' => $code], 'id', IGNORE_MISSING);
        return $badge ? (int)$badge->id : 0;
    }

    /**
     * Ensure a badge mapping row exists for this code/course (no-op if missing badge).
     */
    public static function ensure_badge_created(string $code, int $courseid = 0): void {
        global $DB;

        $defs = self::definitions();
        if (!isset($defs[$code])) {
            return; // Unknown code; nothing to do.
        }

        $def = $defs[$code];

        // Prefer an existing badge with matching idnumber; otherwise fall back to the suggested badgeid if present.
        $badgeid = 0;
        $existing = $DB->get_record('badge', ['idnumber' => $code], 'id', IGNORE_MISSING);
        if ($existing) {
            $badgeid = (int)$existing->id;
        } else if ($DB->record_exists('badge', ['id' => $def['badgeid']])) {
            $badgeid = (int)$def['badgeid'];
        }

        if ($badgeid === 0) {
            return; // Do not create badges automatically in upgrade; admin can map later.
        }

        $record = $DB->get_record('local_ascend_rewards_badges', ['code' => $code, 'courseid' => $courseid], '*', IGNORE_MISSING);
        $now = time();
        if ($record) {
            if ((int)$record->badgeid !== $badgeid) {
                $record->badgeid = $badgeid;
                $record->timemodified = $now;
                $DB->update_record('local_ascend_rewards_badges', $record);
            }
            return;
        }

        $DB->insert_record('local_ascend_rewards_badges', (object)[
            'code' => $code,
            'badgeid' => $badgeid,
            'courseid' => $courseid,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
