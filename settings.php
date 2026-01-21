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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ascend_rewards', get_string('pluginname', 'local_ascend_rewards'));

    // === DEFAULT COIN VALUES FOR BADGES (on first install) ===
    $settings->add(new admin_setting_heading(
        'coins_header',
        get_string('coins_settings', 'local_ascend_rewards'),
        get_string('coins_settings_desc', 'local_ascend_rewards')
    ));

    // Seed defaults from coin_map on first install
    $defaults = \local_ascend_rewards\coin_map::defaults();

    $badgelabels = [
        6 => 'Getting Started',
        4 => 'On a Roll',
        5 => 'Halfway Hero',
        8 => 'Master Navigator',
        9 => 'Early Bird',
        11 => 'Sharp Shooter',
        10 => 'Deadline Burner',
        12 => 'Time Tamer',
        13 => 'Feedback Follower',
        15 => 'Steady Improver',
        14 => 'Tenacious Tiger',
        16 => 'Glory Guide',
        19 => 'High Flyer',
        17 => 'Assessment Ace',
        7 => 'Mission Complete',
        20 => 'Learning Legend',
    ];

    foreach ($badgelabels as $badgeid => $label) {
        $settings->add(new admin_setting_configtext(
            "local_ascend_rewards/coins_badge_{$badgeid}",
            $label . ' — default coins',
            'Default Ascend Assets awarded for this badge (used on install; can be changed anytime).',
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