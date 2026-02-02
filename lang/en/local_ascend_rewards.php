<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'local_ascend_rewards', language 'en'
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment,moodle.Files.LangFilesOrdering.IncorrectOrder
// Note: Removed unused legacy strings: allow_repeat_same_badge, allow_repeat_same_badge_desc,
// coins (use coins_label), filtercourse, filternone, leaderboard (use leaderboard_top10_label),
// noleaderdata, norecent, recentbadges, totalassets
$string['pluginname'] = 'Ascend Rewards';
$string['nav_rewards'] = 'Ascend Rewards';
$string['admin_dashboard'] = 'Admin Dashboard';
$string['audit_trail'] = 'Badge Audit Trail';
$string['managebadges'] = 'Manage Ascend Rewards Badges';
$string['createbadges'] = 'Create default badges';
$string['badgescreated'] = 'Badges created/ensured successfully.';
$string['mappingsaved'] = 'Mappings saved.';
$string['savemappings'] = 'Save mappings';
$string['badge_code_label'] = 'Code';
$string['badge_name_label'] = 'Badge Name';
$string['badge_name_getting_started'] = 'Getting Started';
$string['badge_name_halfway_hero'] = 'Halfway Hero';
$string['badge_name_master_navigator'] = 'Master Navigator';
$string['badge_name_feedback_follower'] = 'Feedback Follower';
$string['badge_name_steady_improver'] = 'Steady Improver';
$string['badge_name_tenacious_tiger'] = 'Tenacious Tiger';
$string['badge_name_glory_guide'] = 'Glory Guide';
$string['badge_idnumber_label'] = 'idnumber';
$string['badge_current_id_label'] = 'Current badgeid';
$string['badge_map_label'] = 'Map to badge';
$string['badge_not_mapped_label'] = '- Not mapped -';
$string['coins_settings'] = 'Default Coin Rewards';
$string['coins_settings_desc'] = 'These values are used when the plugin is first installed. You can change them anytime.';
$string['default_coins_label'] = '{$a} - default coins';
$string['default_coins_desc'] = 'Default Ascend Assets awarded for this badge (used on install; can be changed anytime).';
$string['awardbadges'] = 'Award badges (Ascend Auto Badger)';
$string['task_rebuild_badge_cache'] = 'Rebuild badge activity cache';
$string['instructions_text'] = 'Instructions: click here to see how Ascend works';
$string['congrats_title_levelup'] = 'Level Up! You reached Level {$a}!';
$string['congrats_title_newbadge'] = 'New badge earned this week! The Gameboard has been unlocked!';
$string['congrats_subtext'] = 'Keep up the momentum - more Ascend Assets are on the way.';
$string['current_balance_label'] = 'Your current balance';
$string['assets_label'] = 'Ascend Assets';
$string['current_rank_label'] = 'Your current rank';
$string['rank_display'] = '#{$a->rank} of {$a->total} learners';
$string['rank_not_ranked'] = 'Not ranked';
$string['filter_by_course_label'] = 'Filter by course';
$string['all_courses_label'] = 'All courses';
$string['apply_label'] = 'Apply';
$string['reset_label'] = 'Reset';
$string['coins_earned_label'] = 'Coins Earned';
$string['in_this_course_label'] = 'In this course';
$string['badges_earned_label'] = 'Badges Earned';
$string['ranking_label'] = 'Ranking';
$string['xp_label'] = 'XP';
$string['level_label'] = 'Level';
$string['leaderboard'] = 'Leaderboard';
$string['banner_icon_text'] = 'ID';
$string['your_id_label'] = 'Your ID:';
$string['you_label'] = 'You';
$string['user_number_prefix'] = 'User #';
$string['rank_label'] = 'Rank:';
$string['of_label'] = 'of';
$string['learners_label'] = 'learners';
$string['leaderboard_top10_label'] = 'Top 10';
$string['leaderboard_top10_course_label'] = 'Top 10 - {$a}';
$string['leaderboard_context_sitewide'] = 'Sitewide';
$string['my_position_label'] = 'My Position';
$string['leaderboard_empty_label'] = 'No leaderboard data yet.';
$string['no_badges_label'] = 'No badges earned yet.';
$string['course_journey_label'] = 'Course Journey';
$string['no_journeys_label'] = 'No course journeys available.';
$string['activities_completed_label'] = 'activities completed';
$string['progress_label'] = 'progress';
$string['weekly_gameboard_label'] = 'Weekly Gameboard';
$string['picks_available_label'] = 'Picks Available';
$string['normal_badges_label'] = 'Normal Badges';
$string['normal_picks_label'] = '1 pick -';
$string['meta_badges_label'] = 'Meta Badges';
$string['meta_picks_label'] = '2 picks -';
$string['gameboard_locked_label'] = 'Complete activities and earn badges to unlock the gameboard!';
$string['gameboard_success_text'] = 'You earned {coins} coins.';
$string['gameboard_error_prefix'] = 'Error: ';
$string['gameboard_generic_error'] = 'Error making pick';
$string['gameboard_processing_error_prefix'] = 'Error processing pick: ';
$string['leaderboard_range_label'] = '(Ranks {start}-{end} of {total})';
$string['leaderboard_loading_label'] = 'Loading your position...';
$string['leaderboard_context_error_label'] = 'Error loading leaderboard context.';
$string['leaderboard_context_view_error_label'] = 'Error loading context view.';
$string['user_id_badge_label'] = 'ID:';
$string['coaching_header_personal'] = 'Personal Coach';
$string['coaching_header_progress'] = 'Your Progress';
$string['coaching_message_course_complete'] = 'Course Complete! You have finished all activities.';
$string['coaching_message_final_stretch'] = 'Final stretch! Just {$a} activities left.';
$string['coaching_message_halfway'] = 'Halfway there! Keep up the momentum!';
$string['coaching_message_progress'] = 'You are making progress! Keep going!';
$string['ascend_universe_label'] = 'Ascend Universe';
$string['tokens_available_badge_text'] = '{$a} token(s) available!';
$string['tokens_available_text'] = 'You have <strong>{$a}</strong> unlock token(s)!';
$string['you_are_level_label'] = 'You are level';
$string['you_have_label'] = 'You have';
$string['hero_label'] = 'hero';
$string['heroes_label'] = 'Heroes';
$string['unlocked_label'] = 'unlocked';
$string['pet_label'] = 'pet';
$string['pets_label'] = 'Pets';
$string['adopted_label'] = 'adopted';
$string['and_label'] = 'and';
$string['villain_label'] = 'villain';
$string['villains_label'] = 'Villains';
$string['coins_label'] = 'Coins';
$string['watch_story_label'] = 'Watch Story';
$string['modal_not_loaded_label'] = 'Error: unlock system not loaded';
$string['locked_until_label'] = 'Locked until Level';
$string['epic_level_label'] = 'Epic Level';
$string['collection_label'] = 'Collection';
$string['store_label'] = 'Ascend Store';
$string['pro_version_only_message'] = 'This feature is only available in the PRO version.';
$string['coins_available_label'] = 'coins available';
$string['xp_multiplier_active_label'] = 'XP Multiplier Active!';
$string['xp_multiplier_expires_label'] = 'You are earning 2x XP! Expires in:';
$string['powerups_title_label'] = 'Power-Ups & Mystery Items';
$string['not_enough_coins_label'] = 'Not Enough Coins';
$string['available_label'] = 'available';
$string['no_pets_title'] = 'No Pets Available for Adoption';
$string['no_pets_description'] = 'To unlock pets, you must first unlock an avatar on your current level. Once you unlock a hero, their companion pet becomes available for adoption!';
$string['no_pets_tip'] = 'Tip: Head to the Ascend Universe section above to unlock your first hero, then their pet will be ready to adopt.';
$string['no_villains_title'] = 'No Villains Available to be Unleashed';
$string['no_villains_description'] = 'To unlock villains, you must first unlock a pet. Once you adopt a pet, its arch-nemesis villain becomes available for unleashing!';
$string['no_villains_tip'] = 'Tip: Head to the Pets section above to adopt your first companion, then their villain will be ready to unleash.';
$string['linked_hero_label'] = 'Linked Hero:';
$string['linked_pet_label'] = 'Linked Pet:';
$string['box_label_1'] = 'Box 1';
$string['box_label_2'] = 'Box 2';
$string['box_label_3'] = 'Box 3';
$string['box_label_4'] = 'Box 4';
$string['continue_label'] = 'Continue';
$string['expired_label'] = 'Expired';
$string['purchase_confirm_prefix'] = 'Purchase ';
$string['purchase_confirm_mid'] = ' for ';
$string['purchase_confirm_suffix'] = ' coins?';
$string['processing_label'] = 'Processing...';
$string['purchase_success_label'] = 'Purchase successful!';
$string['remaining_balance_label'] = 'Remaining balance:';
$string['error_prefix'] = 'Error: ';
$string['purchase_error_label'] = 'Could not complete purchase';
$string['purchase_processing_error_label'] = 'Error processing purchase';
$string['purchase_button_prefix'] = 'Purchase for ';
$string['coins_reward_prefix'] = 'You received coins instead:';
$string['balance_label'] = 'Balance:';
$string['tokens_reward_prefix'] = 'You received tokens instead:';
$string['tokens_balance_label'] = 'Tokens balance:';
$string['tokens_label'] = 'tokens';
$string['hero_reward_prefix'] = 'You unlocked';
$string['hero_reward_suffix'] = 'Your new hero is now available in Ascend Universe.';
$string['no_reward_label'] = 'No reward this time.';
$string['mystery_error_label'] = 'Could not open mystery box';
$string['mystery_processing_error_label'] = 'Error processing mystery box';
$string['mystery_generic_error_label'] = 'Error opening mystery box';
$string['xp_item_name'] = 'XP Multiplier (24h)';
$string['xp_item_short_name'] = 'XP Multiplier';
$string['xp_item_description'] = 'Double your XP gains for 24 hours! Activate after purchase.';
$string['xp_item_buy_label'] = 'Purchase for {$a} Coins';
$string['activate_label'] = 'Activate';
$string['mystery_box_label'] = 'Mystery Box';
$string['mystery_box_description'] = 'Unwrap a random surprise reward!';
$string['mystery_box_open_label'] = 'Open Mystery Box for {$a} Coins';
$string['pet_aria_label'] = 'Adopt {$a->name} for {$a->price} coins';
$string['pet_buy_label'] = 'Adopt for {$a} Coins';
$string['villain_aria_label'] = 'Unleash {$a->name} for {$a->price} coins';
$string['villain_buy_label'] = 'Unleash for {$a} Coins';
$string['badge_desc_getting_started'] = 'Awarded after completing the first activity in a course = 250';
$string['badge_desc_halfway_hero'] = 'Completed 50% of course activities = 550';
$string['badge_desc_master_navigator'] = 'Gold Badge - obtain 2 x badges in the Progress Based Badges Category = 700';
$string['badge_desc_feedback_follower'] = 'Improve your grade in an activity = 200';
$string['badge_desc_steady_improver'] = 'Fail and then pass an activity = 300';
$string['badge_desc_tenacious_tiger'] = 'Improve your grade in 2 activities = 350';
$string['badge_desc_glory_guide'] = 'Gold Badge - Obtain any 2 badges in the Quality-Based & Growth/Improvement Badges Category = 600';
$string['badge_desc_default'] = 'See badge rules.';
$string['levelup_modal_title'] = 'Level Up!';
$string['levelup_modal_subtitle'] = 'Keep climbing to the top!';
$string['award_number_label_prefix'] = 'Award #';
$string['award_number_label_suffix'] = ':';
$string['badge_category_progress'] = 'Progress-Based';
$string['badge_category_timeliness'] = 'Timeliness & Discipline';
$string['badge_category_quality'] = 'Quality & Growth';
$string['badge_category_mastery'] = 'Course Mastery';
$string['badge_category_other'] = 'Other';
$string['badge_earned_times'] = 'Earned {$a} times';
$string['badge_earned_on'] = 'Earned on {$a}';
$string['badge_earned_title'] = 'Badge Earned!';
$string['badge_earned_subtitle'] = 'Congratulations on your achievement';
$string['fullscreen_label'] = 'Fullscreen';
$string['close_label'] = 'Close';
$string['badge_category_default'] = 'General';
$string['badge_label'] = 'Badge';
$string['xp_default_display'] = '+0 XP';
$string['experience_points_label'] = 'Experience Points';
$string['coins_default_display'] = '+0 Coins';
$string['course_label'] = 'Course';
$string['earned_on_label'] = 'Earned On';
$string['achievement_details_label'] = 'Achievement Details';
$string['qualifying_activities_label'] = 'Qualifying Activities';
$string['contributing_badges_label'] = 'Contributing Badges';
$string['more_activities_label'] = '...and {$a} more activities';
$string['view_my_progress_label'] = 'View My Progress';
$string['unknown_course_label'] = 'Unknown Course';
$string['empty_state_icon'] = '🎁';
$string['mystery_opening_label'] = 'Opening...';
$string['mystery_error_could_not_open'] = 'Could not open mystery box';
$string['mystery_error_processing'] = 'Error opening mystery box. Please try again.';
$string['mystery_error_network'] = 'Network error. Please check your connection and try again.';
$string['unlock_user_not_logged_in'] = 'User not logged in';
$string['unlock_invalid_type'] = 'Invalid unlock type';
$string['unlock_avatar_already_unlocked'] = 'Avatar already unlocked';
$string['unlock_no_token_record'] = 'No token record found';
$string['unlock_no_tokens_available'] = 'No tokens available';
$string['unlock_level_locked'] = 'You have not unlocked level {$a} yet';
$string['unlock_avatar_success'] = 'Avatar unlocked successfully';
$string['unlock_pet_invalid_id'] = 'Invalid pet ID';
$string['unlock_pet_already_adopted'] = 'Pet already adopted';
$string['unlock_pet_avatar_required'] = 'Avatar not unlocked. You must unlock the {$a} avatar first.';
$string['unlock_pet_insufficient_coins'] = 'Insufficient coins. Need {$a->need}, have {$a->have}';
$string['unlock_pet_success'] = 'Pet adopted successfully!';
$string['unlock_villain_invalid_id'] = 'Invalid villain ID';
$string['unlock_villain_already_unlocked'] = 'Villain already unlocked';
$string['unlock_villain_pet_required'] = 'Pet not adopted. You must adopt the matching pet first.';
$string['unlock_villain_insufficient_coins'] = 'Insufficient coins. Need {$a->need}, have {$a->have}';
$string['unlock_villain_success'] = 'Villain unlocked successfully!';

// Privacy API strings.
$string['privacy:metadata:local_ascend_rewards_coins'] = 'Stores user coin and XP transactions for badge awards';
$string['privacy:metadata:userid'] = 'The ID of the user';
$string['privacy:metadata:badgeid'] = 'The ID of the badge awarded';
$string['privacy:metadata:coins'] = 'Number of coins awarded';
$string['privacy:metadata:courseid'] = 'The course where the badge was earned';
$string['privacy:metadata:timecreated'] = 'Time when the record was created';

$string['privacy:metadata:local_ascend_rewards_badgerlog'] = 'Audit log of badge awarding attempts';
$string['privacy:metadata:status'] = 'Status of the badge award (success/failure)';
$string['privacy:metadata:message'] = 'Message describing the badge award';
