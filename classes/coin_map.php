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

/**
 * Default coin mapping for badge rewards.
 *
 * Provides default coin values used on plugin installation.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Preserve existing comment separators and mapping annotations.
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong

/**
 * Default coin mapping (Badge ID -> Coins).
 *
 * These are defaults used by the admin console on first install.
 * Live values can be overridden via plugin settings.
 */
final class coin_map {
    /** @var array<int,int> */
    private const MAP = [
        // Exact values provided by the user (10 Nov 2025)
        // Getting Started = 250 ; On a Roll = 150; Halfway Hero = 550; Master Navigator = 700; Early Bird = 100;
        // Sharp Shooter = 200; Deadline Burner = 300; Time Tamer = 600; Feedback Follower = 200; Steady Improver = 300;
        // Tenacious Tiger = 350; Glory Guide = 600; High Flyer = 300; Assessment Ace = 400; Mission Complete = 500; Learning Legend = 1000.

        // Progress-Based (base):
        6  => 250, // Getting Started
        4  => 150, // On a Roll
        5  => 550, // Halfway Hero
        // Progress-Based (meta):
        8  => 700, // Master Navigator

        // Timeliness & Discipline (base):
        9  => 100, // Early Bird
        11 => 200, // Sharp Shooter
        10 => 300, // Deadline Burner
        // Timeliness & Discipline (meta):
        12 => 600, // Time Tamer

        // Quality & Growth (base):
        13 => 200, // Feedback Follower
        15 => 300, // Steady Improver
        14 => 350, // Tenacious Tiger
        // Quality & Growth (meta):
        16 => 600, // Glory Guide

        // Course Mastery (base):
        19 => 300, // High Flyer
        17 => 400, // Assessment Ace
        7  => 500, // Mission Complete (ensure this is in Course Mastery)
        // Course Mastery (meta):
        20 => 1000, // Learning Legend
    ];

    /**
     * Return default coins for a badge id (used as install defaults).
     */
    public static function coins_for_badge(int $badgeid): int {
        return self::MAP[$badgeid] ?? 0;
    }

    /**
     * Expose full defaults as array.
     * @return array<int,int>
     */
    public static function defaults(): array {
        return self::MAP;
    }
}
