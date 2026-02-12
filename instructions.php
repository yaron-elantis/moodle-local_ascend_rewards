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
 * Instructions page for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/ascend_rewards:view', $context);

$pageurl = new moodle_url('/local/ascend_rewards/instructions.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('instructions_page_title', 'local_ascend_rewards'));
$PAGE->set_heading(get_string('instructions_page_heading', 'local_ascend_rewards'));

$coindefaults = [
    6 => 250,
    5 => 550,
    8 => 700,
    13 => 200,
    15 => 300,
    14 => 350,
    16 => 600,
];

$badgecoins = function (int $badgeid) use ($coindefaults): int {
    $configkey = 'coins_badge_' . $badgeid;
    $value = get_config('local_ascend_rewards', $configkey);
    if ($value === false || $value === null || $value === '') {
        return $coindefaults[$badgeid] ?? 0;
    }
    return (int)$value;
};

$badgemap = [
    6 => [
        'namekey' => 'badge_name_getting_started',
        'desckey' => 'instructions_badge_desc_getting_started',
        'image' => 'getting_started.png',
    ],
    5 => [
        'namekey' => 'badge_name_halfway_hero',
        'desckey' => 'instructions_badge_desc_halfway_hero',
        'image' => 'halfway_hero.png',
    ],
    8 => [
        'namekey' => 'badge_name_master_navigator',
        'desckey' => 'instructions_badge_desc_master_navigator',
        'image' => 'master_navigator.png',
    ],
    13 => [
        'namekey' => 'badge_name_feedback_follower',
        'desckey' => 'instructions_badge_desc_feedback_follower',
        'image' => 'feedback_follower.png',
    ],
    15 => [
        'namekey' => 'badge_name_steady_improver',
        'desckey' => 'instructions_badge_desc_steady_improver',
        'image' => 'steady_improver.png',
    ],
    14 => [
        'namekey' => 'badge_name_tenacious_tiger',
        'desckey' => 'instructions_badge_desc_tenacious_tiger',
        'image' => 'tenacious_tiger.png',
    ],
    16 => [
        'namekey' => 'badge_name_glory_guide',
        'desckey' => 'instructions_badge_desc_glory_guide',
        'image' => 'glory_guide.png',
    ],
];

$badgelist = function (array $badgeids) use ($badgecoins, $badgemap): array {
    $items = [];
    foreach ($badgeids as $badgeid) {
        if (!isset($badgemap[$badgeid])) {
            continue;
        }
        $map = $badgemap[$badgeid];
        $items[] = [
            'name' => get_string($map['namekey'], 'local_ascend_rewards'),
            'coins' => $badgecoins($badgeid),
            'xp' => \local_ascend_rewards\badge_awarder::xp_for_badge($badgeid),
            'description' => get_string($map['desckey'], 'local_ascend_rewards'),
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/' . $map['image']))->out(false),
        ];
    }
    return $items;
};

$templatecontext = [
    'title' => get_string('instructions_page_heading', 'local_ascend_rewards'),
    'intro' => get_string('instructions_intro', 'local_ascend_rewards'),
    'icon_url' => (new moodle_url('/local/ascend_rewards/pix/instructions.png'))->out(false),
    'badge_groups_heading' => get_string('instructions_badge_groups_heading', 'local_ascend_rewards'),
    'badge_groups' => [
        [
            'title' => get_string('instructions_badge_group_progress', 'local_ascend_rewards'),
            'description' => get_string('instructions_badge_group_progress_desc', 'local_ascend_rewards'),
            'badges' => $badgelist([6, 5]),
        ],
        [
            'title' => get_string('instructions_badge_group_quality', 'local_ascend_rewards'),
            'description' => get_string('instructions_badge_group_quality_desc', 'local_ascend_rewards'),
            'badges' => $badgelist([13, 15, 14]),
        ],
        [
            'title' => get_string('instructions_badge_group_meta', 'local_ascend_rewards'),
            'description' => get_string('instructions_badge_group_meta_desc', 'local_ascend_rewards'),
            'badges' => $badgelist([16, 8]),
        ],
    ],
    'coins_label' => get_string('coins_label', 'local_ascend_rewards'),
    'xp_label' => get_string('xp_label', 'local_ascend_rewards'),
    'sections' => [
        [
            'heading' => get_string('instructions_rewards_heading', 'local_ascend_rewards'),
            'items' => [
                ['text' => get_string('instructions_rewards_coins', 'local_ascend_rewards')],
                ['text' => get_string('instructions_rewards_xp', 'local_ascend_rewards')],
                ['text' => get_string('instructions_rewards_store', 'local_ascend_rewards')],
            ],
        ],
        [
            'heading' => get_string('instructions_earn_heading', 'local_ascend_rewards'),
            'items' => [
                ['text' => get_string('instructions_earn_step1', 'local_ascend_rewards')],
                ['text' => get_string('instructions_earn_step2', 'local_ascend_rewards')],
                ['text' => get_string('instructions_earn_step3', 'local_ascend_rewards')],
            ],
        ],
    ],
    'features_heading' => get_string('instructions_features_heading', 'local_ascend_rewards'),
    'features' => [
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/badges_course.png'))->out(false),
            'title' => get_string('instructions_feature_badges_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_badges_desc', 'local_ascend_rewards'),
        ],
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/leaderboard.png'))->out(false),
            'title' => get_string('instructions_feature_leaderboard_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_leaderboard_desc', 'local_ascend_rewards'),
        ],
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/journey.png'))->out(false),
            'title' => get_string('instructions_feature_journey_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_journey_desc', 'local_ascend_rewards'),
        ],
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/gameboard.png'))->out(false),
            'title' => get_string('instructions_feature_gameboard_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_gameboard_desc', 'local_ascend_rewards'),
        ],
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false),
            'title' => get_string('instructions_feature_universe_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_universe_desc', 'local_ascend_rewards'),
        ],
        [
            'icon_url' => (new moodle_url('/local/ascend_rewards/pix/store.png'))->out(false),
            'title' => get_string('instructions_feature_store_title', 'local_ascend_rewards'),
            'description' => get_string('instructions_feature_store_desc', 'local_ascend_rewards'),
        ],
    ],
    'back_url' => (new moodle_url('/local/ascend_rewards/index.php'))->out(false),
    'back_label' => get_string('instructions_back_label', 'local_ascend_rewards'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_ascend_rewards/instructions', $templatecontext);
echo $OUTPUT->footer();
