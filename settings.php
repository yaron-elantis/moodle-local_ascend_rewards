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
 * Plugin settings for Ascend Rewards.
 *
 * Defines admin settings for default coin values and admin pages.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Commenting.InlineComment.NotCapital
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ascend_rewards', get_string('pluginname', 'local_ascend_rewards'));

    // === DEFAULT COIN VALUES FOR BADGES (on first install) ===
    $settings->add(new admin_setting_heading(
        'coins_header',
        get_string('coins_settings', 'local_ascend_rewards'),
        get_string('coins_settings_desc', 'local_ascend_rewards')
    ));

    // Seed defaults from coin_map on first install.
    $defaults = \local_ascend_rewards\coin_map::defaults();

    // Only active badges in DEMO version.
    $badgelabels = [
        6  => get_string('badge_name_getting_started', 'local_ascend_rewards'),
        5  => get_string('badge_name_halfway_hero', 'local_ascend_rewards'),
        8  => get_string('badge_name_master_navigator', 'local_ascend_rewards'),
        13 => get_string('badge_name_feedback_follower', 'local_ascend_rewards'),
        15 => get_string('badge_name_steady_improver', 'local_ascend_rewards'),
        14 => get_string('badge_name_tenacious_tiger', 'local_ascend_rewards'),
        16 => get_string('badge_name_glory_guide', 'local_ascend_rewards'),
    ];

    foreach ($badgelabels as $badgeid => $label) {
        $settings->add(new admin_setting_configtext(
            "local_ascend_rewards/coins_badge_{$badgeid}",
            get_string('default_coins_label', 'local_ascend_rewards', $label),
            get_string('default_coins_desc', 'local_ascend_rewards'),
            (int)($defaults[$badgeid] ?? 0),
            PARAM_INT
        ));
    }

    // === ADD TO ADMIN MENU ===
    $ADMIN->add('localplugins', $settings);

    // === ADD ADMIN DASHBOARD PAGE ===
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ascend_rewards_dashboard',
        get_string('admin_dashboard', 'local_ascend_rewards'),
        new moodle_url('/local/ascend_rewards/admin_dashboard.php'),
        'moodle/site:config'
    ));

    // === ADD AUDIT TRAIL PAGE ===
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ascend_rewards_audit',
        get_string('audit_trail', 'local_ascend_rewards'),
        new moodle_url('/local/ascend_rewards/admin_audit.php'),
        'moodle/site:config'
    ));
}
