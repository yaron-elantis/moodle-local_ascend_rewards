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
     * @param bool $forcerecalculate
     * @param bool $debugtiming
     * @return array
     */
    public static function get_activities(int $courseid, int $badgeid, bool $forcerecalculate = false, bool $debugtiming = false): array {
        $courseid = (int)$courseid;
        $badgeid = (int)$badgeid;
        $forcerecalculate = (bool)$forcerecalculate;
        $debugtiming = (bool)$debugtiming;
        $returndata = true;

        $result = (function () use ($courseid, $badgeid, $forcerecalculate, $debugtiming, $returndata) {
            return require(__DIR__ . '/../get_activities.php');
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

        $xpcid = ($courseid > 0) ? $courseid : 0;

        $sql = "SELECT x.userid, x.xp
                FROM {local_ascend_rewards_xp} x
                JOIN {user} u ON u.id = x.userid
                WHERE x.courseid = :cid AND x.xp > 0
                  AND u.suspended = 0 AND u.deleted = 0
                ORDER BY x.xp DESC, x.userid ASC";

        $rows = $DB->get_records_sql($sql, ['cid' => $xpcid]);
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

        $usersarray = [];
        if ($myrank !== null && $total > 1) {
            $start = max(0, $myrank - 1 - $neighbors);
            $end = min($total - 1, $myrank - 1 + $neighbors);
            $usersarray = array_slice($ranked, $start, $end - $start + 1);
            $startrank = $start + 1;
            $endrank = min($end + 1, $total);
        } else if ($myrank !== null) {
            $usersarray = array_filter($ranked, function ($r) use ($myrank) {
                return $r['rank'] == $myrank;
            });
            $startrank = $myrank;
            $endrank = $myrank;
        }

        return [
            'success' => true,
            'users' => $usersarray,
            'start_rank' => isset($startrank) ? $startrank : 0,
            'end_rank' => isset($endrank) ? $endrank : 0,
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
                $coinstoadd = (int)$result['coins'];
                $currentbalance = (int)get_user_preferences('ascend_coins_balance', 0, $USER->id);
                $newbalance = $currentbalance + $coinstoadd;

                set_user_preference('ascend_coins_balance', $newbalance, $USER->id);

                debugging(
                    "ascend_rewards: user {$USER->id} picked {$position}, +{$coinstoadd} coins, balance={$newbalance}",
                    DEBUG_DEVELOPER,
                );

                return [
                    'success' => true,
                    'coins' => $coinstoadd,
                    'remaining' => $result['remaining'],
                    'new_balance' => $newbalance,
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
     * @param int $itemid
     * @return array
     */
    public static function store_purchase(int $itemid): array {
        global $DB, $USER;

        $itemid = (int)$itemid;

        $storeitems = [
            4 => ['name' => get_string('xp_item_name', 'local_ascend_rewards'), 'price' => 250],
        ];

        if (!isset($storeitems[$itemid])) {
            return [
                'success' => false,
                'error' => get_string('store_error_invalid_item', 'local_ascend_rewards'),
            ];
        }

        $itemprice = $storeitems[$itemid]['price'];

        $currentcoins = (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = :uid",
            ['uid' => $USER->id]
        );

        if ($currentcoins < $itemprice) {
            return [
                'success' => false,
                'error' => get_string('store_error_not_enough_coins', 'local_ascend_rewards'),
            ];
        }

        $DB->insert_record('local_ascend_rewards_coins', (object)[
            'userid' => $USER->id,
            'coins' => -$itemprice,
            'reason' => 'store_purchase_item_' . $itemid,
            'courseid' => 0,
            'timecreated' => time(),
        ]);

        $newcoins = (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = :uid",
            ['uid' => $USER->id]
        );

        $inventorystr = get_user_preferences('ascend_store_inventory', '', $USER->id);
        $inventory = $inventorystr ? json_decode($inventorystr, true) : [];

        if (!isset($inventory[$itemid])) {
            $inventory[$itemid] = 0;
        }
        $inventory[$itemid]++;

        set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

        if ($itemid == 4) {
            $currentend = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);

            if ($currentend > time()) {
                $newend = $currentend + (24 * 60 * 60);
            } else {
                $newend = time() + (24 * 60 * 60);
            }

            set_user_preference('ascend_xp_multiplier_end', $newend, $USER->id);

            $inventory[$itemid]--;
            if ($inventory[$itemid] <= 0) {
                unset($inventory[$itemid]);
            }
            set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

            $activateditemsstr = get_user_preferences('ascend_store_activated', '', $USER->id);
            $activateditems = $activateditemsstr ? json_decode($activateditemsstr, true) : [];

            if (!isset($activateditems[$itemid])) {
                $activateditems[$itemid] = [];
            }
            $activateditems[$itemid][] = [
                'activated_at' => time(),
                'expires_at' => $newend,
            ];
            set_user_preference('ascend_store_activated', json_encode($activateditems), $USER->id);
        }

        return [
            'success' => true,
            'remaining_coins' => $newcoins,
        ];
    }

    /**
     * Activate a store item.
     *
     * @param int $itemid
     * @return array
     */
    public static function store_activate(int $itemid): array {
        global $USER;

        $itemid = (int)$itemid;

        $inventorystr = get_user_preferences('ascend_store_inventory', '', $USER->id);
        $inventory = $inventorystr ? json_decode($inventorystr, true) : [];

        if (!isset($inventory[$itemid]) || $inventory[$itemid] <= 0) {
            return [
                'success' => false,
                'error' => get_string('store_error_item_not_in_inventory', 'local_ascend_rewards'),
            ];
        }

        if ($itemid == 4) {
            $currentend = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);

            if ($currentend > time()) {
                $newend = $currentend + (24 * 60 * 60);
            } else {
                $newend = time() + (24 * 60 * 60);
            }

            set_user_preference('ascend_xp_multiplier_end', $newend, $USER->id);

            $inventory[$itemid]--;
            if ($inventory[$itemid] <= 0) {
                unset($inventory[$itemid]);
            }
            set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

            $activateditemsstr = get_user_preferences('ascend_store_activated', '', $USER->id);
            $activateditems = $activateditemsstr ? json_decode($activateditemsstr, true) : [];

            if (!isset($activateditems[$itemid])) {
                $activateditems[$itemid] = [];
            }
            $activateditems[$itemid][] = [
                'activated_at' => time(),
                'expires_at' => $newend,
            ];
            set_user_preference('ascend_store_activated', json_encode($activateditems), $USER->id);

            return [
                'success' => true,
                'message' => get_string('store_activate_xp_multiplier_message', 'local_ascend_rewards'),
                'expires_at' => $newend,
                'inventory_count' => isset($inventory[$itemid]) ? $inventory[$itemid] : 0,
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

            $userxp = (int)$DB->get_field('local_ascend_rewards_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
            $userlevel = (int)($userxp / 1000) + 1;
            if ($userlevel > 8) {
                $userlevel = 8;
            }

            if ($level > $userlevel) {
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

            $tokenrecord = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
            if (!$tokenrecord) {
                throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
            }

            $tokensavailable = $tokenrecord->tokens_available - $tokenrecord->tokens_used;

            if ($tokensavailable <= 0) {
                throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
            }

            $transaction = $DB->start_delegated_transaction();

            try {
                $unlockrecord = new \stdClass();
                $unlockrecord->userid = $USER->id;
                $unlockrecord->avatar_name = $avatar;
                $unlockrecord->avatar_level = $level;
                $unlockrecord->pet_id = null;
                $unlockrecord->villain_id = null;
                $unlockrecord->unlock_type = 'token';
                $unlockrecord->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlockrecord);

                $tokenrecord->tokens_used++;
                $tokenrecord->timemodified = time();
                $DB->update_record('local_ascend_rewards_level_tokens', $tokenrecord);

                $transaction->allow_commit();

                return [
                    'success' => true,
                    'message' => get_string('unlock_avatar_success', 'local_ascend_rewards'),
                    'tokens_remaining' => $tokenrecord->tokens_available - $tokenrecord->tokens_used,
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
     * @param int $petid
     * @param string $unlocktype
     * @return array
     */
    public static function pet_unlock(int $petid, string $unlocktype): array {
        global $DB, $USER;

        $petid = (int)$petid;
        $unlocktype = (string)$unlocktype;

        try {
            if (!$USER->id) {
                throw new \Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
            }

            if (!in_array($unlocktype, ['token', 'coin'])) {
                throw new \Exception(get_string('unlock_invalid_type', 'local_ascend_rewards'));
            }

            $petcatalog = [
                100 => ['name' => 'Lynx', 'avatar' => 'elf.png', 'level' => 1, 'price' => 300],
                102 => ['name' => 'Hamster', 'avatar' => 'imp.png', 'level' => 1, 'price' => 300],
            ];

            if (!isset($petcatalog[$petid])) {
                throw new \Exception(get_string('unlock_pet_invalid_id', 'local_ascend_rewards'));
            }

            $petdata = $petcatalog[$petid];

            $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'pet_id' => $petid,
                'villain_id' => null,
            ]);

            if ($existing) {
                throw new \Exception(get_string('unlock_pet_already_adopted', 'local_ascend_rewards'));
            }

            $avatarunlocked = $DB->record_exists('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'avatar_name' => $petdata['avatar'],
                'pet_id' => null,
                'villain_id' => null,
            ]);

            if (!$avatarunlocked) {
                $avatarname = pathinfo($petdata['avatar'], PATHINFO_FILENAME);
                throw new \Exception(get_string('unlock_pet_avatar_required', 'local_ascend_rewards', $avatarname));
            }

            $newbalance = null;

            $transaction = $DB->start_delegated_transaction();

            try {
                if ($unlocktype === 'token') {
                    $tokenrecord = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$tokenrecord) {
                        throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
                    }

                    $tokensavailable = $tokenrecord->tokens_available - $tokenrecord->tokens_used;
                    if ($tokensavailable <= 0) {
                        throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
                    }

                    $tokenrecord->tokens_used++;
                    $tokenrecord->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $tokenrecord);
                } else {
                    $price = $petdata['price'];

                    $coinsrecords = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
                    $totalcoins = 0;
                    foreach ($coinsrecords as $record) {
                        $totalcoins += $record->coins;
                    }

                    if ($totalcoins < $price) {
                        throw new \Exception(get_string(
                            'unlock_pet_insufficient_coins',
                            'local_ascend_rewards',
                            (object) ['need' => $price, 'have' => $totalcoins]
                        ));
                    }

                    $deductionrecord = new \stdClass();
                    $deductionrecord->userid = $USER->id;
                    $deductionrecord->coins = -$price;
                    $deductionrecord->badgeid = 0;
                    $deductionrecord->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $deductionrecord);

                    performance_cache::clear_user_cache($USER->id);
                    performance_cache::clear_leaderboard_cache();

                    $newbalance = $totalcoins - $price;
                }

                $unlockrecord = new \stdClass();
                $unlockrecord->userid = $USER->id;
                $unlockrecord->avatar_name = $petdata['avatar'];
                $unlockrecord->avatar_level = $petdata['level'];
                $unlockrecord->pet_id = $petid;
                $unlockrecord->villain_id = null;
                $unlockrecord->unlock_type = $unlocktype;
                $unlockrecord->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlockrecord);

                $transaction->allow_commit();

                $response = [
                    'success' => true,
                    'message' => get_string('unlock_pet_success', 'local_ascend_rewards'),
                ];

                if ($unlocktype === 'coin') {
                    $response['new_balance'] = $newbalance;
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
     * @param int $villainid
     * @param string $unlocktype
     * @return array
     */
    public static function villain_unlock(int $villainid, string $unlocktype): array {
        global $DB, $USER;

        $villainid = (int)$villainid;
        $unlocktype = (string)$unlocktype;

        try {
            if (!$USER->id) {
                throw new \Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
            }

            if (!in_array($unlocktype, ['token', 'coin'])) {
                throw new \Exception(get_string('unlock_invalid_type', 'local_ascend_rewards'));
            }

            $villaincatalog = [
                300 => ['name' => 'Dryad', 'pet_id' => 100, 'avatar' => 'elf.png', 'level' => 1, 'price' => 500],
                302 => ['name' => 'Mole', 'pet_id' => 102, 'avatar' => 'imp.png', 'level' => 1, 'price' => 500],
            ];

            if (!isset($villaincatalog[$villainid])) {
                throw new \Exception(get_string('unlock_villain_invalid_id', 'local_ascend_rewards'));
            }

            $villaindata = $villaincatalog[$villainid];

            $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                'userid' => $USER->id,
                'villain_id' => $villainid,
            ]);

            if ($existing) {
                throw new \Exception(get_string('unlock_villain_already_unlocked', 'local_ascend_rewards'));
            }

            $petunlocked = $DB->get_record_sql(
                "SELECT * FROM {local_ascend_rewards_avatar_unlocks}
                 WHERE userid = :uid AND pet_id = :petid AND villain_id IS NULL",
                ['uid' => $USER->id, 'petid' => $villaindata['pet_id']]
            );

            if (!$petunlocked) {
                throw new \Exception(get_string('unlock_villain_pet_required', 'local_ascend_rewards'));
            }

            $newbalance = null;

            $transaction = $DB->start_delegated_transaction();

            try {
                if ($unlocktype === 'token') {
                    $tokenrecord = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$tokenrecord) {
                        throw new \Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
                    }

                    $tokensavailable = $tokenrecord->tokens_available - $tokenrecord->tokens_used;
                    if ($tokensavailable <= 0) {
                        throw new \Exception(get_string('unlock_no_tokens_available', 'local_ascend_rewards'));
                    }

                    $tokenrecord->tokens_used++;
                    $tokenrecord->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $tokenrecord);
                } else {
                    $price = $villaindata['price'];

                    $coinsrecords = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
                    $totalcoins = 0;
                    foreach ($coinsrecords as $record) {
                        $totalcoins += $record->coins;
                    }

                    if ($totalcoins < $price) {
                        throw new \Exception(get_string(
                            'unlock_villain_insufficient_coins',
                            'local_ascend_rewards',
                            (object) ['need' => $price, 'have' => $totalcoins]
                        ));
                    }

                    $deductionrecord = new \stdClass();
                    $deductionrecord->userid = $USER->id;
                    $deductionrecord->coins = -$price;
                    $deductionrecord->badgeid = 0;
                    $deductionrecord->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $deductionrecord);

                    performance_cache::clear_user_cache($USER->id);
                    performance_cache::clear_leaderboard_cache();

                    $newbalance = $totalcoins - $price;
                }

                $unlockrecord = new \stdClass();
                $unlockrecord->userid = $USER->id;
                $unlockrecord->avatar_name = $villaindata['avatar'];
                $unlockrecord->avatar_level = $villaindata['level'];
                $unlockrecord->pet_id = $villaindata['pet_id'];
                $unlockrecord->villain_id = $villainid;
                $unlockrecord->unlock_type = $unlocktype;
                $unlockrecord->timecreated = time();

                $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlockrecord);

                $transaction->allow_commit();

                $response = [
                    'success' => true,
                    'message' => get_string('unlock_villain_success', 'local_ascend_rewards'),
                ];

                if ($unlocktype === 'coin') {
                    $response['new_balance'] = $newbalance;
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

            $testoffset = 0;
            try {
                $pref = get_user_preferences('ascend_test_coins', '', $USER->id);
                if ($pref !== '') {
                    $testoffset = (int)$pref;
                }
            } catch (\Exception $e) {
                // Intentionally ignore preference read errors.
                unset($e);
            }
            $balance += $testoffset;

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
                $userxp = (int)$DB->get_field('local_ascend_rewards_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
                if (!$userxp) {
                    $userxp = 0;
                }
            } catch (\Exception $e) {
                $userxp = 0;
            }

            $userlevel = (int)($userxp / 1000) + 1;
            if ($userlevel > 8) {
                $userlevel = 8;
            }

            $boxnumber = mt_rand(1, 4);
            $rewardmessage = '';
            $rewardtype = '';
            $rewarddata = [];
            $newbalance = (int)$balance;

            $debugmaxlevel = 1;
            $debugpoolsize = 0;

            $transaction = $DB->start_delegated_transaction();

            $coinrecord = new \stdClass();
            $coinrecord->userid = $USER->id;
            $coinrecord->coins = -$price;
            $coinrecord->reason = get_string('mystery_reason_purchase', 'local_ascend_rewards');
            $coinrecord->timecreated = time();
            $DB->insert_record('local_ascend_rewards_coins', $coinrecord);

            performance_cache::clear_user_cache($USER->id);
            performance_cache::clear_leaderboard_cache();

            $avatarmapping = [
                1 => 'elf.png',
                16 => 'imp.png',
            ];

            switch ($boxnumber) {
                case 1:
                    $coinreward = mt_rand(100, 500);

                    $rewardrecord = new \stdClass();
                    $rewardrecord->userid = $USER->id;
                    $rewardrecord->coins = $coinreward;
                    $rewardrecord->reason = get_string('mystery_reason_reward_coins', 'local_ascend_rewards');
                    $rewardrecord->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $rewardrecord);

                    $newbalance += $coinreward;
                    $rewardtype = 'coins';
                    $rewardmessage = get_string('mystery_reward_coins_message', 'local_ascend_rewards', (object)[
                        'amount' => number_format($coinreward),
                        'coinlabel' => $coinreward === 1
                            ? get_string('coin_label_singular', 'local_ascend_rewards')
                            : get_string('coin_label_plural', 'local_ascend_rewards'),
                    ]);
                    break;

                case 2:
                    $tokenreward = mt_rand(1, 2);

                    $tokenrecord = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
                    if (!$tokenrecord) {
                        $tokenrecord = new \stdClass();
                        $tokenrecord->userid = $USER->id;
                        $tokenrecord->tokens_available = 0;
                        $tokenrecord->tokens_used = 0;
                        $tokenrecord->timemodified = time();
                        $tokenrecord->id = $DB->insert_record('local_ascend_rewards_level_tokens', $tokenrecord);
                    }

                    $tokenrecord->tokens_available += $tokenreward;
                    $tokenrecord->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $tokenrecord);

                    $newtokens = $tokenrecord->tokens_available - $tokenrecord->tokens_used;
                    $rewardtype = 'tokens';
                    $rewardmessage = get_string('mystery_reward_tokens_message', 'local_ascend_rewards', (object)[
                        'count' => $tokenreward,
                        'tokenlabel' => $tokenreward === 1
                            ? get_string('token_label_singular', 'local_ascend_rewards')
                            : get_string('token_label_plural', 'local_ascend_rewards'),
                    ]);
                    break;

                case 3:
                    $maxlevelbyxp = min($userlevel, 8);
                    $debugmaxlevel = $maxlevelbyxp;

                    $avatarids = [1, 16];

                    shuffle($avatarids);
                    $debugpoolsize = count($avatarids);

                    $attempts = 0;
                    $maxattempts = min(5, count($avatarids));
                    $selectedavatar = null;
                    $selectedavatarlevel = null;
                    $isduplicate = false;
                    $islockedlevel = false;

                    while ($attempts < $maxattempts && !$selectedavatar) {
                        $randomavatar = $avatarids[$attempts];
                        $avatarfilename = $avatarmapping[$randomavatar] ?? $randomavatar . '.png';

                        $avatarlevel = ($randomavatar == 1) ? 1 : 6;

                        $selectedavatarlevel = $avatarlevel;

                        $existingunlock = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
                            'userid' => $USER->id,
                            'avatar_name' => $avatarfilename,
                        ]);

                        if (!$existingunlock) {
                            $selectedavatar = $avatarfilename;
                            $isduplicate = false;
                            break;
                        }

                        $attempts++;
                    }

                    if (!$selectedavatar && !empty($avatarids)) {
                        $randomavatar = $avatarids[array_rand($avatarids)];
                        $selectedavatar = $avatarmapping[$randomavatar] ?? $randomavatar . '.png';

                        $selectedavatarlevel = ($randomavatar == 1) ? 1 : 6;

                        $isduplicate = true;
                    }

                    if ($selectedavatar) {
                        $islockedlevel = ($selectedavatarlevel > $userlevel);

                        if ($islockedlevel) {
                            $coinreward = mt_rand(150, 500);
                            $rewardrecord = new \stdClass();
                            $rewardrecord->userid = $USER->id;
                            $rewardrecord->coins = $coinreward;
                            $rewardrecord->reason = get_string(
                                'mystery_reason_reward_locked_level',
                                'local_ascend_rewards'
                            );
                            $rewardrecord->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_coins', $rewardrecord);

                            $newbalance += $coinreward;
                            $rewardtype = 'avatar_locked_level';
                            $rewardmessage = get_string(
                                'mystery_reward_locked_level_message',
                                'local_ascend_rewards',
                                (object)[
                                    'amount' => number_format($coinreward),
                                    'coinlabel' => $coinreward === 1
                                        ? get_string('coin_label_singular', 'local_ascend_rewards')
                                        : get_string('coin_label_plural', 'local_ascend_rewards'),
                                ]
                            );
                            $rewarddata = ['avatar_filename' => $selectedavatar, 'avatar_level' => $selectedavatarlevel];
                        } else if (!$isduplicate) {
                            $avatarunlockrecord = new \stdClass();
                            $avatarunlockrecord->userid = $USER->id;
                            $avatarunlockrecord->avatar_name = $selectedavatar;
                            $avatarunlockrecord->avatar_level = $userlevel;
                            $avatarunlockrecord->unlock_type = 'mystery_box';
                            $avatarunlockrecord->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_avatar_unlocks', $avatarunlockrecord);

                            $rewardtype = 'avatar_new';
                            $rewardmessage = get_string(
                                'mystery_reward_new_avatar_message',
                                'local_ascend_rewards'
                            );
                            $rewarddata = ['avatar_filename' => $selectedavatar];
                        } else {
                            $coinreward = mt_rand(150, 500);

                            $rewardrecord = new \stdClass();
                            $rewardrecord->userid = $USER->id;
                            $rewardrecord->coins = $coinreward;
                            $rewardrecord->reason = get_string(
                                'mystery_reason_reward_duplicate_avatar',
                                'local_ascend_rewards'
                            );
                            $rewardrecord->timecreated = time();
                            $DB->insert_record('local_ascend_rewards_coins', $rewardrecord);

                            $newbalance += $coinreward;
                            $rewardtype = 'avatar_duplicate';
                            $rewardmessage = get_string(
                                'mystery_reward_duplicate_avatar_message',
                                'local_ascend_rewards',
                                (object)[
                                    'amount' => number_format($coinreward),
                                    'coinlabel' => $coinreward === 1
                                        ? get_string('coin_label_singular', 'local_ascend_rewards')
                                        : get_string('coin_label_plural', 'local_ascend_rewards'),
                                ]
                            );
                            $rewarddata = ['avatar_filename' => $selectedavatar];
                        }
                    } else {
                        $coinreward = mt_rand(100, 500);

                        $rewardrecord = new \stdClass();
                        $rewardrecord->userid = $USER->id;
                        $rewardrecord->coins = $coinreward;
                        $rewardrecord->reason = get_string('mystery_reason_reward_coins', 'local_ascend_rewards');
                        $rewardrecord->timecreated = time();
                        $DB->insert_record('local_ascend_rewards_coins', $rewardrecord);

                        $newbalance += $coinreward;
                        $rewardtype = 'coins';
                        $rewardmessage = get_string('mystery_reward_coins_message', 'local_ascend_rewards', (object)[
                            'amount' => number_format($coinreward),
                            'coinlabel' => $coinreward === 1
                                ? get_string('coin_label_singular', 'local_ascend_rewards')
                                : get_string('coin_label_plural', 'local_ascend_rewards'),
                        ]);
                    }
                    break;

                case 4:
                default:
                    $rewardtype = 'nothing';
                    $rewardmessage = get_string('mystery_reward_nothing_message', 'local_ascend_rewards');
                    break;
            }

            $transaction->allow_commit();

            $totalcoins = (int)$DB->get_field_sql(
                "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = ?",
                [$USER->id]
            );

            $tokenrecord = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
            $totaltokensavailable = 0;
            if ($tokenrecord) {
                $totaltokensavailable = $tokenrecord->tokens_available - $tokenrecord->tokens_used;
            }

            return [
                'success' => true,
                'box_number' => $boxnumber,
                'reward_type' => $rewardtype,
                'message' => $rewardmessage,
                'new_balance' => (int)$totalcoins,
                'total_tokens' => (int)$totaltokensavailable,
                'reward_data' => $rewarddata,
                'debug' => [
                    'user_level' => $userlevel,
                    'user_xp' => $userxp,
                    'max_level' => $debugmaxlevel,
                    'avatar_pool_size' => $debugpoolsize,
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
                // Intentionally ignore logging errors.
                unset($inner);
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
     * @param int $maxageseconds
     * @return array
     */
    private static function filter_recent_notifications(array $items, int $maxageseconds): array {
        $now = time();

        $filtered = array_filter($items, function ($item) use ($now, $maxageseconds) {
            $timestamp = $item['timestamp'] ?? 0;
            return ($now - $timestamp) <= $maxageseconds;
        });

        return array_values($filtered);
    }
}
