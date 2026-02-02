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
 * Pet unlock handler for Ascend Rewards.
 *
 * Processes pet adoptions using tokens or coins.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/classes/performance_cache.php');
// Preserve legacy naming while we stabilize submission checks.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

header('Content-Type: application/json');

$pet_id = required_param('pet_id', PARAM_INT);
$unlock_type = required_param('unlock_type', PARAM_TEXT); // 'token' or 'coin'

try {
    // Validate user is logged in
    if (!$USER->id) {
        throw new Exception(get_string('unlock_user_not_logged_in', 'local_ascend_rewards'));
    }

    // Validate unlock type
    if (!in_array($unlock_type, ['token', 'coin'])) {
        throw new Exception('Invalid unlock type');
    }

    // DEMO VERSION: Pet catalog with only Lynx (elf) and Hamster (imp)
    $pet_catalog = [
        // Level 1: Emberveil Forest
        100 => ['name' => 'Lynx', 'avatar' => 'elf.png', 'level' => 1, 'price' => 300],
        102 => ['name' => 'Hamster', 'avatar' => 'imp.png', 'level' => 1, 'price' => 300],
    ];

    if (!isset($pet_catalog[$pet_id])) {
        throw new Exception('Invalid pet ID');
    }

    $pet_data = $pet_catalog[$pet_id];

    // Check if pet is already owned
    $existing = $DB->get_record('local_ascend_rewards_avatar_unlocks', [
        'userid' => $USER->id,
        'pet_id' => $pet_id,
        'villain_id' => null,
    ]);

    if ($existing) {
        throw new Exception(get_string('unlock_pet_already_adopted', 'local_ascend_rewards'));
    }

    // Check if user has unlocked the required avatar
    $avatar_unlocked = $DB->record_exists('local_ascend_rewards_avatar_unlocks', [
        'userid' => $USER->id,
        'avatar_name' => $pet_data['avatar'],
        'pet_id' => null,
        'villain_id' => null,
    ]);

    if (!$avatar_unlocked) {
        $avatar_name = pathinfo($pet_data['avatar'], PATHINFO_FILENAME);
        throw new Exception(get_string('unlock_pet_avatar_required', 'local_ascend_rewards', $avatar_name));
    }

    $new_balance = null;

    // Start transaction
    $transaction = $DB->start_delegated_transaction();

    try {
        if ($unlock_type === 'token') {
            // Token unlock
            $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
            if (!$token_record) {
                throw new Exception(get_string('unlock_no_token_record', 'local_ascend_rewards'));
            }

            $tokens_available = $token_record->tokens_available - $token_record->tokens_used;
            if ($tokens_available <= 0) {
                throw new Exception('No tokens available');
            }

            // Increment tokens_used
            $token_record->tokens_used++;
            $token_record->timemodified = time();
            $DB->update_record('local_ascend_rewards_level_tokens', $token_record);
        } else {
            // Coin unlock
            $price = $pet_data['price'];

            // Get user's total coins
            $coins_records = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
            $total_coins = 0;
            foreach ($coins_records as $record) {
                $total_coins += $record->coins;
            }

            if ($total_coins < $price) {
                throw new Exception(get_string('unlock_pet_insufficient_coins', 'local_ascend_rewards', 
                    (object)['need' => $price, 'have' => $total_coins]));
            }

            // Deduct coins by inserting a negative record (preserves XP/level)
            $deduction_record = new stdClass();
            $deduction_record->userid = $USER->id;
            $deduction_record->coins = -$price;
            $deduction_record->badgeid = 0; // 0 indicates spending, not badge earning
            $deduction_record->timecreated = time();
            $DB->insert_record('local_ascend_rewards_coins', $deduction_record);

            // Clear performance caches
            \local_ascend_rewards\performance_cache::clear_user_cache($USER->id);
            \local_ascend_rewards\performance_cache::clear_leaderboard_cache();

            $new_balance = $total_coins - $price;
        }

        // Insert pet unlock record
        $unlock_record = new stdClass();
        $unlock_record->userid = $USER->id;
        $unlock_record->avatar_name = $pet_data['avatar'];
        $unlock_record->avatar_level = $pet_data['level'];
        $unlock_record->pet_id = $pet_id;
        $unlock_record->villain_id = null;
        $unlock_record->unlock_type = $unlock_type;
        $unlock_record->timecreated = time();

        $DB->insert_record('local_ascend_rewards_avatar_unlocks', $unlock_record);

        // Commit transaction
        $transaction->allow_commit();

        $response = [
            'success' => true,
            'message' => get_string('unlock_pet_success', 'local_ascend_rewards'),
        ];

        if ($unlock_type === 'coin') {
            $response['new_balance'] = $new_balance;
        }

        echo json_encode($response);
    } catch (Exception $e) {
        $transaction->rollback($e);
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
