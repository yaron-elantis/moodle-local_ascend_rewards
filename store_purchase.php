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
 * Store Item Purchase Handler
 *
 * Handles purchasing of store items with coins.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
// Preserve naming and comment separators without altering behavior.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong

global $DB, $USER;

header('Content-Type: application/json');

// Get item ID from POST
$item_id = required_param('item_id', PARAM_INT);

// Store items with their prices
$store_items = [
    4 => ['name' => 'XP Multiplier (24h)', 'price' => 250],
];

// Check if item exists
if (!isset($store_items[$item_id])) {
    echo json_encode(['success' => false, 'error' => 'Invalid item']);
    exit;
}

$item_price = $store_items[$item_id]['price'];

// Get current coins from database
$current_coins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid",
    ['uid' => $USER->id]
);

// Check if user has enough coins
if ($current_coins < $item_price) {
    echo json_encode(['success' => false, 'error' => 'Not enough coins']);
    exit;
}

// Deduct coins by inserting a negative transaction
$DB->insert_record('local_ascend_rewards_coins', (object)[
    'userid' => $USER->id,
    'coins' => -$item_price,
    'reason' => 'store_purchase_item_' . $item_id,
    'courseid' => 0,
    'timecreated' => time(),
]);

// Calculate new balance after purchase
$new_coins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid",
    ['uid' => $USER->id]
);

// Add to inventory
$inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
$inventory = $inventory_str ? json_decode($inventory_str, true) : [];

if (!isset($inventory[$item_id])) {
    $inventory[$item_id] = 0;
}
$inventory[$item_id]++;

set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

// Auto-activate if it's the XP multiplier (item 4)
if ($item_id == 4) {
    // Check if already active
    $current_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);

    if ($current_end > time()) {
        // Already active - extend it by 24 hours
        $new_end = $current_end + (24 * 60 * 60);
    } else {
        // Not active - activate for 24 hours from now
        $new_end = time() + (24 * 60 * 60);
    }

    // Set the new expiration time
    set_user_preference('ascend_xp_multiplier_end', $new_end, $USER->id);

    // Remove one from inventory and track activation
    $inventory[$item_id]--;
    if ($inventory[$item_id] <= 0) {
        unset($inventory[$item_id]);
    }
    set_user_preference('ascend_store_inventory', json_encode($inventory), $USER->id);

    // Track activation
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

// Return success with remaining coins
echo json_encode([
    'success' => true,
    'remaining_coins' => $new_coins,
]);
