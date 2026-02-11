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
 * AJAX service helpers for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Preserve legacy naming and inline comments in service helpers.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/**
 * Service helpers for AJAX endpoints and external functions.
 */
class ajax_service {
    /**
     * Return badge activities by reusing the get_activities.php logic.
     *
     * @param int $courseid
     * @param int $badgeid
     * @param bool $force_recalculate
     * @param bool $debug_timing
     * @return array
     */
    public static function get_activities(int $courseid, int $badgeid, bool $force_recalculate = false, bool $debug_timing = false): array {
        $courseid = (int)$courseid;
        $badgeid = (int)$badgeid;
        $force_recalculate = (bool)$force_recalculate;
        $debug_timing = (bool)$debug_timing;
        $return_data = true;

        $result = (function () use ($courseid, $badgeid, $force_recalculate, $debug_timing, $return_data) {
            return require __DIR__ . '/../get_activities.php';
        })();

        if (!is_array($result)) {
            return [
                'activities' => [],
                'metadata' => [],
                'cached' => false,
            ];
        }

        if (!array_key_exists('cached', $result)) {
            $result['cached'] = false;
        }

        if (!isset($result['activities'])) {
            $result['activities'] = [];
        }

        if (!isset($result['metadata'])) {
            $result['metadata'] = [];
        }

        return $result;
    }
    /**
     * Return leaderboard context around the current user.
     *
     * @param int $courseid
     * @param int $neighbors
     * @return array
     */
    public static function get_leaderboard_context(int $courseid = 0, int $neighbors = 3): array {
        global $DB, $USER;

        $courseid = (int)$courseid;
        $neighbors = (int)$neighbors;
        if ($neighbors <= 0) {
            $neighbors = 3;
        }

        $xp_cid = ($courseid > 0) ? $courseid : 0;

        $sql = "SELECT x.userid, x.xp
                FROM {local_ascend_rewards_xp} x
                JOIN {user} u ON u.id = x.userid
                WHERE x.courseid = :cid AND x.xp > 0
                  AND u.suspended = 0 AND u.deleted = 0
                ORDER BY x.xp DESC, x.userid ASC";

        $rows = $DB->get_records_sql($sql, ['cid' => $xp_cid]);
        $ranked = [];
        $r = 1;
        foreach ($rows as $row) {
            $ranked[] = [
                'userid' => (int)$row->userid,
                'xp' => (int)$row->xp,
                'rank' => $r,
                'medal' => self::medal_for_place($r),
                'is_current_user' => ((int)$row->userid === (int)$USER->id),
            ];
            $r++;
        }

        $total = count($ranked);
        $myrank = null;
        foreach ($ranked as $r) {
            if ($r['userid'] == $USER->id) {
                $myrank = $r['rank'];
                break;
            }
        }

        $users_array = [];
        if ($myrank !== null && $total > 1) {
            $start = max(0, $myrank - 1 - $neighbors);
            $end = min($total - 1, $myrank - 1 + $neighbors);
            $users_array = array_slice($ranked, $start, $end - $start + 1);
            $start_rank = $start + 1;
            $end_rank = min($end + 1, $total);
        } else if ($myrank !== null) {
            $users_array = array_filter($ranked, function ($r) use ($myrank) {
                return $r['rank'] == $myrank;
            });
            $start_rank = $myrank;
            $end_rank = $myrank;
        }

        return [
            'success' => true,
            'users' => $users_array,
            'start_rank' => isset($start_rank) ? $start_rank : 0,
            'end_rank' => isset($end_rank) ? $end_rank : 0,
            'total_users' => $total,
            'myrank' => $myrank ?? 0,
        ];
    }

    /**
     * Process a gameboard pick.
     *
     * @param int $position
     * @return array
     */
    public static function gameboard_pick(int $position): array {
        global $USER;

        $position = (int)$position;
        if ($position < 0 || $position >= 16) {
            return [
                'success' => false,
                'error' => get_string('gameboard_invalid_position', 'local_ascend_rewards'),
            ];
        }

        try {
            $result = \local_ascend_rewards_gameboard::make_pick($USER->id, $position);

            if ($result['success']) {
                $coins_to_add = (int)$result['coins'];
                $current_balance = (int)get_user_preferences('ascend_coins_balance', 0, $USER->id);
                $new_balance = $current_balance + $coins_to_add;

                set_user_preference('ascend_coins_balance', $new_balance, $USER->id);

                debugging(
                    "ascend_rewards: user {$USER->id} picked {$position}, +{$coins_to_add} coins, balance={$new_balance}",
                    DEBUG_DEVELOPER,
                );

                return [
                    'success' => true,
                    'coins' => $coins_to_add,
                    'remaining' => $result['remaining'],
                    'new_balance' => $new_balance,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? get_string('gameboard_could_not_make_pick', 'local_ascend_rewards'),
            ];
        } catch (\Exception $e) {
            debugging('ascend_rewards gameboard_pick error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'error' => get_string('ajax_server_error_detail', 'local_ascend_rewards', $e->getMessage()),
            ];
        } catch (\Throwable $t) {
            debugging('ascend_rewards gameboard_pick fatal: ' . $t->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'error' => get_string('ajax_server_error', 'local_ascend_rewards'),
            ];
        }
    }
    /**
     * Purchase a store item.
     *
     * @param int $item_id
     * @return array
     */
    public static function store_purchase(int $item_id): array {
        global $DB, $USER;

        $item_id = (int)$item_id;

        $store_items = [
            4 => ['name' => get_string('xp_item_name', 'local_ascend_rewards'), 'price' => 250],
        ];

        if (!isset($store_items[$item_id])) {
            return [
                'success' => false,
                'error' => get_string('store_error_invalid_item', 'local_ascend_rewards'),
            ];
        }

        $item_price = $store_items[$item_id]['price'];

        $current_coins = (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = :uid",
            ['uid' => $USER->id]
        );

        if ($current_coins < $item_price) {
            return [
                'success' => false,
                'error' => get_string('store_error_not_enough_coins', 'local_ascend_rewards'),
            ];
        }

        $DB->insert_record('local_ascend_rewards_coins', (object)[
            'userid' => $USER->id,
            'coins' => -$item_price,
            'reason' => 'store_purchase_item_' . $item_id,
            'courseid' => 0,
            'timecreated' => time(),
        ]);

        $new_coins = (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = :uid",
            ['uid' => $USER->id]
        );

        $inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
        $inventory = $inventory_str ? json_decode($inventory_str, true) : [];

        if (!isset($inventory[$item_id])) {
            $inventory[$item_id] = 0;
        }
        $inventory[$item_id]++;

        set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

        if ($item_id == 4) {
            $current_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);

            if ($current_end > time()) {
                $new_end = $current_end + (24 * 60 * 60);
            } else {
                $new_end = time() + (24 * 60 * 60);
            }

            set_user_preference('ascend_xp_multiplier_end', $new_end, $USER->id);

            $inventory[$item_id]--;
            if ($inventory[$item_id] <= 0) {
                unset($inventory[$item_id]);
            }
            set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

            $activated_items_str = get_user_preferences('ascend_store_activated', '', $USER->id);
            $activated_items = $activated_items_str ? json_decode($activated_items_str, true) : [];

            if (!isset($activated_items[$item_id])) {
                $activated_items[$item_id] = [];
            }
            $activated_items[$item_id][] = [
                'activated_at' => time(),
                'expires_at' => $new_end,
            ];
            set_user_preference('ascend_store_activated', json_encode($activated_items), $USER->id);
        }

        return [
            'success' => true,
            'remaining_coins' => $new_coins,
        ];
    }

    /**
     * Activate a store item.
     *
     * @param int $item_id
     * @return array
     */
    public static function store_activate(int $item_id): array {
        global $USER;

        $item_id = (int)$item_id;

        $inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
        $inventory = $inventory_str ? json_decode($inventory_str, true) : [];

        if (!isset($inventory[$item_id]) || $inventory[$item_id] <= 0) {
            return [
                'success' => false,
                'error' => get_string('store_error_item_not_in_inventory', 'local_ascend_rewards'),
            ];
        }

        if ($item_id == 4) {
            $current_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);

            if ($current_end > time()) {
                $new_end = $current_end + (24 * 60 * 60);
            } else {
                $new_end = time() + (24 * 60 * 60);
            }

            set_user_preference('ascend_xp_multiplier_end', $new_end, $USER->id);

            $inventory[$item_id]--;
            if ($inventory[$item_id] <= 0) {
                unset($inventory[$item_id]);
            }
            set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

            $activated_items_str = get_user_preferences('ascend_store_activated', '', $USER->id);
            $activated_items = $activated_items_str ? json_decode($activated_items_str, true) : [];

            if (!isset($activated_items[$item_id])) {
                $activated_items[$item_id] = [];
            }
            $activated_items[$item_id][] = [
                'activated_at' => time(),
                'expires_at' => $new_end,
            ];
            set_user_preference('ascend_store_activated', json_encode($activated_items), $USER->id);

            return [
                'success' => true,
                'message' => get_string('store_activate_xp_multiplier_message', 'local_ascend_rewards'),
                'expires_at' => $new_end,
                'inventory_count' => isset($inventory[$item_id]) ? $inventory[$item_id] : 0,
            ];
        }

        return [
            'success' => false,
            'error' => get_string('store_error_unknown_item_type', 'local_ascend_rewards'),
        ];
    }
    /**
     * Unlock an avatar using a token.
     *
     * @param string $avatar
     * @param int $level
     * @return array
     */
    public static function avatar_unlock(string $avatar, int $level): array {
        global $DB, $USER;

        $avatar = (string)$avatar;
        $level = (int)$level;

        try {
            if (!$USER->id) {
                throw new \Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
            }

            $user_xp = (int)$DB->get_field('local_ascend_rewards_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
            $user_level = (int)($user_xp / 1000) + 1;
            if ($user_level > 8) {
                $user_level = 8;
            }

            if ($level > $user_level) {
                throw new \Exception(get_string('unlock_level_locked', 'local_ascend_rewards', $level));
            }

            $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'avatar_name' => $avatar,
                'pet_id' => null,
                'villain_id' => null,
            ]);

            if ($existing) {
                throw new \Exception(get_string('unlock_avatar_already_unlocked', 'local_ascend_rewards'));
            }

            $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
            if (!$token_record) {
                throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
            }

            $tokens_available = $token_record->tokens_available - $token_record->tokens_used;

            if ($tokens_available <= 0) {
                throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
            }

            $transaction = $DB->start_delegated_transaction();

            try {
                $unlock_record = new \stdClass();
                $unlock_record->userid = $USER->id;
                $unlock_record->avatar_name = $avatar;
                $unlock_record->avatar_level = $level;
                $unlock_record->pet_id = null;
                $unlock_record->villain_id = null;
                $unlock_record->unlock_type = 'token';
                $unlock_record->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlock_record);

                $token_record->tokens_used++;
                $token_record->timemodified = time();
                $DB->update_record('local_ascend_rewards_level_tokens', $token_record);

                $transaction->allow_commit();

                return [
                    'success' => true,
                    'message' => get_string('unlock_avatar_success', 'local_ascend_rewards'),
                    'tokens_remaining' => $token_record->tokens_available - $token_record->tokens_used,
                ];
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Unlock a pet using a token or coins.
     *
     * @param int $pet_id
     * @param string $unlock_type
     * @return array
     */
    public static function pet_unlock(int $pet_id, string $unlock_type): array {
        global $DB, $USER;

        $pet_id = (int)$pet_id;
        $unlock_type = (string)$unlock_type;

        try {
            if (!$USER->id) {
                throw new \Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
            }

            if (!in_array($unlock_type, ['token', 'coin'])) {
                throw new \Exception(get_string('unlock_invalid_type', 'local_ascend_rewards'));
            }

            $pet_catalog = [
                100 => ['name' => 'Lynx', 'avatar' => 'elf.png', 'level' => 1, 'price' => 300],
                102 => ['name' => 'Hamster', 'avatar' => 'imp.png', 'level' => 1, 'price' => 300],
            ];

            if (!isset($pet_catalog[$pet_id])) {
                throw new \Exception(get_string('unlock_pet_invalid_id', 'local_ascend_rewards'));
            }

            $pet_data = $pet_catalog[$pet_id];

            $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'pet_id' => $pet_id,
                'villain_id' => null,
            ]);

            if ($existing) {
                throw new \Exception(get_string('unlock_pet_already_adopted', 'local_ascend_rewards'));
            }

            $avatar_unlocked = $DB->record_exists('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'avatar_name' => $pet_data['avatar'],
                'pet_id' => null,
                'villain_id' => null,
            ]);

            if (!$avatar_unlocked) {
                $avatar_name = pathinfo($pet_data['avatar'], PATHINFO_FILENAME);
                throw new \Exception(get_string('unlock_pet_avatar_required', 'local_ascend_rewards', $avatar_name));
            }

            $new_balance = null;

            $transaction = $DB->start_delegated_transaction();

            try {
                if ($unlock_type === 'token') {
                    $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$token_record) {
                        throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
                    }

                    $tokens_available = $token_record->tokens_available - $token_record->tokens_used;
                    if ($tokens_available <= 0) {
                        throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
                    }

                    $token_record->tokens_used++;
                    $token_record->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $token_record);
                } else {
                    $price = $pet_data['price'];

                    $coins_records = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
                    $total_coins = 0;
                    foreach ($coins_records as $record) {
                        $total_coins += $record->coins;
                    }

                    if ($total_coins < $price) {
                        throw new \Exception(get_string(
                            'unlock_pet_insufficient_coins',
                            'local_ascend_rewards',
                            (object) ['need' => $price, 'have' => $total_coins]
                        ));
                    }

                    $deduction_record = new \stdClass();
                    $deduction_record->userid = $USER->id;
                    $deduction_record->coins = -$price;
                    $deduction_record->badgeid = 0;
                    $deduction_record->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $deduction_record);

                    performance_cache::clear_user_cache($USER->id);
                    performance_cache::clear_leaderboard_cache();

                    $new_balance = $total_coins - $price;
                }

                $unlock_record = new \stdClass();
                $unlock_record->userid = $USER->id;
                $unlock_record->avatar_name = $pet_data['avatar'];
                $unlock_record->avatar_level = $pet_data['level'];
                $unlock_record->pet_id = $pet_id;
                $unlock_record->villain_id = null;
                $unlock_record->unlock_type = $unlock_type;
                $unlock_record->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlock_record);

                $transaction->allow_commit();

                $response = [
                    'success' => true,
                    'message' => get_string('unlock_pet_success', 'local_ascend_rewards'),
                ];

                if ($unlock_type === 'coin') {
                    $response['new_balance'] = $new_balance;
                }

                return $response;
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    /**
     * Unlock a villain using a token or coins.
     *
     * @param int $villain_id
     * @param string $unlock_type
     * @return array
     */
    public static function villain_unlock(int $villain_id, string $unlock_type): array {
        global $DB, $USER;

        $villain_id = (int)$villain_id;
        $unlock_type = (string)$unlock_type;

        try {
            if (!$USER->id) {
                throw new \Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
            }

            if (!in_array($unlock_type, ['token', 'coin'])) {
                throw new \Exception(get_string('unlock_invalid_type', 'local_ascend_rewards'));
            }

            $villain_catalog = [
                300 => ['name' => 'Dryad', 'pet_id' => 100, 'avatar' => 'elf.png', 'level' => 1, 'price' => 500],
                302 => ['name' => 'Mole', 'pet_id' => 102, 'avatar' => 'imp.png', 'level' => 1, 'price' => 500],
            ];

            if (!isset($villain_catalog[$villain_id])) {
                throw new \Exception(get_string('unlock_villain_invalid_id', 'local_ascend_rewards'));
            }

            $villain_data = $villain_catalog[$villain_id];

            $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'villain_id' => $villain_id,
            ]);

            if ($existing) {
                throw new \Exception(get_string('unlock_villain_already_unlocked', 'local_ascend_rewards'));
            }

            $pet_unlocked = $DB->get_record_sql(
                "SELECT * FROM {local_ascend_rewards_avatar_unlocks}
                 WHERE userid = :uid AND pet_id = :petid AND villain_id IS NULL",
                ['uid' => $USER->id, 'petid' => $villain_data['pet_id']]
            );

            if (!$pet_unlocked) {
                throw new \Exception(get_string('unlock_villain_pet_required', 'local_ascend_rewards'));
            }

            $new_balance = null;

            $transaction = $DB->start_delegated_transaction();

            try {
                if ($unlock_type === 'token') {
                    $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$token_record) {
                        throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
                    }

                    $tokens_available = $token_record->tokens_available - $token_record->tokens_used;
                    if ($tokens_available <= 0) {
                        throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
                    }

                    $token_record->tokens_used++;
                    $token_record->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $token_record);
                } else {
                    $price = $villain_data['price'];

                    $coins_records = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
                    $total_coins = 0;
                    foreach ($coins_records as $record) {
                        $total_coins += $record->coins;
                    }

                    if ($total_coins < $price) {
                        throw new \Exception(get_string(
                            'unlock_villain_insufficient_coins',
                            'local_ascend_rewards',
                            (object) ['need' => $price, 'have' => $total_coins]
                        ));
                    }

                    $deduction_record = new \stdClass();
                    $deduction_record->userid = $USER->id;
                    $deduction_record->coins = -$price;
                    $deduction_record->badgeid = 0;
                    $deduction_record->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $deduction_record);

                    performance_cache::clear_user_cache($USER->id);
                    performance_cache::clear_leaderboard_cache();

                    $new_balance = $total_coins - $price;
                }

                $unlock_record = new \stdClass();
                $unlock_record->userid = $USER->id;
                $unlock_record->avatar_name = $villain_data['avatar'];
                $unlock_record->avatar_level = $villain_data['level'];
                $unlock_record->pet_id = $villain_data['pet_id'];
                $unlock_record->villain_id = $villain_id;
                $unlock_record->unlock_type = $unlock_type;
                $unlock_record->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlock_record);

                $transaction->allow_commit();

                $response = [
                    'success' => true,
                    'message' => get_string('unlock_villain_success', 'local_ascend_rewards'),
                ];

                if ($unlock_type === 'coin') {
                    $response['new_balance'] = $new_balance;
                }

                return $response;
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    /**
     * Open a mystery box.
     *
     * @param int $price
     * @return array
     */
    public static function mysterybox_open(int $price = 50): array {
        global $DB, $USER;

        $price = (int)$price;
        $transaction = null;

        try {
            try {
                $sql = "SELECT COALESCE(SUM(coins), 0) as total_coins
                        FROM {local_ascend_rewards_coins}
                        WHERE userid = :userid";
                $balance = $DB->get_field_sql($sql, ['userid' => $USER->id]);
            } catch (\Exception $e) {
                $records = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
                $sum = 0;
                foreach ($records as $r) {
                    $sum += (int)$r->coins;
                }
                $balance = $sum;
            }

            $test_offset = 0;
            try {
                $pref = get_user_preferences('ascend_test_coins', '', $USER->id);
                if ($pref !== '') {
                    $test_offset = (int)$pref;
                }
            } catch (\Exception $e) {
                // ignore
            }
            $balance += $test_offset;

            if ($balance < $price) {
                return [
                    'success' => false,
                    'message' => get_string(
                        'mystery_not_enough_coins',
                        'local_ascend_rewards',
                        number_format($price)
                    ),
                    'balance' => (int)$balance,
                ];
            }

            try {
                $user_xp = (int)$DB->get_field('local_ascend_rewards_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
                if (!$user_xp) {
                    $user_xp = 0;
                }
            } catch (\Exception $e) {
                $user_xp = 0;
            }

            $user_level = (int)($user_xp / 1000) + 1;
            if ($user_level > 8) {
                $user_level = 8;
            }

            $box_number = mt_rand(1, 4);
            $reward_message = '';
            $reward_type = '';
            $reward_data = [];
            $new_balance = (int)$balance;

            $debug_max_level = 1;
            $debug_pool_size = 0;

            $transaction = $DB->start_delegated_transaction();

            $coin_record = new \stdClass();
            $coin_record->userid = $USER->id;
            $coin_record->coins = -$price;
            $coin_record->reason = get_string('mystery_reason_purchase', 'local_ascend_rewards');
            $coin_record->timecreated = time();
            $DB->insert_record('local_ascend_rewards_coins', $coin_record);

            performance_cache::clear_user_cache($USER->id);
            performance_cache::clear_leaderboard_cache();

            $avatar_mapping = [
                1 => 'elf.png',
                16 => 'imp.png',
            ];

            switch ($box_number) {
                case 1:
                    $coin_reward = mt_rand(100, 500);

                    $reward_record = new \stdClass();
                    $reward_record->userid = $USER->id;
                    $reward_record->coins = $coin_reward;
                    $reward_record->reason = get_string('mystery_reason_reward_coins', 'local_ascend_rewards');
                    $reward_record->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $reward_record);

                    $new_balance += $coin_reward;
                    $reward_type = 'coins';
                    $reward_message = get_string('mystery_reward_coins_message', 'local_ascend_rewards', (object)[
                        'amount' => number_format($coin_reward),
                        'coinlabel' => $coin_reward === 1
                            ? get_string('coin_label_singular', 'local_ascend_rewards')
                            : get_string('coin_label_plural', 'local_ascend_rewards'),
                    ]);
                    break;

                case 2:
                    $token_reward = mt_rand(1, 2);

                    $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$token_record) {
                        $token_record = new \stdClass();
                        $token_record->userid = $USER->id;
                        $token_record->tokens_available = 0;
                        $token_record->tokens_used = 0;
                        $token_record->timemodified = time();
                        $token_record->id = $DB->insert_record('local_ascend_rewards_level_tokens', $token_record);
                    }

                    $token_record->tokens_available += $token_reward;
                    $token_record->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $token_record);

                    $new_tokens = $token_record->tokens_available - $token_record->tokens_used;
                    $reward_type = 'tokens';
                    $reward_message = get_string('mystery_reward_tokens_message', 'local_ascend_rewards', (object)[
                        'count' => $token_reward,
                        'tokenlabel' => $token_reward === 1
                            ? get_string('token_label_singular', 'local_ascend_rewards')
                            : get_string('token_label_plural', 'local_ascend_rewards'),
                    ]);
                    break;

                case 3:
                    $max_level_by_xp = min($user_level, 8);
                    $debug_max_level = $max_level_by_xp;

                    $avatar_ids = [1, 16];

                    shuffle($avatar_ids);
                    $debug_pool_size = count($avatar_ids);

                    $attempts = 0;
                    $max_attempts = min(5, count($avatar_ids));
                    $selected_avatar = null;
                    $selected_avatar_level = null;
                    $is_duplicate = false;
                    $is_locked_level = false;

                    while ($attempts < $max_attempts && !$selected_avatar) {
                        $random_avatar = $avatar_ids[$attempts];
                        $avatar_filename = $avatar_mapping[$random_avatar] ?? $random_avatar . '.png';

                        $avatar_level = ($random_avatar == 1) ? 1 : 6;

                        $selected_avatar_level = $avatar_level;

                        $existing_unlock = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                            'userid' => $USER->id,
                            'avatar_name' => $avatar_filename,
                        ]);

                        if (!$existing_unlock) {
                            $selected_avatar = $avatar_filename;
                            $is_duplicate = false;
                            break;
                        }

                        $attempts++;
                    }

                    if (!$selected_avatar && !empty($avatar_ids)) {
                        $random_avatar = $avatar_ids[array_rand($avatar_ids)];
                        $selected_avatar = $avatar_mapping[$random_avatar] ?? $random_avatar . '.png';

                        $selected_avatar_level = ($random_avatar == 1) ? 1 : 6;

                        $is_duplicate = true;
                    }

                    if ($selected_avatar) {
                        $is_locked_level = ($selected_avatar_level > $user_level);

                        if ($is_locked_level) {
                            $coin_reward = mt_rand(150, 500);
                            $reward_record = new \stdClass();
                            $reward_record->userid = $USER->id;
                            $reward_record->coins = $coin_reward;
                            $reward_record->reason = get_string(
                                'mystery_reason_reward_locked_level',
                                'local_ascend_rewards'
                            );
                            $reward_record->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_coins', $reward_record);

                            $new_balance += $coin_reward;
                            $reward_type = 'avatar_locked_level';
                            $reward_message = get_string(
                                'mystery_reward_locked_level_message',
                                'local_ascend_rewards',
                                (object)[
                                    'amount' => number_format($coin_reward),
                                    'coinlabel' => $coin_reward === 1
                                        ? get_string('coin_label_singular', 'local_ascend_rewards')
                                        : get_string('coin_label_plural', 'local_ascend_rewards'),
                                ]
                            );
                            $reward_data = ['avatar_filename' => $selected_avatar, 'avatar_level' => $selected_avatar_level];
                        } else if (!$is_duplicate) {
                            $avatar_unlock_record = new \stdClass();
                            $avatar_unlock_record->userid = $USER->id;
                            $avatar_unlock_record->avatar_name = $selected_avatar;
                            $avatar_unlock_record->avatar_level = $user_level;
                            $avatar_unlock_record->unlock_type = 'mystery_box';
                            $avatar_unlock_record->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_avatar_unlocks', $avatar_unlock_record);

                            $reward_type = 'avatar_new';
                            $reward_message = get_string(
                                'mystery_reward_new_avatar_message',
                                'local_ascend_rewards'
                            );
                            $reward_data = ['avatar_filename' => $selected_avatar];
                        } else {
                            $coin_reward = mt_rand(150, 500);

                            $reward_record = new \stdClass();
                            $reward_record->userid = $USER->id;
                            $reward_record->coins = $coin_reward;
                            $reward_record->reason = get_string(
                                'mystery_reason_reward_duplicate_avatar',
                                'local_ascend_rewards'
                            );
                            $reward_record->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_coins', $reward_record);

                            $new_balance += $coin_reward;
                            $reward_type = 'avatar_duplicate';
                            $reward_message = get_string(
                                'mystery_reward_duplicate_avatar_message',
                                'local_ascend_rewards',
                                (object)[
                                    'amount' => number_format($coin_reward),
                                    'coinlabel' => $coin_reward === 1
                                        ? get_string('coin_label_singular', 'local_ascend_rewards')
                                        : get_string('coin_label_plural', 'local_ascend_rewards'),
                                ]
                            );
                            $reward_data = ['avatar_filename' => $selected_avatar];
                        }
                    } else {
                        $coin_reward = mt_rand(100, 500);

                        $reward_record = new \stdClass();
                        $reward_record->userid = $USER->id;
                        $reward_record->coins = $coin_reward;
                        $reward_record->reason = get_string('mystery_reason_reward_coins', 'local_ascend_rewards');
                        $reward_record->timecreated = time();
                        $DB->insert_record('local_ascend_rewards_coins', $reward_record);

                        $new_balance += $coin_reward;
                        $reward_type = 'coins';
                        $reward_message = get_string('mystery_reward_coins_message', 'local_ascend_rewards', (object)[
                            'amount' => number_format($coin_reward),
                            'coinlabel' => $coin_reward === 1
                                ? get_string('coin_label_singular', 'local_ascend_rewards')
                                : get_string('coin_label_plural', 'local_ascend_rewards'),
                        ]);
                    }
                    break;

                case 4:
                default:
                    $reward_type = 'nothing';
                    $reward_message = get_string('mystery_reward_nothing_message', 'local_ascend_rewards');
                    break;
            }

            $transaction->allow_commit();

            $total_coins = (int)$DB->get_field_sql(
                "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = ?",
                [$USER->id]
            );

            $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
            $total_tokens_available = 0;
            if ($token_record) {
                $total_tokens_available = $token_record->tokens_available - $token_record->tokens_used;
            }

            return [
                'success' => true,
                'box_number' => $box_number,
                'reward_type' => $reward_type,
                'message' => $reward_message,
                'new_balance' => (int)$total_coins,
                'total_tokens' => (int)$total_tokens_available,
                'reward_data' => $reward_data,
                'debug' => [
                    'user_level' => $user_level,
                    'user_xp' => $user_xp,
                    'max_level' => $debug_max_level,
                    'avatar_pool_size' => $debug_pool_size,
                ],
            ];
        } catch (\Exception $e) {
            if ($transaction && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }

            try {
                global $CFG;
                $logdir = __DIR__ . '/../logs';
                if (!function_exists('make_writable_directory')) {
                    require_once($CFG->libdir . '/filelib.php');
                }
                if (make_writable_directory($logdir)) {
                    $logfile = $logdir . '/mysterybox_errors.log';
                    $msg = '[' . date('Y-m-d H:i:s') . '] Error opening mystery box for user ' . $USER->id . ': '
                        . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n---\n";
                    file_put_contents($logfile, $msg, FILE_APPEND | LOCK_EX);
                }
            } catch (\Exception $inner) {
                // ignore logging errors
            }

            return [
                'success' => false,
                'message' => get_string('mystery_error_logged', 'local_ascend_rewards'),
            ];
        }
    }
    /**
     * Check if more badge notifications remain.
     *
     * @return array
     */
    public static function check_notifications(): array {
        global $USER;

        $pending = get_user_preferences('ascend_pending_notifications', '', $USER->id);
        if (empty($pending)) {
            return ['has_more' => false];
        }

        $notifications = json_decode($pending, true);
        if (!is_array($notifications) || empty($notifications)) {
            unset_user_preference('ascend_pending_notifications', $USER->id);
            return ['has_more' => false];
        }

        $filtered = self::filter_recent_notifications($notifications, 7 * DAYSECS);
        if (empty($filtered)) {
            unset_user_preference('ascend_pending_notifications', $USER->id);
            return ['has_more' => false];
        }

        if (count($filtered) !== count($notifications)) {
            set_user_preference('ascend_pending_notifications', json_encode($filtered), $USER->id);
        }

        return ['has_more' => true];
    }

    /**
     * Check if more level-up notifications remain.
     *
     * @return array
     */
    public static function check_levelups(): array {
        global $USER;

        $pending = get_user_preferences('ascend_pending_levelups', '', $USER->id);
        if (empty($pending)) {
            return ['has_more' => false];
        }

        $levelups = json_decode($pending, true);
        if (!is_array($levelups) || empty($levelups)) {
            unset_user_preference('ascend_pending_levelups', $USER->id);
            return ['has_more' => false];
        }

        $filtered = self::filter_recent_notifications($levelups, 7 * DAYSECS);
        if (empty($filtered)) {
            unset_user_preference('ascend_pending_levelups', $USER->id);
            return ['has_more' => false];
        }

        if (count($filtered) !== count($levelups)) {
            set_user_preference('ascend_pending_levelups', json_encode($filtered), $USER->id);
        }

        return ['has_more' => true];
    }

    /**
     * Return medal display for a rank.
     *
     * @param int $place
     * @return string
     */
    private static function medal_for_place(int $place): string {
        if ($place === 1) {
            return '1st';
        }
        if ($place === 2) {
            return '2nd';
        }
        if ($place === 3) {
            return '3rd';
        }
        return (string)$place;
    }

    /**
     * Filter out stale notifications.
     *
     * @param array $items
     * @param int $max_age_seconds
     * @return array
     */
    private static function filter_recent_notifications(array $items, int $max_age_seconds): array {
        $now = time();

        $filtered = array_filter($items, function ($item) use ($now, $max_age_seconds) {
            $timestamp = $item['timestamp'] ?? 0;
            return ($now - $timestamp) <= $max_age_seconds;
        });

        return array_values($filtered);
    }
}
