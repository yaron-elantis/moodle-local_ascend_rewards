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

defined('MOODLE_INTERNAL') || die();

/**
 * Adds "Ascend Rewards" to the site navigation.
 */
function local_ascend_rewards_extend_navigation(global_navigation $nav) {
    global $USER;
    
    if (isloggedin() && !isguestuser()) {
        $nav->add(
            navigation_node::create(
                get_string('pluginname', 'local_ascend_rewards'),
                new moodle_url('/local/ascend_rewards/index.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_ascend_rewards'
            )
        );
        
        // Cache warming - pre-load badges user is close to earning (once per hour)
        try {
            require_once(__DIR__ . '/classes/cache_warmer.php');
            $last_warm = get_user_preferences('ascend_last_cache_warm', 0, $USER->id);
            if (time() - $last_warm > 3600) {
                \local_ascend_rewards\cache_warmer::warm_user_cache($USER->id);
                set_user_preference('ascend_last_cache_warm', time(), $USER->id);
            }
        } catch (\Exception $e) {
            // Silently fail - cache warming is not critical
        }
    }
}

/**
 * Adds "Ascend Rewards" to the user navigation (navbar).
 */
function local_ascend_rewards_extend_navigation_user(navigation_node $usernode) {
    global $USER;
    
    if (isloggedin() && !isguestuser()) {
        $usernode->add(
            navigation_node::create(
                get_string('pluginname', 'local_ascend_rewards'),
                new moodle_url('/local/ascend_rewards/index.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_ascend_rewards_navbar'
            )
        );
    }
}

/**
 * Hook to inject badge notification on all pages
 */
function local_ascend_rewards_before_standard_top_of_body_html() {
    global $USER, $PAGE;
    
    if (!isloggedin() || isguestuser()) {
        return '';
    }
    
    // Priority 1: Check for level-up notifications first (show after badges)
    $levelup_output = local_ascend_rewards_show_levelup_modal();
    
    // Priority 2: Check for badge notifications
    $pending = get_user_preferences('ascend_pending_notifications', '', $USER->id);
    if (empty($pending)) {
        return $levelup_output; // Return level-up modal if no badge notifications
    }
    
    $notifications = json_decode($pending, true);
    if (!is_array($notifications) || empty($notifications)) {
        return $levelup_output;
    }
    
    // Filter out stale notifications (older than 7 days)
    $now = time();
    $notifications = array_filter($notifications, function($notif) use ($now) {
        $timestamp = $notif['timestamp'] ?? 0;
        return ($now - $timestamp) <= (7 * DAYSECS);
    });
    
    if (empty($notifications)) {
        unset_user_preference('ascend_pending_notifications', $USER->id);
        return $levelup_output;
    }
    
    // Get the first notification
    $notification = array_shift($notifications);
    
    // Save remaining notifications
    if (empty($notifications)) {
        unset_user_preference('ascend_pending_notifications', $USER->id);
    } else {
        set_user_preference('ascend_pending_notifications', json_encode($notifications), $USER->id);
    }
    
    // Prepare data for JavaScript
    $badgename = s($notification['badgename']);
    $badgeid = (int)($notification['badgeid'] ?? 0);
    $coins = (int)($notification['coins'] ?? 0);
    $xp = isset($notification['xp']) ? (int)$notification['xp'] : (int)floor($coins / 2);
    $coursename = s($notification['coursename'] ?? 'Unknown Course');
    $activities = $notification['activities'] ?? [];
    $rank = isset($notification['rank']) ? (int)$notification['rank'] : 0;
    $total_users = isset($notification['total_users']) ? (int)$notification['total_users'] : 0;
    $rank_change = $notification['rank_change'] ?? null;
    $badge_description = s($notification['description'] ?? 'See badge rules.');
    $badge_category = s($notification['category'] ?? 'General');
    $timestamp = isset($notification['timestamp']) ? (int)$notification['timestamp'] : time();
    $date_earned = userdate($timestamp, get_string('strftimedate', 'langconfig'));
    $rewardsurl = (new moodle_url('/local/ascend_rewards/index.php'))->out(false);
    
    // Map badges to video files with specific animations for each badge
    $badge_videos = [
        // Progress-Based badges (pink theme)
        6  => 'Getting Started/getting_started_2.mp4',      // Getting Started
        4  => 'On a Roll/on_a_roll_2.mp4',                  // On a Roll
        5  => 'Halfway Hero/halfway_hero_1.mp4',            // Halfway Hero
        8  => 'Master Navigator/master_navigator_3.mp4',    // Master Navigator (meta)
        
        // Timeliness & Discipline (cyan theme)
        9  => 'Early Bird/early_bird_1.mp4',                // Early Bird
        11 => 'Sharp Shooter/sharp_shooter_1.mp4',          // Sharp Shooter
        10 => 'Deadline Burner/deadline_burner_1.mp4',      // Deadline Burner
        12 => 'Time Tamer/time_tamer_4.mp4',                // Time Tamer (meta)
        
        // Quality & Growth (orange theme)
        13 => 'Feedback Follower/feedback_follower_1.mp4',  // Feedback Follower
        15 => 'Steady Improver/steady_improver_1.mp4',      // Steady Improver
        14 => 'Tenacious Tiger/tenacious_tiger_1.mp4',      // Tenacious Tiger
        16 => 'Glory Guide/glory_guide_3.mp4',              // Glory Guide (meta)
        
        // Course Mastery (purple theme)
        19 => 'High Flyer/high_flyer_3.mp4',                // High Flyer
        17 => 'Activity Ace/activity_ace_3.mp4',            // Activity Ace
        7  => 'Mission Complete/mission_complete_1.mp4',    // Mission Complete
        20 => 'Learning Legend/learning_legend_5.mp4',      // Learning Legend (super meta)
    ];
    
    // Get video filename for this badge, fallback to default
    $video_filename = $badge_videos[$badgeid] ?? 'reward_animation_2.mp4';
    $videourl = (new moodle_url('/local/ascend_rewards/pix/' . $video_filename))->out(false);
    $medalurl = (new moodle_url('/local/ascend_rewards/pix/medal_gold.png'))->out(false);
    
    // Output HTML and JavaScript for notification
    $output = <<<HTML
<style>
/* Modern Badge Notification Modal */
.apex-reward-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(8px);
  z-index: 9998;
  display: none;
  animation: fadeIn 0.3s ease-out;
}

.apex-reward-modal {
  position: fixed;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 520px;
  max-width: 95vw;
  max-height: 90vh;
  background: linear-gradient(145deg, #0a1832 0%, #0d1b35 100%);
  border: 2px solid #ff9500;
  border-radius: 20px;
  box-shadow: 
    0 25px 50px rgba(0, 0, 0, 0.5),
    0 0 0 1px rgba(255, 149, 0, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  z-index: 9999;
  display: none;
  overflow: hidden;
  animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { 
    opacity: 0;
    transform: translate(-50%, -60%);
  }
  to { 
    opacity: 1;
    transform: translate(-50%, -50%);
  }
}

/* Header */
.apex-reward-header {
  position: relative;
  padding: 24px 24px 20px;
  background: linear-gradient(90deg, rgba(255, 149, 0, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%);
  border-bottom: 1px solid rgba(255, 149, 0, 0.2);
  overflow: hidden;
}

.apex-reward-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 20%, rgba(255, 149, 0, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 80% 80%, rgba(0, 212, 255, 0.1) 0%, transparent 50%);
  pointer-events: none;
}

.apex-reward-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  text-align: center;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  background: linear-gradient(135deg, #ffd700 0%, #ff9500 50%, #00d4ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  position: relative;
  z-index: 1;
}

.apex-reward-subtitle {
  font-size: 0.9rem;
  color: #a5b4d6;
  text-align: center;
  margin: 4px 0 0;
  font-weight: 500;
  position: relative;
  z-index: 1;
}

/* Progress Indicator */
.apex-reward-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, #ff9500 0%, #ffd700 50%, #00d4ff 100%);
  width: 100%;
  transform-origin: left;
  animation: progressShrink 25s linear forwards;
  border-radius: 0 0 20px 20px;
}

@keyframes progressShrink {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

.apex-reward-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  text-align: center;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  background: linear-gradient(135deg, #ffd700 0%, #ff9500 50%, #00d4ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.apex-reward-subtitle {
  font-size: 0.9rem;
  color: #a5b4d6;
  text-align: center;
  margin: 4px 0 0;
  font-weight: 500;
}

.apex-reward-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 40px;
  height: 40px;
  background: rgba(255, 149, 0, 0.1);
  border: 2px solid rgba(255, 149, 0, 0.3);
  border-radius: 50%;
  color: #ff9500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 18px;
  font-weight: bold;
}

.apex-reward-close:hover {
  background: rgba(255, 149, 0, 0.2);
  border-color: #ff9500;
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
}

/* Body */
.apex-reward-body {
  padding: 24px;
  overflow-y: auto;
  max-height: calc(90vh - 120px);
}

.apex-reward-body::-webkit-scrollbar {
  width: 8px;
}

.apex-reward-body::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 4px;
}

.apex-reward-body::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, rgba(255, 149, 0, 0.6) 0%, rgba(255, 149, 0, 0.3) 100%);
  border-radius: 4px;
}

/* Video Section */
.apex-reward-video-container {
  position: relative;
  width: 100%;
  max-width: 320px;
  aspect-ratio: 1;
  border-radius: 16px;
  overflow: hidden;
  margin: 0 auto 24px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  background: linear-gradient(135deg, rgba(0, 212, 255, 0.1) 0%, rgba(255, 0, 170, 0.1) 50%, rgba(255, 149, 0, 0.1) 100%);
}

.apex-reward-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  background: #000;
}

.apex-reward-fullscreen-btn {
  position: absolute;
  bottom: 12px;
  right: 12px;
  width: 36px;
  height: 36px;
  background: rgba(0, 0, 0, 0.8);
  border: 2px solid rgba(255, 149, 0, 0.5);
  border-radius: 8px;
  color: #ffd700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  transition: all 0.3s ease;
  z-index: 10;
}

.apex-reward-fullscreen-btn:hover {
  background: rgba(255, 149, 0, 0.2);
  border-color: #ff9500;
  transform: scale(1.05);
}

/* Stats Grid */
.apex-reward-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}

.apex-reward-stat-card {
  background: linear-gradient(135deg, rgba(0, 212, 255, 0.1) 0%, rgba(255, 0, 170, 0.1) 100%);
  border: 2px solid rgba(255, 149, 0, 0.3);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.apex-reward-stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #00d4ff 0%, #ff00aa 50%, #ff9500 100%);
}

.apex-reward-stat-card:hover {
  transform: translateY(-2px);
  border-color: rgba(255, 149, 0, 0.6);
  box-shadow: 0 8px 25px rgba(255, 149, 0, 0.2);
}

.apex-reward-stat-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(0, 212, 255, 0.2) 0%, rgba(255, 0, 170, 0.2) 100%);
  border: 2px solid rgba(255, 149, 0, 0.4);
}

.apex-reward-stat-icon svg {
  width: 24px;
  height: 24px;
}

.apex-reward-stat-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: #ffd700;
  margin: 0;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.apex-reward-stat-label {
  font-size: 0.8rem;
  color: #a5b4d6;
  margin: 4px 0 0;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Info Section */
.apex-reward-info {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 149, 0, 0.2);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.apex-reward-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.apex-reward-info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.apex-reward-info-label {
  font-size: 0.8rem;
  color: #a5b4d6;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.apex-reward-info-value {
  font-size: 0.95rem;
  color: #ffffff;
  font-weight: 500;
}

/* Description */
.apex-reward-description {
  margin-bottom: 20px;
  position: relative;
}

.apex-reward-description::before {
  content: '📋';
  position: absolute;
  left: -8px;
  top: 2px;
  font-size: 1.2rem;
  opacity: 0.8;
}

.apex-reward-description-title {
  font-size: 1rem;
  font-weight: 700;
  color: #00d4ff;
  margin: 0 0 8px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.apex-reward-description-title::before {
  content: '';
  width: 4px;
  height: 16px;
  background: linear-gradient(180deg, #00d4ff, #0099cc);
  border-radius: 2px;
}

.apex-reward-description-text {
  font-size: 0.9rem;
  color: #e9f0ff;
  line-height: 1.6;
  margin: 0 0 0 24px;
  padding: 12px 16px;
  background: rgba(255, 255, 255, 0.02);
  border-left: 3px solid rgba(0, 212, 255, 0.4);
  border-radius: 0 8px 8px 0;
}

/* Activities */
.apex-reward-activities {
  margin-bottom: 20px;
  position: relative;
}

.apex-reward-activities::before {
  content: '🎯';
  position: absolute;
  left: -8px;
  top: 2px;
  font-size: 1.2rem;
  opacity: 0.8;
}

.apex-reward-activities-title {
  font-size: 1rem;
  font-weight: 700;
  color: #00d4ff;
  margin: 0 0 12px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.apex-reward-activities-title::before {
  content: '';
  width: 4px;
  height: 16px;
  background: linear-gradient(180deg, #00d4ff, #0099cc);
  border-radius: 2px;
}

.apex-reward-activities-list {
  list-style: none;
  margin: 0 0 0 24px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.02);
  border-left: 3px solid rgba(0, 212, 255, 0.4);
  border-radius: 0 8px 8px 0;
  max-height: 150px;
  overflow-y: auto;
}

.apex-reward-activities-list::-webkit-scrollbar {
  width: 6px;
}

.apex-reward-activities-list::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 3px;
}

.apex-reward-activities-list::-webkit-scrollbar-thumb {
  background: rgba(255, 149, 0, 0.5);
  border-radius: 3px;
}

.apex-reward-activity-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  font-size: 0.85rem;
  color: #e9f0ff;
  transition: all 0.2s ease;
  position: relative;
}

.apex-reward-activity-item:hover {
  background: rgba(255, 255, 255, 0.02);
  border-radius: 4px;
  padding-left: 8px;
  margin-left: -8px;
  margin-right: -8px;
}

.apex-reward-activity-item:last-child {
  border-bottom: none;
}

.apex-reward-activity-item::before {
  content: '✅';
  color: #00ff88;
  font-weight: 800;
  font-size: 0.9rem;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.apex-reward-activity-item:hover::before {
  transform: scale(1.1);
}

/* Rank */
.apex-reward-rank {
  background: linear-gradient(135deg, rgba(0, 212, 255, 0.1) 0%, rgba(255, 0, 170, 0.1) 100%);
  border: 2px solid rgba(0, 212, 255, 0.3);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 24px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.apex-reward-rank::before {
  content: '🏅';
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1.5rem;
  opacity: 0.6;
}

.apex-reward-rank-text {
  font-size: 1rem;
  color: #ffffff;
  font-weight: 600;
  margin: 0;
  position: relative;
  z-index: 1;
}

.apex-reward-rank-change {
  font-size: 0.9rem;
  font-weight: 800;
  margin: 4px 0 0;
  position: relative;
  z-index: 1;
}

.apex-reward-rank-change.up {
  color: #00ff88;
}

.apex-reward-rank-change.down {
  color: #ff4444;
}

/* Actions */
.apex-reward-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 149, 0, 0.2);
  position: relative;
}

.apex-reward-actions::before {
  content: '';
  position: absolute;
  top: -1px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(255, 149, 0, 0.6), transparent);
  border-radius: 1px;
}

.apex-reward-btn {
  padding: 14px 28px;
  border-radius: 10px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 0.95rem;
  min-width: 160px;
  position: relative;
  overflow: hidden;
}

.apex-reward-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
  transition: left 0.5s;
}

.apex-reward-btn:hover::before {
  left: 100%;
}

.apex-reward-btn-primary {
  background: linear-gradient(135deg, #ffd700 0%, #ff9500 100%);
  color: #01142e;
  border: none;
  box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
}

.apex-reward-btn-primary:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 8px 25px rgba(255, 149, 0, 0.4);
}
</style>

<div id="apexRewardBackdrop" class="apex-reward-modal-backdrop"></div>
<div id="apexRewardModal" class="apex-reward-modal">
  <div class="apex-reward-header">
    <h1 class="apex-reward-title">🏆 Badge Earned!</h1>
    <p class="apex-reward-subtitle">Congratulations on your achievement</p>
    <div class="apex-reward-progress"></div>
    <button class="apex-reward-close" onclick="closeApexRewardModal()" aria-label="Close">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </div>
  
  <div class="apex-reward-body">
    <!-- Video Section -->
    <div class="apex-reward-video-container">
      <video id="apexRewardVideo" class="apex-reward-video" playsinline loop>
        <source src="{$videourl}" type="video/mp4">
      </video>
      <button class="apex-reward-fullscreen-btn" id="apexRewardFullscreen" title="Fullscreen">⛶</button>
    </div>
    
    <!-- Badge Name -->
    <div style="text-align: center; margin-bottom: 24px;">
      <h2 style="font-size: 1.5rem; font-weight: 800; color: #00d4ff; margin: 0; text-shadow: 0 2px 4px rgba(0, 212, 255, 0.3);">{$badgename}</h2>
      <p style="font-size: 0.9rem; color: #a5b4d6; margin: 4px 0 0; font-weight: 500;">{$badge_category} Badge</p>
    </div>
    
    <!-- Stats -->
    <div class="apex-reward-stats">
      <div class="apex-reward-stat-card">
        <div class="apex-reward-stat-icon">
          <svg width="48" height="48" viewBox="0 0 80 80">
            <defs><linearGradient id="xpIconGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>
            <circle cx="40" cy="40" r="36" fill="url(#xpIconGrad)" />
            <text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">X</text>
          </svg>
        </div>
        <div class="apex-reward-stat-value" id="apexRewardXP">+0 XP</div>
        <div class="apex-reward-stat-label">Experience Points</div>
      </div>
      
      <div class="apex-reward-stat-card">
        <div class="apex-reward-stat-icon">
          <img id="apexRewardMedal" src="" alt="Medal" style="width: 24px; height: 24px;" onerror="this.style.display='none';">
        </div>
        <div class="apex-reward-stat-value">+{$coins} Coins</div>
        <div class="apex-reward-stat-label">Ascend Assets</div>
      </div>
    </div>
    
    <!-- Info Grid -->
    <div class="apex-reward-info">
      <div class="apex-reward-info-grid">
        <div class="apex-reward-info-item">
          <div class="apex-reward-info-label">Course</div>
          <div class="apex-reward-info-value">{$coursename}</div>
        </div>
        <div class="apex-reward-info-item">
          <div class="apex-reward-info-label">Earned On</div>
          <div class="apex-reward-info-value" id="apexRewardDate">{$date_earned}</div>
        </div>
      </div>
    </div>
    
    <!-- Description -->
    <div class="apex-reward-description">
      <h3 class="apex-reward-description-title">Achievement Details</h3>
      <p class="apex-reward-description-text" id="apexRewardDescription">{$badge_description}</p>
    </div>
    
    <!-- Activities -->
HTML;

    // Add qualifying activities if available
    if (!empty($activities)) {
        $output .= '<div class="apex-reward-activities">';
        $output .= '<h3 class="apex-reward-activities-title">Qualifying Activities</h3>';
        $output .= '<ul class="apex-reward-activities-list">';
        $activity_count = 0;
        foreach ($activities as $activity) {
            if ($activity_count >= 10) {
                $remaining = count($activities) - $activity_count;
                $output .= '<li class="apex-reward-activity-item" style="color: #ffd700; font-weight: 600;">...and ' . $remaining . ' more activities</li>';
                break;
            }
            $output .= '<li class="apex-reward-activity-item">' . s($activity) . '</li>';
            $activity_count++;
        }
        $output .= '</ul></div>';
    }

    // Add rank information if available
    if ($rank > 0 && $total_users > 0) {
        $rank_change_class = '';
        $rank_change_text = '';
        
        if ($rank_change !== null && $rank_change != 0) {
            if ($rank_change > 0) {
                $rank_change_class = 'up';
                $rank_change_text = ' ↑ +' . abs($rank_change);
            } elseif ($rank_change < 0) {
                $rank_change_class = 'down';
                $rank_change_text = ' ↓ ' . abs($rank_change);
            }
        }
        
        $output .= '<div class="apex-reward-rank">';
        $output .= '<div class="apex-reward-rank-text">🏆 Rank: <strong>#' . $rank . ' of ' . $total_users . '</strong></div>';
        if ($rank_change_text) {
            $output .= '<div class="apex-reward-rank-change ' . $rank_change_class . '">' . $rank_change_text . '</div>';
        }
        $output .= '</div>';
    }

    $output .= <<<HTML
    <!-- Actions -->
    <div class="apex-reward-actions">
      <a href="{$rewardsurl}" class="apex-reward-btn apex-reward-btn-primary">View My Progress</a>
    </div>
  </div>
</div>

<script>
(function(){
  var backdrop = document.getElementById('apexRewardBackdrop');
  var modal = document.getElementById('apexRewardModal');
  var video = document.getElementById('apexRewardVideo');
  var xpElement = document.getElementById('apexRewardXP');
  var medalImg = document.getElementById('apexRewardMedal');
  var descriptionElement = document.getElementById('apexRewardDescription');
  var dateElement = document.getElementById('apexRewardDate');
  var fullscreenBtn = document.getElementById('apexRewardFullscreen');

  // XP value comes directly from the notification payload (not derived from coins)
  var coins = {$coins};
  var badgeXp = {$xp};
  
  // Set XP display
  if(xpElement) {
    xpElement.textContent = '+' + badgeXp + ' XP';
  }
  
  // Set medal icon URL
  if(medalImg) {
    medalImg.src = '{$medalurl}';
  }
  
  // Set description
  if(descriptionElement) {
    descriptionElement.textContent = '{$badge_description}';
  }
  
  // Set date
  if(dateElement) {
    dateElement.textContent = '{$date_earned}';
  }
  
  // Fullscreen button functionality
  if(fullscreenBtn && video) {
    fullscreenBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      if(video.requestFullscreen) {
        video.requestFullscreen();
      } else if(video.webkitRequestFullscreen) {
        video.webkitRequestFullscreen();
      } else if(video.mozRequestFullScreen) {
        video.mozRequestFullScreen();
      } else if(video.msRequestFullscreen) {
        video.msRequestFullscreen();
      }
    });
  }

  var loopCount = 0;
  var maxLoops = 3;

  function showModal() {
    backdrop.style.display = 'block';
    modal.style.display = 'block';
    if(video) {
      loopCount = 0;
      video.volume = 0.7;
      video.currentTime = 0;
      video.play().catch(function(){});
    }
  }

  // Loop video 3 times
  if(video) {
    video.addEventListener('ended', function() {
      loopCount++;
      if(loopCount < maxLoops) {
        video.currentTime = 0;
        video.play().catch(function(){});
      }
    });
  }

  window.closeApexRewardModal = function() {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
    if(video) {
      video.pause();
      video.currentTime = 0;
      loopCount = 0;
    }
    // Check if there are more notifications first, then trigger level-up only if none
    checkForMoreNotifications();
  };

  function checkForMoreNotifications() {
    // Use AJAX to check if there are more pending notifications
    var xhr = new XMLHttpRequest();
    xhr.open('GET', M.cfg.wwwroot + '/local/ascend_rewards/check_notifications.php', true);
    xhr.onload = function() {
      if(xhr.status === 200) {
        var response = JSON.parse(xhr.responseText);
        console.log('Check notifications response:', response);
        if(response.has_more) {
          // Reload page to show next notification after a short delay
          console.log('More badges pending, reloading page...');
          setTimeout(function() {
            window.location.reload();
          }, 300);
        } else {
          // No more badge notifications, show level-up if present
          console.log('No more badges, checking for level-up modal...');
          if(window.showApexLevelup) {
            console.log('Triggering level-up modal!');
            setTimeout(window.showApexLevelup, 500);
          } else {
            console.log('No level-up modal function found');
          }
        }
      }
    };
    xhr.send();
  }

  backdrop.addEventListener('click', closeApexRewardModal);

  // Show modal after page loads
  if(document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(showModal, 500);
    });
  } else {
    setTimeout(showModal, 500);
  }

  // Auto-close after 25 seconds (increased for more content)
  setTimeout(closeApexRewardModal, 25000);
})();
</script>
HTML;
    
    return $output . $levelup_output;
}

/**
 * Generate level-up modal HTML if there are pending level-ups
 */
function local_ascend_rewards_show_levelup_modal() {
    global $USER;
    
    if (!isloggedin() || isguestuser()) {
        return '';
    }
    
    // Check for pending level-up notifications
    $pending = get_user_preferences('ascend_pending_levelups', '', $USER->id);
    if (empty($pending)) {
        return '';
    }
    
    $levelups = json_decode($pending, true);
    if (!is_array($levelups) || empty($levelups)) {
        return '';
    }
    
    // Filter out stale level-ups (older than 7 days)
    $now = time();
    $levelups = array_filter($levelups, function($lvl) use ($now) {
        $timestamp = $lvl['timestamp'] ?? 0;
        return ($now - $timestamp) <= (7 * DAYSECS);
    });
    
    if (empty($levelups)) {
        unset_user_preference('ascend_pending_levelups', $USER->id);
        return '';
    }
    
    // Get the first level-up notification
    $levelup = array_shift($levelups);
    
    // Save remaining level-up notifications
    if (empty($levelups)) {
        unset_user_preference('ascend_pending_levelups', $USER->id);
    } else {
        set_user_preference('ascend_pending_levelups', json_encode($levelups), $USER->id);
    }
    
    $level = (int)($levelup['level'] ?? 1);
    
    // Ensure level is between 1 and 10
    if ($level < 1 || $level > 10) {
        return '';
    }
    
    // Get video URL for this level
    $video_filename = "Level Up/Level_{$level}.mp4";
    $videourl = (new moodle_url('/local/ascend_rewards/pix/' . $video_filename))->out(false);
    $soundurl = (new moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false);
    
    // Output HTML and JavaScript for level-up notification
    $output = <<<HTML
<style>
.apex-levelup-backdrop{
    position:fixed;
    top:0;left:0;
    width:100%;height:100%;
    background:rgba(0,0,0,0.75);
    z-index:10000;
    display:none;
    backdrop-filter:blur(2px);
}

.apex-levelup-modal{
    position:fixed;
    left:50%;top:50%;
    transform:translate(-50%,-50%);
    width:480px;
    max-width:90vw;
    max-height:75vh;
    background:#01142E;
    color:#e9f0ff;
    border:3px solid #FF9500;
    border-radius:16px;
    box-shadow:0 12px 48px rgba(0,0,0,0.6);
    z-index:10001;
    overflow:hidden;
    display:none;
    flex-direction:column;
}

.apex-levelup-modal[style*="display: block"]{display:flex!important;}

.apex-levelup-header{
    padding:20px;
    text-align:center;
    background:#01142E;
    border-bottom:2px solid rgba(255,149,0,0.3);
    position:relative;
}

.apex-levelup-close{
    position:absolute;
    top:10px;right:10px;
    background:rgba(0,0,0,0.7);
    border:2px solid #FF9500;
    color:#FFD700;
    font-size:20px;
    cursor:pointer;
    width:36px;height:36px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    transition:all 0.3s;
    z-index:15;
}

.apex-levelup-close:hover{
    background:rgba(255,149,0,0.3);
    color:#FFF;
    transform:scale(1.1);
}

.apex-levelup-close:before{
    content:'✕';
    font-weight:400;
    line-height:1;
}

.apex-levelup-title{
    font-size:1.5rem;
    font-weight:800;
    color:#FFD700;
    margin:0;
    text-shadow:0 2px 8px rgba(255,215,0,0.3);
}

.apex-levelup-body{
    position:relative;
    background:linear-gradient(135deg,rgba(0,212,255,0.1) 0%,rgba(255,0,170,0.1) 50%,rgba(255,149,0,0.1) 100%);
    overflow:hidden;
    width:100%;
    max-width:320px;
    aspect-ratio:1;
    margin:0 auto;
}

.apex-levelup-video{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
    background:#000;
}

.apex-levelup-footer{
    padding:24px 20px;
    text-align:center;
    background:#01142E;
    border-top:2px solid rgba(255,149,0,0.3);
}

.apex-levelup-level{
    font-size:3rem;
    font-weight:900;
    background:linear-gradient(135deg,#FFD700 0%,#FF9500 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin:0 0 12px 0;
    line-height:1;
    text-shadow:0 4px 12px rgba(255,149,0,0.4);
}

.apex-levelup-subtitle{
    font-size:1rem;
    font-weight:600;
    color:#A5B4D6;
    margin:0;
}

@media (max-width:640px){
    .apex-levelup-title{font-size:1.25rem;}
    .apex-levelup-level{font-size:2.5rem;}
    .apex-levelup-subtitle{font-size:0.9rem;}
    .apex-levelup-body{max-width:280px;}
}
</style>

<div class="apex-levelup-backdrop" id="apexLevelupBackdrop"></div>
<div class="apex-levelup-modal" id="apexLevelupModal">
    <div class="apex-levelup-header">
        <button class="apex-levelup-close" onclick="closeApexLevelup()" aria-label="Close" title="Close"></button>
        <h2 class="apex-levelup-title">🎉 Level Up! 🎉</h2>
    </div>
    <div class="apex-levelup-body">
        <video id="apexLevelupVideo" class="apex-levelup-video" playsinline loop>
            <source src="{$videourl}" type="video/mp4">
        </video>
    </div>
    <div class="apex-levelup-footer">
        <div class="apex-levelup-level">LEVEL {$level}</div>
        <div class="apex-levelup-subtitle">Keep climbing to the top!</div>
    </div>
</div>

<audio id="apexLevelupSound" preload="auto">
    <source src="{$soundurl}" type="audio/mpeg">
</audio>

<script>
(function() {
  var backdrop = document.getElementById('apexLevelupBackdrop');
  var modal = document.getElementById('apexLevelupModal');
  var video = document.getElementById('apexLevelupVideo');
  var sound = document.getElementById('apexLevelupSound');

  function showLevelup() {
    backdrop.style.display = 'block';
    modal.style.display = 'block';
    if(video) {
      video.volume = 0.7;
      video.currentTime = 0;
      video.play().catch(function(){});
    }
    if(sound) {
      sound.volume = 0.6;
      sound.currentTime = 0;
      sound.play().catch(function(){});
    }
  }

  window.closeApexLevelup = function() {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
    if(video) {
      video.pause();
      video.currentTime = 0;
    }
    // Check if there are more level-ups and reload to show next one
    checkForMoreLevelups();
  };

  function checkForMoreLevelups() {
    // Use AJAX to check if there are more pending level-ups
    var xhr = new XMLHttpRequest();
    xhr.open('GET', M.cfg.wwwroot + '/local/ascend_rewards/check_levelups.php', true);
    xhr.onload = function() {
      if(xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if(response.has_more) {
            // Reload page to show next level-up after a short delay
            setTimeout(function() {
              window.location.reload();
            }, 300);
          }
        } catch(e) {}
      }
    };
    xhr.send();
  }

  // Loop video 3 times
  var levelupLoopCount = 0;
  var levelupMaxLoops = 3;
  
  if(video) {
    video.addEventListener('ended', function() {
      levelupLoopCount++;
      if(levelupLoopCount < levelupMaxLoops) {
        video.currentTime = 0;
        video.play().catch(function(){});
      }
    });
  }

  backdrop.addEventListener('click', closeApexLevelup);

  // Make showLevelup globally accessible for badge modal to trigger
  window.showApexLevelup = showLevelup;
  
  console.log('Level-up modal initialized, checking for badge notification...');
  
  // Show level-up after page loads
  // Check if badge notification exists, only show immediately if no badge
  var hasBadgeNotif = document.getElementById('apexToast');
  
  if(!hasBadgeNotif) {
    // No badge notification, show level-up immediately
    console.log('No badge notification, showing level-up immediately');
    if(document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        setTimeout(showLevelup, 1000);
      });
    } else {
      setTimeout(showLevelup, 1000);
    }
  } else {
    console.log('Badge notification present, level-up will trigger after badges complete');
  }
  // If badge notification exists, it will trigger level-up when it closes

  // Auto-close after 25 seconds
  setTimeout(closeApexLevelup, 25000);
})();
</script>
HTML;
    
    return $output;
}
