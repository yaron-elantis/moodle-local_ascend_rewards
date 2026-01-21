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
 * Pet Unlock Handler
 * Processes pet adoptions using tokens OR coins
 */

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_login();
require_once(__DIR__ . '/classes/performance_cache.php');

header('Content-Type: application/json');

$pet_id = required_param('pet_id', PARAM_INT);
$unlock_type = required_param('unlock_type', PARAM_TEXT); // 'token' or 'coin'

try {
    // Validate user is logged in
    if (!$USER->id) {
        throw new Exception('User not logged in');
    }
    
    // Validate unlock type
    if (!in_array($unlock_type, ['token', 'coin'])) {
        throw new Exception('Invalid unlock type');
    }
    
    // Get pet details from catalog (simulated - in real app would be in DB or config)
    // For now, we'll get the avatar and level from the pet catalog
    // You should load this from index.php's $avatar_pets_catalog or a separate config file
    $pet_catalog = [
        // Level 1: Emberveil Forest
        100 => ['name' => 'Lynx', 'avatar' => 'elf.png', 'level' => 1, 'price' => 300],
        101 => ['name' => 'Tortoise', 'avatar' => 'ent.png', 'level' => 1, 'price' => 300],
        102 => ['name' => 'Hamster', 'avatar' => 'imp.png', 'level' => 1, 'price' => 300],
        // Level 2: Stormscar Desert
        103 => ['name' => 'Falcon', 'avatar' => 'nomad.png', 'level' => 2, 'price' => 400],
        104 => ['name' => 'Gryphon', 'avatar' => 'guardian.png', 'level' => 2, 'price' => 400],
        105 => ['name' => 'Boar', 'avatar' => 'warrior.png', 'level' => 2, 'price' => 400],
        // Level 3: Veilspire Empire
        106 => ['name' => 'Viper', 'avatar' => 'sorceress.png', 'level' => 3, 'price' => 500],
        107 => ['name' => 'Swan', 'avatar' => 'queen.png', 'level' => 3, 'price' => 500],
        108 => ['name' => 'Mischiefcap', 'avatar' => 'jester.png', 'level' => 3, 'price' => 500],
        // Level 4: Thalassar Archipelago
        109 => ['name' => 'Otter', 'avatar' => 'amazon.png', 'level' => 4, 'price' => 600],
        110 => ['name' => 'Kinkajou', 'avatar' => 'pirate.png', 'level' => 4, 'price' => 600],
        111 => ['name' => 'Seahorse', 'avatar' => 'mermaid.png', 'level' => 4, 'price' => 600],
        // Level 5: Arcanum Citadel
        112 => ['name' => 'Dragonet', 'avatar' => 'magician.png', 'level' => 5, 'price' => 700],
        113 => ['name' => 'Mastiff', 'avatar' => 'philosopher.png', 'level' => 5, 'price' => 700],
        114 => ['name' => 'Raven', 'avatar' => 'wizard.png', 'level' => 5, 'price' => 700],
        // Level 6: Frostfang Tundra
        115 => ['name' => 'Saber Tooth Tiger', 'avatar' => 'viking.png', 'level' => 6, 'price' => 800],
        116 => ['name' => 'Wolf', 'avatar' => 'sentinel.png', 'level' => 6, 'price' => 800],
        117 => ['name' => 'Polar Bear', 'avatar' => 'beserker.png', 'level' => 6, 'price' => 800],
        // Level 7 (Epic): Pangaea Prime - Zulu
        200 => ['name' => 'Cheetah Bros', 'avatar' => 'zulu.png', 'level' => 7, 'price' => 1000],
        // Level 8 (Epic): Pangaea Prime - Kapu
        201 => ['name' => 'Monkey', 'avatar' => 'kapu.png', 'level' => 8, 'price' => 1000],
        // Level 9 (Epic): Pangaea Prime - Maori
        202 => ['name' => 'Heron', 'avatar' => 'maori.png', 'level' => 9, 'price' => 1000]
    ];
    
    if (!isset($pet_catalog[$pet_id])) {
        throw new Exception('Invalid pet ID');
    }
    
    $pet_data = $pet_catalog[$pet_id];
    
    // Check if pet is already owned
    $existing = $DB->get_record('local_ascend_avatar_unlocks', [
        'userid' => $USER->id,
        'pet_id' => $pet_id,
        'villain_id' => null
    ]);
    
    if ($existing) {
        throw new Exception('Pet already adopted');
    }
    
    // Check if user has unlocked the required avatar
    $avatar_unlocked = $DB->record_exists('local_ascend_avatar_unlocks', [
        'userid' => $USER->id,
        'avatar_name' => $pet_data['avatar'],
        'pet_id' => null,
        'villain_id' => null
    ]);
    
    if (!$avatar_unlocked) {
        throw new Exception('Avatar not unlocked. You must unlock the ' . pathinfo($pet_data['avatar'], PATHINFO_FILENAME) . ' avatar first.');
    }
    
    $new_balance = null;
    
    // Start transaction
    $transaction = $DB->start_delegated_transaction();
    
    try {
        if ($unlock_type === 'token') {
            // Token unlock
            $token_record = $DB->get_record('local_ascend_level_tokens', ['userid' => $USER->id]);
            if (!$token_record) {
                throw new Exception('No token record found');
            }
            
            $tokens_available = $token_record->tokens_available - $token_record->tokens_used;
            if ($tokens_available <= 0) {
                throw new Exception('No tokens available');
            }
            
            // Increment tokens_used
            $token_record->tokens_used++;
            $token_record->timemodified = time();
            $DB->update_record('local_ascend_level_tokens', $token_record);
            
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
                throw new Exception('Insufficient coins. Need ' . $price . ', have ' . $total_coins);
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
        
        $DB->insert_record('local_ascend_avatar_unlocks', $unlock_record);
        
        // Commit transaction
        $transaction->allow_commit();
        
        $response = [
            'success' => true,
            'message' => 'Pet adopted successfully!'
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
        'error' => $e->getMessage()
    ]);
}
