<?php
/**
 * Mystery Box Handler - Opens mystery boxes and awards random rewards
 * 
 * 4 Possible Rewards:
 * - Box 1: Random coins (0-1000)
 * - Box 2: Random tokens (1-2)
 * - Box 3: Random avatar from user's current XP level (can duplicate)
 * - Box 4: Nothing
 * 
 * @package    local_ascend_rewards
 * @copyright  2024 Apex Rewards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/performance_cache.php');

global $DB, $USER;

if (!defined('CLI_SCRIPT')) {
    require_login();
}

header('Content-Type: application/json');

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$price = optional_param('price', 50, PARAM_INT);

try {
    // Get user's coin balance (use safe fallback if read replica fails)
    try {
        $sql = "SELECT COALESCE(SUM(coins), 0) as total_coins 
                FROM {local_ascend_rewards_coins} 
                WHERE userid = :userid";
        $balance = $DB->get_field_sql($sql, ['userid' => $USER->id]);
    } catch (Exception $e) {
        // Fallback: fetch recent records and sum in PHP to avoid replica/read errors
        $records = $DB->get_records('local_ascend_rewards_coins', ['userid' => $USER->id]);
        $sum = 0;
        foreach ($records as $r) { $sum += (int)$r->coins; }
        $balance = $sum;
    }
    
    // Add test coin offset if set (for testing only)
    $test_offset = 0;
    try {
        $pref = get_user_preferences('ascend_test_coins', '', $USER->id);
        if ($pref !== '') {
            $test_offset = (int)$pref;
        }
    } catch (Exception $e) {
        // ignore
    }
    $balance += $test_offset;
    
    if ($balance < $price) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Not enough coins! You need ' . number_format($price) . ' coins.',
            'balance' => (int)$balance
        ]);
        exit;
    }
    
    // Get user's current level (XP-based)
    // Get user's current XP from the separate XP table (safe fallback if aggregate read fails)
    try {
        $user_xp = (int)$DB->get_field('local_ascend_xp', 'xp', ['userid' => $USER->id, 'courseid' => 0]);
        if (!$user_xp) {
            $user_xp = 0;
        }
    } catch (Exception $e) {
        $user_xp = 0;
    }
    
    // Calculate level from XP (assuming levels are: 0-999=L1, 1000-1999=L2, etc.)
    $user_level = (int)($user_xp / 1000) + 1;
    if ($user_level > 8) $user_level = 8; // Cap at level 8
    
    // Determine which reward box is opened (1-4, equally random)
    $box_number = mt_rand(1, 4);
    $reward_message = '';
    $reward_type = '';
    $reward_data = [];
    $new_balance = (int)$balance;
    
    // Debug info
    $debug_max_level = 1;
    $debug_pool_size = 0;
    
    // Start transaction
    $transaction = $DB->start_delegated_transaction();
    
    // Deduct mystery box cost
    $coin_record = new stdClass();
    $coin_record->userid = $USER->id;
    $coin_record->coins = -$price;
    $coin_record->reason = 'Mystery Box Purchase';
    $coin_record->timecreated = time();
    $DB->insert_record('local_ascend_rewards_coins', $coin_record);
    
    // Clear performance caches
    \local_ascend_rewards\performance_cache::clear_user_cache($USER->id);
    \local_ascend_rewards\performance_cache::clear_leaderboard_cache();
    
    // Avatar mapping: DEMO VERSION - only elf and imp available
    // In production, all avatars would be available based on user level
    $avatar_mapping = [
        // DEMO VERSION: Only Elf (Level 1) and Imp (Level 6)
        1 => 'elf.png',
        16 => 'imp.png'
    ];
    
    // Process based on box number
    switch ($box_number) {
        case 1:
            // ✨ COINS: Demo version - random 100-500 coins
            $coin_reward = mt_rand(100, 500);
            
            $reward_record = new stdClass();
            $reward_record->userid = $USER->id;
            $reward_record->coins = $coin_reward;
            $reward_record->reason = 'Mystery Box Reward - Coins';
            $reward_record->timecreated = time();
            $DB->insert_record('local_ascend_rewards_coins', $reward_record);
            
            $new_balance += $coin_reward;
            $reward_type = 'coins';
            $reward_message = 'YOU FOUND COINS! +' . number_format($coin_reward) . ' coins!';
            break;
            
        case 2:
            // ⭐ TOKENS: Random 1-2 tokens
            $token_reward = mt_rand(1, 2);
            
            // Get or create token record from database
            $token_record = $DB->get_record('local_ascend_level_tokens', ['userid' => $USER->id]);
            if (!$token_record) {
                $token_record = new stdClass();
                $token_record->userid = $USER->id;
                $token_record->tokens_available = 0;
                $token_record->tokens_used = 0;
                $token_record->timemodified = time();
                $token_record->id = $DB->insert_record('local_ascend_level_tokens', $token_record);
            }
            
            // Add the reward tokens
            $token_record->tokens_available += $token_reward;
            $token_record->timemodified = time();
            $DB->update_record('local_ascend_level_tokens', $token_record);
            
            $new_tokens = $token_record->tokens_available - $token_record->tokens_used;
            $reward_type = 'tokens';
            $reward_message = 'YOU EARNED TOKENS! +' . $token_reward . ' unlock token' . ($token_reward > 1 ? 's' : '') . '!';
            break;
            
        case 3:
            // 👤 AVATAR: DEMO VERSION - Only Elf and Imp available
            // Get user's XP level
            $max_level_by_xp = min($user_level, 8);
            $debug_max_level = $max_level_by_xp;
            
            // DEMO: Only award elf (ID 1, Level 1) or imp (ID 16, Level 6)
            // Both are available to demo users
            $avatar_ids = [1, 16]; // elf and imp
            
            // Shuffle the array for better randomization
            shuffle($avatar_ids);
            $debug_pool_size = count($avatar_ids);
            $debug_pool_size = count($avatar_ids);
            
            // Try to find an avatar they don't have yet (try up to 5 times)
            $attempts = 0;
            $max_attempts = min(5, count($avatar_ids));
            $selected_avatar = null;
            $selected_avatar_level = null;
            $is_duplicate = false;
            $is_locked_level = false;
            
            while ($attempts < $max_attempts && !$selected_avatar) {
                $random_avatar = $avatar_ids[$attempts];
                $avatar_filename = $avatar_mapping[$random_avatar] ?? $random_avatar . '.png';
                
                // Determine what level this avatar belongs to (DEMO: elf=level 1, imp=level 6)
                $avatar_level = ($random_avatar == 1) ? 1 : 6;
                
                $selected_avatar_level = $avatar_level;
                
                // Check if user has this avatar already unlocked
                $existing_unlock = $DB->get_record('local_ascend_avatar_unlocks', [
                    'userid' => $USER->id, 
                    'avatar_name' => $avatar_filename
                ]);
                
                if (!$existing_unlock) {
                    // Found one they don't have!
                    $selected_avatar = $avatar_filename;
                    $is_duplicate = false;
                    break;
                }
                
                $attempts++;
            }
            
            // If all attempts failed (they have all avatars), use the last one checked
            if (!$selected_avatar && !empty($avatar_ids)) {
                $random_avatar = $avatar_ids[array_rand($avatar_ids)];
                $selected_avatar = $avatar_mapping[$random_avatar] ?? $random_avatar . '.png';
                
                // Determine level for duplicate avatar (DEMO: elf=level 1, imp=level 6)
                $selected_avatar_level = ($random_avatar == 1) ? 1 : 6;
                
                $is_duplicate = true;
            }
            
            if ($selected_avatar) {
                // Guard: if avatar belongs to a level the user hasn’t unlocked yet, convert to coins
                $is_locked_level = ($selected_avatar_level > $user_level);

                if ($is_locked_level) {
                    $coin_reward = mt_rand(150, 500);
                    $reward_record = new stdClass();
                    $reward_record->userid = $USER->id;
                    $reward_record->coins = $coin_reward;
                    $reward_record->reason = 'Mystery Box Reward - Locked Level Avatar (Coins)';
                    $reward_record->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $reward_record);

                    $new_balance += $coin_reward;
                    $reward_type = 'avatar_locked_level';
                    $reward_message = 'That hero belongs to a future level. You received coins instead: +' . number_format($coin_reward) . ' coins!';
                    $reward_data = ['avatar_filename' => $selected_avatar, 'avatar_level' => $selected_avatar_level];
                } else if (!$is_duplicate) {
                    // Add avatar to unlocked list (with their actual current level, not avatar's level)
                    $avatar_unlock_record = new stdClass();
                    $avatar_unlock_record->userid = $USER->id;
                    $avatar_unlock_record->avatar_name = $selected_avatar;
                    $avatar_unlock_record->avatar_level = $user_level;
                    $avatar_unlock_record->unlock_type = 'mystery_box';
                    $avatar_unlock_record->timecreated = time();
                    $DB->insert_record('local_ascend_avatar_unlocks', $avatar_unlock_record);
                    
                    $reward_type = 'avatar_new';
                    $reward_message = 'YOU UNLOCKED A NEW HERO! Your new hero has been added to your collection!';
                    $reward_data = ['avatar_filename' => $selected_avatar];
                } else {
                    // They already have this avatar - give them coins instead (150-500)
                    $coin_reward = mt_rand(150, 500);
                    
                    $reward_record = new stdClass();
                    $reward_record->userid = $USER->id;
                    $reward_record->coins = $coin_reward;
                    $reward_record->reason = 'Mystery Box Reward - Duplicate Avatar (Coins)';
                    $reward_record->timecreated = time();
                    $DB->insert_record('local_ascend_rewards_coins', $reward_record);
                    
                    $new_balance += $coin_reward;
                    $reward_type = 'avatar_duplicate';
                    $reward_message = 'You already have this avatar! You received coins: +' . number_format($coin_reward) . ' coins!';
                    $reward_data = ['avatar_filename' => $selected_avatar];
                }
            } else {
                // Fallback: award coins (100-500)
                $coin_reward = mt_rand(100, 500);
                
                $reward_record = new stdClass();
                $reward_record->userid = $USER->id;
                $reward_record->coins = $coin_reward;
                $reward_record->reason = 'Mystery Box Reward - Coins';
                $reward_record->timecreated = time();
                $DB->insert_record('local_ascend_rewards_coins', $reward_record);
                
                $new_balance += $coin_reward;
                $reward_type = 'coins';
                $reward_message = 'YOU FOUND COINS! +' . number_format($coin_reward) . ' coins!';
            }
            break;
            
        case 4:
        default:
            // NOTHING: Just a message
            $reward_type = 'nothing';
            $reward_message = 'BETTER LUCK NEXT TIME! You got nothing, but the mystery continues...';
            break;
    }
    
    // Commit transaction
    $transaction->allow_commit();
    
    // Calculate actual total balances after reward
    $total_coins = (int)$DB->get_field_sql("SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = ?", [$USER->id]);
    
    // Get total tokens available
    $token_record = $DB->get_record('local_ascend_level_tokens', ['userid' => $USER->id]);
    $total_tokens_available = 0;
    if ($token_record) {
        $total_tokens_available = $token_record->tokens_available - $token_record->tokens_used;
    }
    
    echo json_encode([
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
            'avatar_pool_size' => $debug_pool_size
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($transaction) && !$transaction->is_disposed()) {
        $transaction->rollback($e);
    }
    
    http_response_code(500);
    // Log error to plugin log file for debugging
    try {
        $logdir = __DIR__ . '/logs';
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0755, true);
        }
        $logfile = $logdir . '/mysterybox_errors.log';
        $msg = "[" . date('Y-m-d H:i:s') . "] Error opening mystery box for user {$USER->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n---\n";
        @file_put_contents($logfile, $msg, FILE_APPEND | LOCK_EX);
    } catch (Exception $inner) {
        // ignore logging errors
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error opening mystery box. The error has been logged.'
    ]);
}
// End of script - response already sent above.
