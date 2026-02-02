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
 * AJAX handler for gameboard card picks.
 *
 * Receives position from JavaScript and processes the pick.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
// Preserve legacy naming and inline comment separators.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong

global $USER, $DB;

header('Content-Type: application/json');

// Get position from POST
$position = optional_param('position', -1, PARAM_INT);

if ($position < 0 || $position >= 16) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid position']);
    exit;
}

try {
    // Import gameboard class
    require_once(__DIR__ . '/classes/gameboard.php');

    // Make the pick
    $result = local_ascend_rewards_gameboard::make_pick($USER->id, $position);

    if ($result['success']) {
        // Add coins to user's balance (update coins spendable balance)
        $coins_to_add = (int)$result['coins'];

        // Get current balance
        $current_balance = (int)get_user_preferences('ascend_coins_balance', 0, $USER->id);
        $new_balance = $current_balance + $coins_to_add;

        // Update balance preference
        set_user_preference('ascend_coins_balance', $new_balance, $USER->id);

        debugging(
            "ascend_rewards: user {$USER->id} picked {$position}, +{$coins_to_add} coins, balance={$new_balance}",
            DEBUG_DEVELOPER,
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'coins' => $coins_to_add,
            'remaining' => $result['remaining'],
            'new_balance' => $new_balance,
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Could not make pick',
        ]);
    }
} catch (Exception $e) {
    debugging('ascend_rewards gameboard_pick error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
} catch (Throwable $t) {
    debugging('ascend_rewards gameboard_pick fatal: ' . $t->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
