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
 * External service definitions for Ascend Rewards AJAX calls.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ascend_rewards_get_activities' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'get_activities',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Return activities contributing to a badge.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_get_leaderboard_context' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'get_leaderboard_context',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Return leaderboard context around the current user.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_gameboard_pick' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'gameboard_pick',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Process a gameboard pick and return the result.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_store_purchase' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'store_purchase',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Purchase a store item with coins.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_store_activate' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'store_activate',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Activate a store item from inventory.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_mysterybox_open' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'mysterybox_open',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Open a mystery box and return the reward.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_avatar_unlock' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'avatar_unlock',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Unlock an avatar using a token.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_pet_unlock' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'pet_unlock',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Unlock a pet using a token or coins.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_villain_unlock' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'villain_unlock',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Unlock a villain using a token or coins.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_check_notifications' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'check_notifications',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Check if more badge notifications are pending.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
    'local_ascend_rewards_check_levelups' => [
        'classname' => 'local_ascend_rewards\\external',
        'methodname' => 'check_levelups',
        'classpath' => 'local/ascend_rewards/classes/external.php',
        'description' => 'Check if more level-up notifications are pending.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/ascend_rewards:view',
    ],
];
