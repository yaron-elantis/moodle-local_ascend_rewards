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
 * External service definitions for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External API implementation.
 */
class external extends external_api {
    /**
     * Parameters for get_activities.
     *
     * @return external_function_parameters
     */
    public static function get_activities_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'badgeid' => new external_value(PARAM_INT, 'Badge id'),
            'force' => new external_value(PARAM_BOOL, 'Force recalculation', VALUE_DEFAULT, 0),
            'debug' => new external_value(PARAM_BOOL, 'Include timing data', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get activities contributing to a badge.
     *
     * @param int $courseid
     * @param int $badgeid
     * @param bool $force
     * @param bool $debug
     * @return array
     */
    public static function get_activities(
        int $courseid,
        int $badgeid,
        bool $force = false,
        bool $debug = false
    ): array {
        $params = self::validate_parameters(self::get_activities_parameters(), [
            'courseid' => $courseid,
            'badgeid' => $badgeid,
            'force' => $force,
            'debug' => $debug,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::get_activities(
                $params['courseid'],
                $params['badgeid'],
                (bool)$params['force'],
                (bool)$params['debug']
            );
        } catch (\Throwable $e) {
            return [
                'activities' => [],
                'metadata' => [],
                'cached' => false,
            ];
        }
    }

    /**
     * Returns for get_activities.
     *
     * @return external_single_structure
     */
    public static function get_activities_returns(): external_single_structure {
        $metadata = new external_multiple_structure(
            new external_single_structure([
                'award_number' => new external_value(PARAM_INT, 'Award number', VALUE_OPTIONAL),
                'award_count' => new external_value(PARAM_INT, 'Award count', VALUE_OPTIONAL),
                'is_pair_member' => new external_value(PARAM_BOOL, 'Is pair member', VALUE_OPTIONAL),
                'single_activity' => new external_value(PARAM_BOOL, 'Single activity', VALUE_OPTIONAL),
                'old_grade' => new external_value(PARAM_INT, 'Old grade', VALUE_OPTIONAL),
                'new_grade' => new external_value(PARAM_INT, 'New grade', VALUE_OPTIONAL),
                'failed_grade' => new external_value(PARAM_INT, 'Failed grade', VALUE_OPTIONAL),
                'passed_grade' => new external_value(PARAM_INT, 'Passed grade', VALUE_OPTIONAL),
            ])
        );

        $performance = new external_single_structure([
            'init' => new external_value(PARAM_FLOAT, 'Init time in ms', VALUE_OPTIONAL),
            'cache_check' => new external_value(PARAM_FLOAT, 'Cache check time in ms', VALUE_OPTIONAL),
            'decode' => new external_value(PARAM_FLOAT, 'Decode time in ms', VALUE_OPTIONAL),
            'calculation' => new external_value(PARAM_FLOAT, 'Calculation time in ms', VALUE_OPTIONAL),
            'total' => new external_value(PARAM_FLOAT, 'Total time in ms', VALUE_OPTIONAL),
        ], 'Performance metrics', VALUE_OPTIONAL);

        return new external_single_structure([
            'activities' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Activity or badge name')),
            'metadata' => $metadata,
            'cached' => new external_value(PARAM_BOOL, 'Whether result was cached'),
            'performance' => $performance,
        ]);
    }
    /**
     * Parameters for get_leaderboard_context.
     *
     * @return external_function_parameters
     */
    public static function get_leaderboard_context_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_DEFAULT, 0),
            'neighbors' => new external_value(PARAM_INT, 'Neighbor count', VALUE_DEFAULT, 3),
        ]);
    }

    /**
     * Get leaderboard context around the current user.
     *
     * @param int $courseid
     * @param int $neighbors
     * @return array
     */
    public static function get_leaderboard_context(int $courseid = 0, int $neighbors = 3): array {
        $params = self::validate_parameters(self::get_leaderboard_context_parameters(), [
            'courseid' => $courseid,
            'neighbors' => $neighbors,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::get_leaderboard_context(
                $params['courseid'],
                $params['neighbors']
            );
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'users' => [],
                'start_rank' => 0,
                'end_rank' => 0,
                'total_users' => 0,
                'myrank' => 0,
            ];
        }
    }

    /**
     * Returns for get_leaderboard_context.
     *
     * @return external_single_structure
     */
    public static function get_leaderboard_context_returns(): external_single_structure {
        $user = new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'xp' => new external_value(PARAM_INT, 'XP total'),
            'rank' => new external_value(PARAM_INT, 'Rank position'),
            'medal' => new external_value(PARAM_TEXT, 'Medal label'),
            'is_current_user' => new external_value(PARAM_BOOL, 'Is current user'),
        ]);

        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'users' => new external_multiple_structure($user),
            'start_rank' => new external_value(PARAM_INT, 'Start rank'),
            'end_rank' => new external_value(PARAM_INT, 'End rank'),
            'total_users' => new external_value(PARAM_INT, 'Total users'),
            'myrank' => new external_value(PARAM_INT, 'Current user rank'),
        ]);
    }
    /**
     * Parameters for gameboard_pick.
     *
     * @return external_function_parameters
     */
    public static function gameboard_pick_parameters(): external_function_parameters {
        return new external_function_parameters([
            'position' => new external_value(PARAM_INT, 'Card position'),
        ]);
    }

    /**
     * Process a gameboard pick.
     *
     * @param int $position
     * @return array
     */
    public static function gameboard_pick(int $position): array {
        $params = self::validate_parameters(self::gameboard_pick_parameters(), [
            'position' => $position,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::gameboard_pick($params['position']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for gameboard_pick.
     *
     * @return external_single_structure
     */
    public static function gameboard_pick_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'coins' => new external_value(PARAM_INT, 'Coins awarded', VALUE_OPTIONAL),
            'remaining' => new external_value(PARAM_INT, 'Remaining picks', VALUE_OPTIONAL),
            'new_balance' => new external_value(PARAM_INT, 'New coin balance', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Parameters for store_purchase.
     *
     * @return external_function_parameters
     */
    public static function store_purchase_parameters(): external_function_parameters {
        return new external_function_parameters([
            'item_id' => new external_value(PARAM_INT, 'Store item id'),
        ]);
    }

    /**
     * Purchase a store item.
     *
     * @param int $item_id
     * @return array
     */
    public static function store_purchase(int $item_id): array {
        $params = self::validate_parameters(self::store_purchase_parameters(), [
            'item_id' => $item_id,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::store_purchase($params['item_id']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for store_purchase.
     *
     * @return external_single_structure
     */
    public static function store_purchase_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'remaining_coins' => new external_value(PARAM_INT, 'Remaining coins', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Parameters for store_activate.
     *
     * @return external_function_parameters
     */
    public static function store_activate_parameters(): external_function_parameters {
        return new external_function_parameters([
            'item_id' => new external_value(PARAM_INT, 'Store item id'),
        ]);
    }

    /**
     * Activate a store item.
     *
     * @param int $item_id
     * @return array
     */
    public static function store_activate(int $item_id): array {
        $params = self::validate_parameters(self::store_activate_parameters(), [
            'item_id' => $item_id,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::store_activate($params['item_id']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for store_activate.
     *
     * @return external_single_structure
     */
    public static function store_activate_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'expires_at' => new external_value(PARAM_INT, 'Expiration timestamp', VALUE_OPTIONAL),
            'inventory_count' => new external_value(PARAM_INT, 'Remaining inventory', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
    /**
     * Parameters for mysterybox_open.
     *
     * @return external_function_parameters
     */
    public static function mysterybox_open_parameters(): external_function_parameters {
        return new external_function_parameters([
            'price' => new external_value(PARAM_INT, 'Mystery box price', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * Open a mystery box.
     *
     * @param int $price
     * @return array
     */
    public static function mysterybox_open(int $price = 50): array {
        $params = self::validate_parameters(self::mysterybox_open_parameters(), [
            'price' => $price,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::mysterybox_open($params['price']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for mysterybox_open.
     *
     * @return external_single_structure
     */
    public static function mysterybox_open_returns(): external_single_structure {
        $reward_data = new external_single_structure([
            'avatar_filename' => new external_value(PARAM_TEXT, 'Avatar filename', VALUE_OPTIONAL),
            'avatar_level' => new external_value(PARAM_INT, 'Avatar level', VALUE_OPTIONAL),
        ], 'Reward data', VALUE_OPTIONAL);

        $debug = new external_single_structure([
            'user_level' => new external_value(PARAM_INT, 'User level', VALUE_OPTIONAL),
            'user_xp' => new external_value(PARAM_INT, 'User XP', VALUE_OPTIONAL),
            'max_level' => new external_value(PARAM_INT, 'Max level', VALUE_OPTIONAL),
            'avatar_pool_size' => new external_value(PARAM_INT, 'Avatar pool size', VALUE_OPTIONAL),
        ], 'Debug data', VALUE_OPTIONAL);

        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'box_number' => new external_value(PARAM_INT, 'Box number', VALUE_OPTIONAL),
            'reward_type' => new external_value(PARAM_TEXT, 'Reward type', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'new_balance' => new external_value(PARAM_INT, 'New coin balance', VALUE_OPTIONAL),
            'total_tokens' => new external_value(PARAM_INT, 'Total tokens available', VALUE_OPTIONAL),
            'balance' => new external_value(PARAM_INT, 'Balance when insufficient', VALUE_OPTIONAL),
            'reward_data' => $reward_data,
            'debug' => $debug,
        ]);
    }
    /**
     * Parameters for avatar_unlock.
     *
     * @return external_function_parameters
     */
    public static function avatar_unlock_parameters(): external_function_parameters {
        return new external_function_parameters([
            'avatar' => new external_value(PARAM_TEXT, 'Avatar filename'),
            'level' => new external_value(PARAM_INT, 'Avatar level'),
        ]);
    }

    /**
     * Unlock an avatar.
     *
     * @param string $avatar
     * @param int $level
     * @return array
     */
    public static function avatar_unlock(string $avatar, int $level): array {
        $params = self::validate_parameters(self::avatar_unlock_parameters(), [
            'avatar' => $avatar,
            'level' => $level,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::avatar_unlock($params['avatar'], $params['level']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for avatar_unlock.
     *
     * @return external_single_structure
     */
    public static function avatar_unlock_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'tokens_remaining' => new external_value(PARAM_INT, 'Tokens remaining', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Parameters for pet_unlock.
     *
     * @return external_function_parameters
     */
    public static function pet_unlock_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pet_id' => new external_value(PARAM_INT, 'Pet id'),
            'unlock_type' => new external_value(PARAM_TEXT, 'Unlock type'),
        ]);
    }

    /**
     * Unlock a pet.
     *
     * @param int $pet_id
     * @param string $unlock_type
     * @return array
     */
    public static function pet_unlock(int $pet_id, string $unlock_type): array {
        $params = self::validate_parameters(self::pet_unlock_parameters(), [
            'pet_id' => $pet_id,
            'unlock_type' => $unlock_type,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::pet_unlock($params['pet_id'], $params['unlock_type']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for pet_unlock.
     *
     * @return external_single_structure
     */
    public static function pet_unlock_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'new_balance' => new external_value(PARAM_INT, 'New coin balance', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Parameters for villain_unlock.
     *
     * @return external_function_parameters
     */
    public static function villain_unlock_parameters(): external_function_parameters {
        return new external_function_parameters([
            'villain_id' => new external_value(PARAM_INT, 'Villain id'),
            'unlock_type' => new external_value(PARAM_TEXT, 'Unlock type'),
        ]);
    }

    /**
     * Unlock a villain.
     *
     * @param int $villain_id
     * @param string $unlock_type
     * @return array
     */
    public static function villain_unlock(int $villain_id, string $unlock_type): array {
        $params = self::validate_parameters(self::villain_unlock_parameters(), [
            'villain_id' => $villain_id,
            'unlock_type' => $unlock_type,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        try {
            return ajax_service::villain_unlock($params['villain_id'], $params['unlock_type']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns for villain_unlock.
     *
     * @return external_single_structure
     */
    public static function villain_unlock_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'new_balance' => new external_value(PARAM_INT, 'New coin balance', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
    /**
     * Parameters for check_notifications.
     *
     * @return external_function_parameters
     */
    public static function check_notifications_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Check if more badge notifications remain.
     *
     * @return array
     */
    public static function check_notifications(): array {
        self::validate_parameters(self::check_notifications_parameters(), []);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        return ajax_service::check_notifications();
    }

    /**
     * Returns for check_notifications.
     *
     * @return external_single_structure
     */
    public static function check_notifications_returns(): external_single_structure {
        return new external_single_structure([
            'has_more' => new external_value(PARAM_BOOL, 'Has more notifications'),
        ]);
    }

    /**
     * Parameters for check_levelups.
     *
     * @return external_function_parameters
     */
    public static function check_levelups_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Check if more level-up notifications remain.
     *
     * @return array
     */
    public static function check_levelups(): array {
        self::validate_parameters(self::check_levelups_parameters(), []);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ascend_rewards:view', $context);

        return ajax_service::check_levelups();
    }

    /**
     * Returns for check_levelups.
     *
     * @return external_single_structure
     */
    public static function check_levelups_returns(): external_single_structure {
        return new external_single_structure([
            'has_more' => new external_value(PARAM_BOOL, 'Has more level-ups'),
        ]);
    }
}
