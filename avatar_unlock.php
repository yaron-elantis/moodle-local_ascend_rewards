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
 * Avatar Unlock Handler
 * Processes avatar unlocks using tokens (token-only, no coin option)
 */

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_login();

header('Content-Type: application/json');

$avatar = required_param('avatar', PARAM_TEXT);
$level = required_param('level', PARAM_INT);

try {
    // Validate user is logged in
    if (!$USER->id) {
        throw new Exception('User not logged in');
    }
    
    // Get user's current XP level
    $user_xp = (int)$DB->get_field('local_ascend_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
    $user_level = (int)($user_xp / 1000) + 1;
    if ($user_level > 8) $user_level = 8;
    
    // Validate that user has unlocked the level they're trying to unlock an avatar from
    if ($level > $user_level) {
        throw new Exception('You have not unlocked level ' . $level . ' yet');
    }
    
    // Check if avatar is already unlocked
    $existing = $DB->get_record('local_ascend_avatar_unlocks', [
        'userid' => $USER->id,
        'avatar_name' => $avatar,
        'pet_id' => null,
        'villain_id' => null
    ]);
    
    if ($existing) {
        throw new Exception('Avatar already unlocked');
    }
    
    // Get user's token balance
    $token_record = $DB->get_record('local_ascend_level_tokens', ['userid' => $USER->id]);
    if (!$token_record) {
        throw new Exception('No token record found');
    }
    
    $tokens_available = $token_record->tokens_available - $token_record->tokens_used;
    
    if ($tokens_available <= 0) {
        throw new Exception('No tokens available');
    }
    
    // Start transaction
    $transaction = $DB->start_delegated_transaction();
    
    try {
        // Insert avatar unlock record
        $unlock_record = new stdClass();
        $unlock_record->userid = $USER->id;
        $unlock_record->avatar_name = $avatar;
        $unlock_record->avatar_level = $level;
        $unlock_record->pet_id = null;
        $unlock_record->villain_id = null;
        $unlock_record->unlock_type = 'token';
        $unlock_record->timecreated = time();
        
        $DB->insert_record('local_ascend_avatar_unlocks', $unlock_record);
        
        // Increment tokens_used
        $token_record->tokens_used++;
        $token_record->timemodified = time();
        $DB->update_record('local_ascend_level_tokens', $token_record);
        
        // Commit transaction
        $transaction->allow_commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Avatar unlocked successfully',
            'tokens_remaining' => $token_record->tokens_available - $token_record->tokens_used
        ]);
        
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
