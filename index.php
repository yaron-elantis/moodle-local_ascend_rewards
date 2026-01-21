<?php
/**
 * Apex Rewards - Main Dashboard Page
 *
 * Displays user's coin balance, badges, rank, course progress, and gamification elements.
 *
 * @package    local_ascend_rewards
 * @copyright  2025 Apex Rewards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_login();

// Include performance cache helper
require_once(__DIR__ . '/classes/performance_cache.php');

// Include gameboard class
require_once(__DIR__ . '/classes/gameboard.php');

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

// AJAX: leaderboard context
$apex_action = optional_param('apex_action', '', PARAM_ALPHANUMEXT);
if ($apex_action === 'get_leaderboard_context') {
    require_sesskey();
    $neighbors = optional_param('neighbors', 3, PARAM_INT);
    $courseid_param = optional_param('courseid', 0, PARAM_INT);
    header('Content-Type: application/json');

    $xp_cid = ($courseid_param > 0) ? $courseid_param : 0;
    
    $sql = "SELECT x.userid, x.xp
            FROM {local_ascend_xp} x
            JOIN {user} u ON u.id = x.userid
            WHERE x.courseid = :cid AND x.xp > 0
              AND u.suspended = 0 AND u.deleted = 0
            ORDER BY x.xp DESC, x.userid ASC";
    
    $rows = $DB->get_records_sql($sql, ['cid' => $xp_cid]);
    $ranked = [];
    $r = 1;
    foreach ($rows as $row) {
        $ranked[] = ['userid' => (int)$row->userid, 'xp' => (int)$row->xp, 'rank' => $r++];
    }

    $total = count($ranked);
    $myrank = null;
    foreach ($ranked as $u) {
        if ($u['userid'] === (int)$USER->id) {
            $myrank = $u['rank'];
            break;
        }
    }
    
    if ($myrank === null) {
        echo json_encode(['success' => true, 'users' => [], 'myrank' => null, 'start_rank' => 0, 'end_rank' => 0, 'total_users' => $total]);
        exit;
    }

    $start = max(1, $myrank - $neighbors);
    $end = min($total, $myrank + $neighbors);

    $context = [];
    for ($i = $start - 1; $i <= $end - 1; $i++) {
        if (!isset($ranked[$i])) continue;
        $item = $ranked[$i];
        $medal = ($item['rank'] === 1) ? '🥇' : (($item['rank'] === 2) ? '🥈' : (($item['rank'] === 3) ? '🥉' : '#'.$item['rank']));
        $context[] = ['userid' => $item['userid'], 'xp' => $item['xp'], 'rank' => $item['rank'], 'medal' => $medal, 'is_current_user' => ($item['userid'] === (int)$USER->id)];
    }

    echo json_encode(['success' => true, 'users' => $context, 'myrank' => $myrank, 'start_rank' => $start, 'end_rank' => $end, 'total_users' => $total]);
    exit;
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ascend_rewards/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('');
$PAGE->set_heading('');

// Course filter
$courseparam = optional_param('courseid', 'all', PARAM_RAW_TRIMMED);
$courseid = null;
if ($courseparam !== '' && strtolower($courseparam) !== 'all') {
    if (ctype_digit((string)$courseparam)) {
        $courseid = (int)$courseparam;
    }
}

// Helper functions
function apex_img(moodle_url $fallbackurl, string $pixkey, string $component = 'local_ascend_rewards'): moodle_url {
    global $OUTPUT;
    try {
        return $OUTPUT->image_url($pixkey, $component);
    } catch (Throwable $e) {
        return $fallbackurl;
    }
}

function apex_normalize_badge_name(string $name): string {
    return (strcasecmp($name, 'Assignment Ace') === 0) ? 'Assessment Ace' : $name;
}

function apex_medal_for_place(int $place): string {
    return match ($place) {
        1       => '1st',
        2       => '2nd',
        3       => '3rd',
        default => $place . '.',
    };
}

function apex_fmt_date(int $ts): string {
    return userdate($ts, '%d %b %Y %H:%M');
}

/**
 * Analyze peer performance for competitive context
 */
function apex_analyze_peer_performance(int $userid, int $courseid = 0): array {
    global $DB;
    
    // Get user's XP
    $xp_field = ($courseid > 0) ? 'courseid' : 'courseid = 0';
    $user_xp = (int)$DB->get_field_sql(
        "SELECT FLOOR(SUM(coins)/2) FROM {local_ascend_rewards_coins} 
         WHERE userid = :uid AND ($xp_field" . ($courseid > 0 ? " = :cid OR courseid IS NULL" : "") . ")",
        ['uid' => $userid, 'cid' => $courseid]
    ) ?: 0;
    
    // Get percentile
    $count_above = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid) FROM (
            SELECT userid, FLOOR(SUM(coins)/2) as xp 
            FROM {local_ascend_rewards_coins}
            WHERE " . ($courseid > 0 ? "courseid = :cid OR courseid IS NULL" : "courseid = 0") . "
            GROUP BY userid
            HAVING FLOOR(SUM(coins)/2) > :userxp
        ) subq",
        ['cid' => $courseid, 'userxp' => $user_xp]
    );
    
    $total_users = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid) FROM {local_ascend_rewards_coins} 
         WHERE " . ($courseid > 0 ? "courseid = :cid OR courseid IS NULL" : "courseid = 0"),
        ['cid' => $courseid]
    );
    
    $percentile = $total_users > 0 ? round((($total_users - $count_above) / $total_users) * 100) : 0;
    
    // Get rank and nearby competitors
    $rank = $count_above + 1;
    
    $peers = $DB->get_records_sql(
        "SELECT userid, FLOOR(SUM(coins)/2) as xp 
         FROM {local_ascend_rewards_coins}
         WHERE " . ($courseid > 0 ? "courseid = :cid OR courseid IS NULL" : "courseid = 0") . "
         GROUP BY userid
         ORDER BY xp DESC, userid ASC
         LIMIT 100",
        ['cid' => $courseid]
    );
    
    // Build competitive insight
    $competitive_insight = "#$rank of " . count($peers) . " learners";
    if ($rank <= 3) {
        $competitive_insight .= " – You're in the top tier! 🥇";
    } elseif ($rank <= count($peers) / 4) {
        $competitive_insight .= " – Strong position in the top quartile";
    }
    
    return [
        'user_xp' => $user_xp,
        'rank' => $rank,
        'percentile' => $percentile,
        'competitive_insight' => $competitive_insight,
        'peer_badge_comparison' => []
    ];
}

/**
 * Analyze badge eligibility and availability
 */
function apex_analyze_badge_logic(int $userid, int $courseid): array {
    global $DB;
    
    $result = [];
    
    // Check which badges have been earned in this course
    $earned = $DB->get_records_sql(
        "SELECT DISTINCT badgeid FROM {local_ascend_rewards_coins} 
         WHERE userid = :uid AND badgeid > 0 AND (courseid = :cid OR courseid IS NULL)",
        ['uid' => $userid, 'cid' => $courseid]
    );
    
    $earned_ids = array_keys($earned);
    
    $result['earned'] = $earned_ids;
    $result['eligible_now'] = [];
    $result['time_sensitive'] = [];
    $result['needs_action'] = [];
    
    return $result;
}

/**
 * Analyze learner behavior patterns from badge data
 */
function apex_analyze_learner_behavior(int $userid, int $courseid): array {
    global $DB;
    
    $behaviors = [
        'patterns' => [],
        'strengths' => [],
        'struggles' => [],
        'recommendations' => []
    ];
    
    // Analyze earned badges to infer patterns
    $earned = $DB->get_records_sql(
        "SELECT badgeid, COUNT(*) as cnt FROM {local_ascend_rewards_coins}
         WHERE userid = :uid AND badgeid > 0 AND (courseid = :cid OR courseid IS NULL)
         GROUP BY badgeid",
        ['uid' => $userid, 'cid' => $courseid]
    );
    
    $badge_map = [
        4 => 'On a Roll', 6 => 'Getting Started', 5 => 'Halfway Hero',
        9 => 'Early Bird', 11 => 'Sharp Shooter', 10 => 'Deadline Burner',
        13 => 'Feedback Follower', 14 => 'Tenacious Tiger', 15 => 'Steady Improver',
        17 => 'Assessment Ace', 19 => 'High Flyer'
    ];
    
    if (!empty($earned)) {
        $behaviors['strengths'][] = "You've earned " . count($earned) . " badges—consistent performer";
        if (isset($earned[9]) || isset($earned[10])) {
            $behaviors['patterns'][] = "Time-conscious: You plan ahead and meet deadlines";
        }
        if (isset($earned[14]) || isset($earned[15])) {
            $behaviors['patterns'][] = "Growth-minded: You iterate and improve from feedback";
        }
        if (isset($earned[11])) {
            $behaviors['patterns'][] = "Quality-focused: You prioritize mastery over speed";
        }
    }
    
    return $behaviors;
}





/**
 * Build a rich, personalized coaching prompt based on REAL learner behavior and earned badges.
 */
function apex_build_behavioral_coaching_prompt(
  string $user_name,
  string $course_name,
  int $completed,
  int $total,
  int $remaining,
  int $weekly_completed,
  int $streak,
  ?string $grading_type,
  ?float $score_pct,
  ?string $ontrack,
  ?int $days_left,
  string $badge_context,
  array $incomplete_list,
  bool $course_complete,
  array $learner_behavior,
  array $peer_data,
  array $badge_eligibility
): string {
  
  $completion_pct = $total > 0 ? round(($completed / $total) * 100) : 0;
  
  $prompt = "You are an awesome personal learning coach. Provide genuine, personalized encouragement based on REAL achievements and learning style (2-3 sentences max, 60-80 words).\n\n";
  $prompt .= "LEARNER PROFILE:\n";
  $prompt .= "- Name: $user_name | Course: $course_name\n";
  $prompt .= "- Progress: $completed/$total activities ($completion_pct% complete)\n";
  $prompt .= "- Remaining: $remaining activities\n";
  
  // Add their REAL behavior insights
  if (!empty($learner_behavior['patterns'])) {
    $prompt .= "\nREAL BEHAVIOR PATTERNS (from their actual earned badges):\n";
    foreach (array_slice($learner_behavior['patterns'], 0, 2) as $pattern) {
      $prompt .= "• $pattern\n";
    }
  }
  
  // Add their earned badges as proof of competence
  if (!empty($learner_behavior['strengths'])) {
    $prompt .= "\nACHIEVEMENTS:\n";
    foreach (array_slice($learner_behavior['strengths'], 0, 2) as $strength) {
      $prompt .= "• $strength\n";
    }
  }
  
  $prompt .= "\nCOACHING APPROACH:\n";
  
  if ($course_complete) {
    $prompt .= "- Course is COMPLETE! Write a genuine celebration that honors their unique learning journey\n";
    if (!empty($learner_behavior['patterns'])) {
      $prompt .= "- Reference their behavior pattern above (e.g., 'Your " . strtolower($learner_behavior['patterns'][0]) . " is what made you succeed')\n";
    }
    $prompt .= "- Specific and warm—no generic platitudes\n";
  } elseif ($remaining <= 3) {
    $prompt .= "- They're in the FINAL STRETCH with just $remaining activities left\n";
    $prompt .= "- Celebrate their momentum and push them across the finish line\n";
    if (!empty($learner_behavior['patterns'])) {
      $prompt .= "- Reference how their behavior pattern (e.g., " . strtolower($learner_behavior['patterns'][0]) . ") shows they can finish strong\n";
    }
    $prompt .= "- 2-3 sentences, warm and encouraging\n";
  } elseif ($completion_pct >= 75) {
    $prompt .= "- They're OVER 75% DONE—major accomplishment!\n";
    $prompt .= "- Acknowledge what got them here (their behavior pattern/strengths above)\n";
    $prompt .= "- Encourage them to maintain momentum to the finish\n";
  } elseif ($completion_pct >= 50) {
    $prompt .= "- They've hit the HALFWAY MILESTONE—celebrate this!\n";
    $prompt .= "- Reference their behavior pattern as what's carrying them forward\n";
    $prompt .= "- Build confidence that they're on the right track\n";
  } else {
    $prompt .= "- They're BUILDING FOUNDATION KNOWLEDGE—early in the journey\n";
    if (!empty($learner_behavior['strengths'])) {
      $prompt .= "- Reference their early achievements to show they're on the right track\n";
    }
    $prompt .= "- Build momentum and excitement for what's ahead\n";
  }
  
  $prompt .= "\nTONE: Warm, specific, genuinely encouraging. Reference REAL behavior patterns and earned achievements—NOT generic motivation.";
  
  return $prompt;
}

/**
 * Call Gemini AI API safely
 */
function apex_call_grok_coaching(string $prompt): ?string {
    global $CFG, $USER;
    
    $api_key = get_config('local_ascend_rewards', 'gemini_api_key');
    if (empty($api_key)) {
        return null;
    }
    
    try {
        $curl = new \curl();
        
        $curl->setopt(array(
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => false,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10
        ));
        
        $curl->setHeader('Content-Type: application/json');
        
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => "You are Apex Coach - a perceptive, personalized mentor.\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 300,
                'topP' => 0.8,
                'topK' => 40
            ]
        ]);
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($api_key);
        $response = $curl->post($url, $payload);
        
        if ($response === false) {
            return null;
        }
        
        if (empty($response)) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null) {
            return null;
        }
        
        if (isset($data['error'])) {
            return null;
        }
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($data['candidates'][0]['content']['parts'][0]['text']);
            return ($text === '[NO_CHANGE]') ? null : $text;
        }
        
        return null;
    } catch (Exception $e) {
        $_SESSION['gemini_debug'][$USER->id] = 'Exception: ' . $e->getMessage();
        return null;
    }
}

/**
 * Check if coaching should be regenerated based on progress and time
 */
function apex_should_regenerate_coaching(int $userid, int $courseid, float $current_progress): bool {
    // Always regenerate for now - preference caching not available in this context
    return true;
}

/**
 * Get cached coaching response or null if expired
 */
function apex_get_cached_coaching(int $userid, int $courseid): ?string {
    // Caching not available in this context
    return null;
}

/**
 * Store coaching response in cache
 */
function apex_cache_coaching_response(int $userid, int $courseid, float $progress, string $response): void {
    // Caching not available in this context
}

/**
 * Get AI coaching insights - with proper fallback
 */
function apex_get_coaching_insights(object $journey, object $user): string {
    global $DB;
    
    try {
        $courseid = (int)$journey->courseid;
        $userid = (int)$user->id;
        $progress_pct = (float)$journey->progress;
        
        // Check if coaching should regenerate
        if (!apex_should_regenerate_coaching($userid, $courseid, $progress_pct)) {
            $cached = apex_get_cached_coaching($userid, $courseid);
            if ($cached) {
                return $cached;
            }
        }
        
        // Get course
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return apex_fallback_coaching($journey);
        }
        
        // Gather data
        $completed = (int)$journey->completed_count;
        $total = (int)$journey->total_count;
        $muser = $DB->get_record('user', ['id' => $userid]);
        $user_name = fullname($muser);
        
        // Run analysis
        $learner_behavior = apex_analyze_learner_behavior($userid, $courseid);
        $peer_data = apex_analyze_peer_performance($userid, $courseid);
        $badge_eligibility = apex_analyze_badge_logic($userid, $courseid);
        
        // Build prompt
        $prompt = apex_build_behavioral_coaching_prompt(
            $user_name, $course->fullname, $completed, $total, $total - $completed,
            0, 0, null, null, null, null, '', [], 
            $progress_pct >= 100, $learner_behavior, $peer_data, $badge_eligibility
        );
        
        // Call Grok
        $response = apex_call_grok_coaching($prompt);
        
        if ($response) {
            $html = '<div class="coaching-box"><div class="coaching-header">Personal Coach</div><p>' . htmlspecialchars($response) . '</p></div>';
            apex_cache_coaching_response($userid, $courseid, $progress_pct, $html);
            return $html;
        }
        
        // Fallback
        $fallback = apex_fallback_coaching($journey);
        apex_cache_coaching_response($userid, $courseid, $progress_pct, $fallback);
        return $fallback;
        
    } catch (Exception $e) {
        return apex_fallback_coaching($journey);
    }
}

/**
 * Fallback coaching messages when AI is unavailable
 */
function apex_fallback_coaching(object $journey): string {
    $progress_pct = round($journey->progress, 0);
    $completed = (int)$journey->completed_count;
    $total = (int)$journey->total_count;

    if ($progress_pct >= 100) {
        $message = "🎉 Course Complete! You've finished all activities.";
    } elseif ($progress_pct >= 75) {
        $remaining = $total - $completed;
        $message = "🔥 Final stretch! Just $remaining activities left.";
    } elseif ($progress_pct >= 50) {
        $message = "📈 Halfway there! Keep up the momentum!";
    } else {
        $message = "🚀 You're making progress! Keep going!";
    }

    return '<div class="coaching-box"><div class="coaching-header">Your Progress</div><p>' . htmlspecialchars($message) . '</p></div>';
}

/**
 * Generate squiggly path SVG for course journey.
 * Creates a wavy path with waypoints and colored segments based on completion.
 */
function apex_generate_journey_svg(object $journey, int $width = 1000, int $height = 200): string {
    $waypoint_count = count($journey->waypoints);
    if ($waypoint_count === 0) return '';
    
    $padding = 40;
    $usable_width = $width - (2 * $padding);
    $waypoint_spacing = $usable_width / max(1, ($waypoint_count - 1));
    
    // Generate squiggly path using cubic bezier curves
    $path_points = [];
    for ($i = 0; $i < $waypoint_count; $i++) {
        $x = $padding + ($i * $waypoint_spacing);
        // Create up-down wave pattern
        $y = ($i % 2 === 0) ? $height / 2 - 20 : $height / 2 + 20;
        $path_points[] = ['x' => $x, 'y' => $y, 'index' => $i];
    }
    
    // Build SVG
    $svg = '<svg class="journey-svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="120" preserveAspectRatio="xMidYMid meet">';
    $svg .= '<defs><linearGradient id="journeyGradient" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '<stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" />';
    $svg .= '</linearGradient></defs>';
    // Color cycle for waypoints and path segments: cyan, pink, orange
    $colors = ['#00D4FF', '#FF00AA', '#FF9500'];

    // Add start.png image above the first waypoint
    if (!empty($path_points)) {
      $first = $path_points[0];
      $img_width = 38; // px
      $img_height = 38; // px
      $img_x = $first['x'] - $img_width / 2;
      $img_y = $first['y'] - 32 - $img_height; // 32px above the waypoint
      $start_img_url = (new moodle_url('/local/ascend_rewards/pix/start.png'))->out(false);
      $svg .= '<image href="' . htmlspecialchars($start_img_url) . '" xlink:href="' . htmlspecialchars($start_img_url) . '" x="' . $img_x . '" y="' . $img_y . '" width="' . $img_width . '" height="' . $img_height . '" />';

      // Add finish.png image above the last waypoint
      $last = $path_points[count($path_points)-1];
      $finish_img_url = (new moodle_url('/local/ascend_rewards/pix/finish.png'))->out(false);
      $img_x2 = $last['x'] - $img_width / 2;
      $img_y2 = $last['y'] - 32 - $img_height; // 32px above the waypoint
      $svg .= '<image href="' . htmlspecialchars($finish_img_url) . '" xlink:href="' . htmlspecialchars($finish_img_url) . '" x="' . $img_x2 . '" y="' . $img_y2 . '" width="' . $img_width . '" height="' . $img_height . '" />';
    }

    
    // Draw path segments with different colors for completed/incomplete
    for ($i = 0; $i < count($path_points) - 1; $i++) {
        $p1 = $path_points[$i];
        $p2 = $path_points[$i + 1];
        $all_prev_complete = true;
        
        // Check if this segment should be colored (all previous waypoints complete)
        for ($j = 0; $j <= $i; $j++) {
            if (!$journey->waypoints[$j]->completed) {
                $all_prev_complete = false;
                break;
            }
        }
        
        // Use cubic bezier for smooth wave
        $cx1 = $p1['x'] + ($p2['x'] - $p1['x']) / 3;
        $cy1 = $p1['y'];
        $cx2 = $p1['x'] + 2 * ($p2['x'] - $p1['x']) / 3;
        $cy2 = $p2['y'];
        
        if ($all_prev_complete) {
          // color segments in sequence: cyan, pink, orange
          $segcolor = $colors[$i % count($colors)];
          $stroke_color = $segcolor;
          $stroke_width = '8';
        } else {
          $stroke_color = 'rgba(255,255,255,0.2)';
          $stroke_width = '4';
        }
        
        $svg .= '<path d="M ' . $p1['x'] . ' ' . $p1['y'] . ' C ' . $cx1 . ' ' . $cy1 . ' ' . $cx2 . ' ' . $cy2 . ' ' . $p2['x'] . ' ' . $p2['y'] . '" ';
        $svg .= 'stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" fill="none" stroke-linecap="round" />';
    }
    
    // Draw waypoints (circles) with cycling colors (cyan -> pink -> orange)
    $completed_index = -1;
    
    // Find the index of last completed waypoint
    for ($i = count($journey->waypoints) - 1; $i >= 0; $i--) {
        if ($journey->waypoints[$i]->completed) {
            $completed_index = $i;
            break;
        }
    }
    
    foreach ($path_points as $p) {
        $wp = $journey->waypoints[$p['index']];
        $waypoint_index = $p['index'];
        
        // Determine color: cycle through colors for completed waypoints
        $fill_color = 'rgba(255,255,255,0.2)'; // Default pending color
        $circle_class = 'waypoint-pending';
        
        if ($wp->completed) {
            // Assign colors in cycling sequence: 0->cyan, 1->pink, 2->orange, 3->cyan, etc.
            $color_index = $waypoint_index % 3;
            $fill_color = $colors[$color_index];
            $circle_class = 'waypoint-completed';
        }
        
        // Create a group for the waypoint with tooltip
        $svg .= '<g class="waypoint-group">';
        $svg .= '<title>' . htmlspecialchars($wp->activity_name) . '</title>';
        $svg .= '<circle cx="' . $p['x'] . '" cy="' . $p['y'] . '" r="12" fill="' . $fill_color . '" class="journey-waypoint ' . $circle_class . '" />';
        
        // Add a lighter circle for incomplete waypoints
        if (!$wp->completed) {
            $svg .= '<circle cx="' . $p['x'] . '" cy="' . $p['y'] . '" r="10" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" />';
        }
        $svg .= '</g>';
    }

    
    $svg .= '</svg>';
    return $svg;
}

/** ------------------------------------------------------------------------
 *  PIX / BADGE CONFIG
 *  --------------------------------------------------------------------- */
$coin_fallback  = new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png');
$stack_fallback = new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png');

$coinimgurl  = apex_img($coin_fallback, 'ascend_coin_main');
$stackimgurl = apex_img($stack_fallback, 'ascend_assets_stack');

$medal_gold_url   = apex_img(new moodle_url('/local/ascend_rewards/pix/medal_gold.png'), 'medal_gold');
$medal_silver_url = apex_img(new moodle_url('/local/ascend_rewards/pix/medal_silver.png'), 'medal_silver');
$medal_bronze_url = apex_img(new moodle_url('/local/ascend_rewards/pix/medal_bronze.png'), 'medal_bronze');

$pixbase = new moodle_url('/local/ascend_rewards/pix');

// Section header icons
$icon_leaderboard_url      = apex_img(new moodle_url('/local/ascend_rewards/pix/leaderboard.png'), 'leaderboard');
$icon_badges_course_url   = apex_img(new moodle_url('/local/ascend_rewards/pix/badges_course.png'), 'badges_course');
$icon_challenges_url      = apex_img(new moodle_url('/local/ascend_rewards/pix/mystery_box.png'), 'challenge');
$icon_journey_url         = apex_img(new moodle_url('/local/ascend_rewards/pix/journey.png'), 'journey');

/**
 * Badge → icon filename mapping.
 * PNG images are in the root pix folder with lowercase underscore filenames.
 */
$badge_images = [
    'Getting Started'   => 'getting_started.png',
    'Halfway Hero'      => 'halfway_hero.png',
    'Early Bird'        => 'early_bird.png',
    'High Flyer'        => 'high_flyer.png',
    'Feedback Follower' => 'feedback_follower.png',
    'Deadline Burner'   => 'deadline_burner.png',
    'Time Tamer'        => 'time_tamer.png',
    'Master Navigator'  => 'master_navigator.png',
    'Glory Guide'       => 'glory_guide.png',
    'On a Roll'         => 'on_a_roll.png',
    'Tenacious Tiger'   => 'tenacious_tiger.png',
    'Steady Improver'   => 'steady_improver.png',
    'Sharp Shooter'     => 'sharp_shooter.png',
    'Learning Legend'   => 'learning_legend.png',
    'Assessment Ace'    => 'activity_ace.png',
    'Mission Complete'  => 'mission_complete.png',
];

/**
 * Badge definitions (name → badgeid).
 */
$badge_definitions = [
    'Getting Started'   => 6,
    'Halfway Hero'      => 5,
    'On a Roll'         => 4,
    'Master Navigator'  => 8,
    'Early Bird'        => 9,
    'Sharp Shooter'     => 11,
    'Deadline Burner'   => 10,
    'Time Tamer'        => 12,
    'Feedback Follower' => 13,
    'Steady Improver'   => 15,
    'Tenacious Tiger'   => 14,
    'Glory Guide'       => 16,
    'High Flyer'        => 19,
    'Assessment Ace'    => 17,
    'Mission Complete'  => 7,
    'Learning Legend'   => 20,
];

/**
 * Badge descriptions for modal / tooltips.
 */
$badge_descriptions = [
    'Getting Started'   => 'Awarded after completing the first activity in a course = 50',
    'On a Roll'         => 'Awarded for completing 2 consecutive activities = 150',
    'Halfway Hero'      => 'Completed 50% of course activities = 250',
    'Master Navigator'  => 'Gold Badge - obtain 2 x badges in the Progress Based Badges Category = 600',
    'Early Bird'        => 'Complete an activity 24 hours before the deadline = 100',
    'Sharp Shooter'     => 'Complete 2 consecutive activities before the deadline = 200',
    'Deadline Burner'   => 'Complete all course activities before the deadline = 300',
    'Time Tamer'        => 'Gold Badge - Awarded for obtaining 2 x badges in the Timeline Badges Category = 600',
    'Feedback Follower' => 'Improve your grade in an activity = 100',
    'Steady Improver'   => 'Fail and then pass an activity = 200',
    'Tenacious Tiger'   => 'Improve your grade in 2 activities = 250',
    'Glory Guide'       => 'Gold Badge - Obtain any 2 badges in the Quality-Based & Growth/Improvement Badges Category = 600',
    'High Flyer'        => 'Pass 2 consecutive activities on first attempt = 300',
    'Assessment Ace'    => 'Submit all course activities with a pass on the first attempt = 400',
    'Mission Complete'  => 'All course activities completed = 500',
    'Learning Legend'   => 'Super Gold Badge - Obtain any 2 badges in the Course Completion & Mastery Badges = 1000',
];

/**
 * Resolve a badge icon URL.
 */
function apex_badge_icon_url(string $badgename, array $map, moodle_url $pixbase, moodle_url $fallback): moodle_url {
    $name = apex_normalize_badge_name($badgename);
    $filename = $map[$name] ?? null;
    if ($filename) {
        return new moodle_url('/local/ascend_rewards/pix/' . $filename);
    }
    return $fallback;
}

/** ------------------------------------------------------------------------
 *  USER PARAMS / WHERE FRAGMENTS
 *  --------------------------------------------------------------------- */
$userparam = ['uid' => $USER->id];

$where_course_c   = is_null($courseid) ? "" : " AND c.courseid = :cid ";
$where_course_cns = is_null($courseid) ? "" : " AND cns.courseid = :cid ";
$where_course_lb  = is_null($courseid) ? "" : " AND courseid = :cid";

if (!is_null($courseid)) {
    $userparam['cid'] = $courseid;
}

/** ------------------------------------------------------------------------
 *  TOTAL COINS (GLOBAL) & BALANCE PER COURSE - Including Gameboard Coins
 *  --------------------------------------------------------------------- */
// All coins are now in the unified local_ascend_rewards_coins table
// This includes badge coins, gameboard coins, and any other coin earnings
// Count only positive coins (earned), not negative coins (spent)
$totalcoins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid AND coins > 0",
    ['uid' => $USER->id]
);

// Get user's actual spendable balance (positive earnings minus spending)
$coin_balance = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid",
    ['uid' => $USER->id]
);

// If a test-only coin offset is set (for testing), add it to the displayed balance.
// This does NOT insert a ledger record; it's only for UI/testing purposes.
$test_offset = 0;
try {
  $pref = get_user_preferences('ascend_test_coins', '', $USER->id);
  if ($pref !== '') {
    $test_offset = (int)$pref;
  }
} catch (Exception $e) {
  // ignore
}

$coin_balance += $test_offset;

$balance_course = null;
if (!is_null($courseid)) {
    $balance_course = (int)$DB->get_field_sql(
        "SELECT COALESCE(SUM(coins), 0)
           FROM {local_ascend_rewards_coins}
          WHERE userid = :uid
            AND courseid = :cid
            AND coins > 0",
        ['uid' => $USER->id, 'cid' => $courseid]
    );
}

/** ------------------------------------------------------------------------
 *  TOTAL BADGES (INCLUDING REPEATABLE BADGES)
 *  --------------------------------------------------------------------- */
$totalbadges = (int)$DB->get_field_sql(
    "SELECT COUNT(*)
       FROM {local_ascend_rewards_coins} c
      WHERE c.userid = :uid
        AND c.badgeid > 0 {$where_course_c}",
    $userparam
);

/** ------------------------------------------------------------------------
 *  RANKING: Uses local_ascend_xp table - XP is SEPARATE from coins
 *  XP never decreases. Rankings are ALWAYS based on XP.
 *  --------------------------------------------------------------------- */

// Determine which XP to use: course-specific or site-wide (courseid=0)
$xp_courseid = is_null($courseid) ? 0 : $courseid;

// Use cached user stats for better performance
$user_stats = \local_ascend_rewards\performance_cache::get_user_stats($USER->id, $xp_courseid);
$user_xp = $user_stats['xp'];

// Get total earners (users with XP > 0 in this scope)
$totalearners = (int)$DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_ascend_xp} WHERE courseid = :cid AND xp > 0",
    ['cid' => $xp_courseid]
);

// Calculate user's rank using cached method
$myrank = \local_ascend_rewards\performance_cache::get_user_rank($USER->id, $xp_courseid, $user_xp);

// Also get site-wide rank for display when course is filtered
$site_user_stats = \local_ascend_rewards\performance_cache::get_user_stats($USER->id, 0);
$site_user_xp = $site_user_stats['xp'];

$site_totalearners = (int)$DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_ascend_xp} WHERE courseid = 0 AND xp > 0"
);

$site_myrank = \local_ascend_rewards\performance_cache::get_user_rank($USER->id, 0, $site_user_xp);

/** ------------------------------------------------------------------------
 *  TOP 10 LEADERBOARD - uses local_ascend_xp table (cached)
 *  --------------------------------------------------------------------- */
$top10 = \local_ascend_rewards\performance_cache::get_leaderboard($xp_courseid, 10);

// Check if current user is in top 10, if not, get their data separately
$user_in_top10 = false;
foreach ($top10 as $row) {
    if ((int)$row->userid === (int)$USER->id) {
        $user_in_top10 = true;
        break;
    }
}

$current_user_data = null;
if (!$user_in_top10 && $user_xp > 0) {
    // Get current user's data
    $current_user_data = (object)[
        'userid' => (int)$USER->id,
        'xp' => $user_xp,
        'rank' => $myrank
    ];
}

/** ------------------------------------------------------------------------
 *  COURSE DROPDOWN OPTIONS
 *  --------------------------------------------------------------------- */
$courseoptions = [];

$course_rows = $DB->get_records_sql("
    SELECT DISTINCT cns.courseid, crs.fullname AS coursename
      FROM {local_ascend_rewards_coins} cns
      LEFT JOIN {course} crs ON crs.id = cns.courseid
     WHERE cns.userid = :uid AND cns.courseid > 1
     ORDER BY crs.fullname ASC
", ['uid' => $USER->id]);

foreach ($course_rows as $r) {
    $courseoptions[(string)(int)$r->courseid] = $r->coursename;
}

$selectedcoursename = (!is_null($courseid) && isset($courseoptions[(string)$courseid]))
    ? $courseoptions[(string)$courseid]
    : null;

/** ------------------------------------------------------------------------
 *  BADGES BY COURSE
 *  --------------------------------------------------------------------- */
$badgenames_map_full = array_flip($badge_definitions);
$paramscourse        = $userparam;

$sql_course = "SELECT cns.id,
                      cns.badgeid,
                      cns.coins,
                      cns.courseid,
                      cns.timecreated,
                      crs.fullname AS coursename
                 FROM {local_ascend_rewards_coins} cns
                 LEFT JOIN {course} crs ON crs.id = cns.courseid
                WHERE cns.userid = :uid {$where_course_cns} AND cns.courseid > 0
             ORDER BY crs.fullname ASC, cns.timecreated DESC";

$rows_course = $DB->get_records_sql($sql_course, $paramscourse);

$bycourse = [];
foreach ($rows_course as $r) {
    $name  = $badgenames_map_full[(int)$r->badgeid] ?? 'Coin Transaction';
    $name  = apex_normalize_badge_name($name);
    $bucket = $r->coursename;
    if (!$bucket) continue;  // Skip if no course name
    if (!isset($bycourse[$bucket])) {
        $bycourse[$bucket] = [];
    }
    $r->badgename_display = $name;
    $r->icon_url          = apex_badge_icon_url($name, $badge_images, $pixbase, $coinimgurl)->out(false);
    $r->formatted_date    = apex_fmt_date((int)$r->timecreated);
    $r->coins_text        = (((int)$r->coins) > 0 ? '+' : '') . (int)$r->coins;
    $bycourse[$bucket][]  = $r;
}

// Course journeys - only load for specific course to improve performance
$journeys = [];

try {
    // Only fetch journey data if a specific course is selected
    if (!is_null($courseid) && $courseid > 0) {
        // Get the specific course
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        
        if ($course) {
            // Get course modules with completion tracking and actual activity names
            $modules_sql = "SELECT cm.id, cm.module, cm.instance, cm.section, m.name as modname,
                                   COALESCE(cmc.completionstate, 0) as completionstate,
                                   CASE 
                                       WHEN m.name = 'assign' THEN (SELECT name FROM {assign} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'quiz' THEN (SELECT name FROM {quiz} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'forum' THEN (SELECT name FROM {forum} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'lesson' THEN (SELECT name FROM {lesson} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'scorm' THEN (SELECT name FROM {scorm} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'choice' THEN (SELECT name FROM {choice} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'feedback' THEN (SELECT name FROM {feedback} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'resource' THEN (SELECT name FROM {resource} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'url' THEN (SELECT name FROM {url} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'page' THEN (SELECT name FROM {page} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'book' THEN (SELECT name FROM {book} WHERE id = cm.instance LIMIT 1)
                                       WHEN m.name = 'h5pactivity' THEN (SELECT name FROM {h5pactivity} WHERE id = cm.instance LIMIT 1)
                                       ELSE NULL
                                   END as activity_name
                            FROM {course_modules} cm
                            JOIN {modules} m ON m.id = cm.module
                            LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :uid
                            WHERE cm.course = :cid AND cm.completion > 0
                            ORDER BY cm.section ASC, cm.id ASC";
            
            $waypoints = $DB->get_records_sql($modules_sql, ['uid' => $USER->id, 'cid' => $course->id]);
            
            if (!empty($waypoints)) {
                $journey_waypoints = [];
                $completed_count = 0;
                
                foreach ($waypoints as $wp) {
                    $is_completed = (int)$wp->completionstate > 0;
                    if ($is_completed) $completed_count++;
                    
                    // Use activity_name if available, otherwise use module type
                    $display_name = !empty($wp->activity_name) ? $wp->activity_name : $wp->modname;
                    
                    $journey_waypoints[] = (object)[
                        'id' => $wp->id,
                        'module' => $wp->modname,
                        'activity_name' => $display_name,
                        'completed' => $is_completed,
                        'completionstate' => (int)$wp->completionstate,
                    ];
                }
                
                $journeys[$course->id] = (object)[
                    'courseid' => $course->id,
                    'coursename' => $course->fullname,
                    'waypoints' => $journey_waypoints,
                    'progress' => count($journey_waypoints) > 0 ? ($completed_count / count($journey_waypoints)) * 100 : 0,
                    'completed_count' => $completed_count,
                    'total_count' => count($journey_waypoints),
                ];
            }
        }
    }
} catch (Exception $e) {
    $journeys = [];
} catch (Throwable $t) {
    $journeys = [];
}

/** ------------------------------------------------------------------------
 *  XP / LEVEL CALC - Use cached stats (NEVER decreases)
 *  --------------------------------------------------------------------- */
// Reuse site-wide stats we already fetched above
$xp = $site_user_xp;

// Calculate badge coins for coin balance (separate from XP)
$badge_coins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid AND badgeid > 0 AND coins > 0",
    ['uid' => $USER->id]
);

// Check for XP Multiplier (applied when badges are awarded, not here)
$xp_multiplier_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);
$xp_multiplier_active = ($xp_multiplier_end > time());

$level              = (int)floor($xp / 1000);
$xp_for_current_lvl = $level * 1000;
$xp_for_next_lvl    = ($level + 1) * 1000;
$xp_progress        = ($level > 0) ? ($xp - $xp_for_current_lvl) : $xp;
$xp_needed          = $xp_for_next_lvl - $xp_for_current_lvl;
$xp_percent         = ($xp_needed > 0 ? ($xp_progress / $xp_needed) * 100 : 100);

$last_level_up = (int)get_user_preferences('ascend_level_up_time', 0, $USER->id);
$show_level_up = ($last_level_up > 0 && (time() - $last_level_up) <= 7 * DAYSECS);

/** AI Challenge feature removed - disabled for plugin cleanup */

/** ------------------------------------------------------------------------
 *  BADGE CATEGORIES (FOR DISPLAY)
 *  --------------------------------------------------------------------- */
$badge_categories = [
    'Getting Started'   => 'Progress-Based',
    'Halfway Hero'      => 'Progress-Based',
    'On a Roll'         => 'Progress-Based',
    'Master Navigator'  => 'Progress-Based',
    'Early Bird'        => 'Timeliness & Discipline',
    'Sharp Shooter'     => 'Timeliness & Discipline',
    'Deadline Burner'   => 'Timeliness & Discipline',
    'Time Tamer'        => 'Timeliness & Discipline',
    'Feedback Follower' => 'Quality & Growth',
    'Steady Improver'   => 'Quality & Growth',
    'Tenacious Tiger'   => 'Quality & Growth',
    'Glory Guide'       => 'Quality & Growth',
    'High Flyer'        => 'Course Mastery',
    'Assessment Ace'    => 'Course Mastery',
    'Mission Complete'  => 'Course Mastery',
    'Learning Legend'   => 'Course Mastery',
];

/** ------------------------------------------------------------------------
 *  CELEBRATION FLAG
 *  --------------------------------------------------------------------- */
$celebratesince   = (int)get_user_preferences('ascendassets_lastcoin', 0, $USER->id);
$showcelebration  = ($celebratesince > 0 && (time() - $celebratesince) <= 7 * DAYSECS);

// Compute whether the current user has earned badges this week (gameboard logic)
try {
  $has_earned = local_ascend_rewards_gameboard::has_earned_badges_this_week($USER->id);
} catch (Exception $e) {
  $has_earned = false;
}

/** ------------------------------------------------------------------------
 *  RENDER PAGE
 *  --------------------------------------------------------------------- */
/** Render Page */
echo $OUTPUT->header();
?>
<style>
  :root {
    --aa-font: 'Montserrat', 'Segoe UI', sans-serif;
    --aa-gap: 16px;
    --aa-pad: 16px;
    --aa-card-radius: 12px;
    --aa-shadow: 0 4px 12px rgba(0,0,0,0.3);
  }

body#page-local-ascend_rewards-index #region-main{max-width:100%!important;padding-top:0!important;background:transparent;}
.path-local-ascend_rewards{background:#010828!important;font-family:var(--aa-font);}
  .path-local-ascend_rewards #page-debug,.path-local-ascend_rewards .debug,.path-local-ascend_rewards .notifytiny{display:none!important;}
  
  /* Main wrapper */
  .aa-wrap{max-width:1400px;margin:0 auto;padding:12px;color:#e6e9f0;}
  
  /* Base card styles */
  .aa-filter-bar,.aa-congrats,.section--instructions,.aa-card,.aa-panel,.aa-balance-bar,.aa-rank-bar{
    background:linear-gradient(to bottom,#01142E 0%,#010828 100%);
    border:1px solid rgba(255,255,255,.06);
    border-radius:var(--aa-card-radius);
    box-shadow:var(--aa-shadow);
    padding:var(--aa-pad);
    margin-bottom:var(--aa-gap);
  }
  
  /* Balance and Rank bars */
  .aa-balance-rank-container{display:grid;grid-template-columns:1fr 1fr;gap:var(--aa-gap);margin-bottom:var(--aa-gap);}
  .aa-balance-bar,.aa-rank-bar{display:flex;flex-direction:column;align-items:center;text-align:center;}
  .aa-balance-bar img,.aa-rank-bar img{height:60px;width:auto;object-fit:contain;margin-bottom:12px;}
  .aa-balance-bar .aa-muted,.aa-rank-bar .aa-muted{min-height:20px;}
  .aa-balance-bar > div,.aa-rank-bar > div{display:flex;flex-direction:column;align-items:center;}
  .rank-main{font-size:28px;font-weight:800;min-height:38px;display:flex;align-items:center;justify-content:center;}
  @media (max-width:768px){.aa-balance-rank-container{grid-template-columns:1fr;}}
  
  .aa-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--aa-gap);margin:16px 0;}
  @media (max-width:1200px){.aa-kpis{grid-template-columns:repeat(2,1fr)}}
  @media (max-width:600px){.aa-kpis{grid-template-columns:1fr}}
  .aa-card,.aa-panel,.aa-xp-card,.aa-rank-card{padding:var(--aa-pad);margin-bottom:var(--aa-gap);background:linear-gradient(to bottom,#01142E 0%,#010828 100%);border:1px solid rgba(255,255,255,.06);border-radius:var(--aa-card-radius);box-shadow:var(--aa-shadow);}
  /* Ensure 'You' row styling is identical in Top 10 and My Position */
  .leaderboard { list-style: none; margin: 0; padding: 0; }
  .leaderboard li { display: flex; align-items: center; gap: 12px; padding: 8px 10px; border-radius: 8px; }
  .leaderboard li .pos { width: 46px; font-weight: 800; color: #fff; }
  .leaderboard li strong { font-weight: 800; color: #e6e9f0; }
  .leaderboard li.current-user {
    background: linear-gradient(135deg,rgba(255,0,170,0.12) 0%,rgba(0,212,255,0.12) 100%) !important;
    border: 2px solid #FF00AA !important;
    box-shadow: 0 0 24px rgba(255,0,170,0.4), inset 0 1px 0 rgba(255,255,255,0.1) !important;
  }
  .leaderboard li.current-user strong { color: #e6e9f0; background: none; padding: 0; border-radius: 0; }
  .user-id-badge, .user-id-pill { background: linear-gradient(90deg,#FFDD57,#FFD100); padding: 3px 8px; border-radius: 8px; color: #111; font-weight: 800; display: inline-block; margin-left: 6px; }
  .xp-display { display: flex; align-items: center; gap: 8px; }
  /* Leaderboard styles */
  .leaderboard{list-style:none;margin:0;padding:0}
  .leaderboard li{display:flex;align-items:center;padding:8px 10px;border-radius:8px;margin-bottom:8px}
  .leaderboard li .pos{width:46px;font-weight:800;color:#fff}
  .leaderboard li strong{font-weight:800;color:#e6e9f0}
  .leaderboard li.highlight-user{background:linear-gradient(90deg,rgba(255,240,160,0.03),rgba(6,182,212,0.02));box-shadow:0 6px 18px rgba(6,182,212,0.04);border:1px solid rgba(6,182,212,0.06)}
  /* Keep the 'You' strong styling plain to match the My Position view */
  .leaderboard li.current-user strong, .leaderboard li.highlight-user strong{color:#e6e9f0;padding:0;border-radius:0}
  .leaderboard .user-id-badge, .leaderboard .user-id-pill{background:linear-gradient(90deg,#FFDD57,#FFD100);padding:3px 8px;border-radius:8px;color:#111;font-weight:800;margin-left:6px;display:inline-block}

  .leaderboard-banner{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:20px 24px;border-radius:14px;background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(255,0,170,0.06));border:2px solid rgba(0,212,255,0.3);box-shadow:0 8px 32px rgba(0,212,255,0.15);font-weight:700;color:#e6e9f0}
  .leaderboard-banner .banner-icon{width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#00D4FF,#FF00AA);border-radius:10px;flex-shrink:0;box-shadow:0 4px 12px rgba(0,212,255,0.4)}
  .leaderboard-banner .banner-icon svg{width:28px;height:28px}
  .leaderboard-banner .banner-text{display:flex;flex-direction:column;gap:8px;flex:1}
  .leaderboard-banner .banner-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .leaderboard-banner .banner-label{color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
  .leaderboard-banner .banner-value{color:#FFD700;font-size:16px;font-weight:800}
  .leaderboard-banner .banner-context{color:#06b6d4;font-size:13px;font-weight:700;padding:4px 10px;background:rgba(6,182,212,0.15);border-radius:6px}
  .aa-panel + .aa-panel{margin-top:0;}
  .aa-kpi-label{color:#fff;font-weight:800;}
  .aa-kpi-value{color:#fff;font-size:22px;font-weight:800;}
  .xp-ring-container{position:relative;width:80px;height:80px;margin:0 auto 12px;}
  .xp-ring{transform:rotate(-90deg);width:100%;height:100%;}
  .xp-ring circle{fill:none;stroke-width:8;}
  .xp-ring .bg-ring{stroke:rgba(255,255,255,0.1);}
  .xp-ring .progress{stroke:url(#xpGradient);stroke-linecap:round;stroke-dasharray:226;stroke-dashoffset:226;animation:xp-fill 2.2s ease-out forwards;}
  @keyframes xp-fill{0%{stroke-dashoffset:226;}50%{stroke-dashoffset:0;}100%{stroke-dashoffset:var(--final-offset);}}
  .xp-level{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:24px;font-weight:800;color:#FFD700;text-shadow:0 0 10px rgba(255,215,0,0.6);}
  .xp-level.max{color:#FF4500;text-shadow:0 0 15px rgba(255,69,0,0.8);}
  .xp-text{font-size:0.85rem;color:#A5B4D6;}
  .level-up-flash{animation:levelUpPulse 2s infinite;}
  @keyframes levelUpPulse{0%,100%{opacity:1;}50%{opacity:0.6;}}
  .aa-filter-bar form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
  .aa-filter-bar h3{font-size:1rem;color:#fff;margin:0 0 8px 0;font-weight:600;}
  .aa-filter-bar label{color:#A5B4D6;font-size:.9rem;}
  .aa-filter-bar select,.aa-filter-bar button,.aa-filter-bar a{background:#010828;color:#A5B4D6;border:1px solid rgba(255,255,255,0.3);border-radius:8px;padding:6px 10px;font-size:.9rem;}
  .aa-filter-bar button,.aa-filter-bar a{cursor:pointer;text-decoration:none;display:inline-block;}
  .aa-congrats{display:flex;gap:16px;align-items:center;flex-wrap:wrap;}
  .aa-congrats video{width:19.9cm;height:11.01cm;max-width:100%;object-fit:contain;border-radius:10px;}
  .aa-congrats .aa-muted{font-size:.9rem;}
  .aa-panel-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.06);margin:-8px 0 var(--aa-pad) 0;cursor:pointer;}
  .aa-panel-head h3{display:flex;align-items:center;gap:8px;margin:0;color:#F8FAFF;font-size:1rem;font-weight:800;}
  .aa-panel-head .fa-chevron-down{font-size:12px;color:#A5B4D6;transition:transform .3s;}
  .aa-panel-head.open .fa-chevron-down{transform:rotate(180deg);}
  .aa-panel-content{display:none;margin-top:12px;}
  .aa-panel-head.open + .aa-panel-content{display:block;}
  .aa-muted{color:#A5B4D6;font-size:.9rem}
  .recent-list{list-style:none;margin:0;padding:0}
  .recent-list li{display:flex;gap:12px;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.06);transition:all 0.2s ease;cursor:pointer;}
  .recent-list li:hover{background:rgba(255,255,255,.04);padding-left:4px;padding-right:-4px;}
  .recent-list li:last-child{border-bottom:0}
  .earned-badge-icon{height:90px;width:90px;border-radius:6px;background:rgba(255,255,255,.05);object-fit:contain;border:1px solid rgba(255,255,255,.1)}
  
  /* Badge & Challenge grids */
  .badge-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;}
  .badge-card{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:10px;transition:all 0.2s ease;cursor:pointer;}
  .badge-card:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);}
  .badge-card img{height:100px;width:100px;object-fit:contain;border-radius:8px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.2);transition:all 0.2s ease;}
  .badge-card:hover img{transform:scale(1.05);}
  .leaderboard{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
  .leaderboard li{display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:12px;background:linear-gradient(135deg, rgba(0,212,255,0.03) 0%, rgba(255,0,170,0.03) 100%);border:1px solid rgba(255,255,255,.08);transition:all 0.3s ease;position:relative;overflow:hidden;}
  .leaderboard li::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg, #00D4FF 0%, #FF00AA 50%, #FF9500 100%);opacity:0;transition:opacity 0.3s ease;}
  .leaderboard li:hover{background:linear-gradient(135deg, rgba(0,212,255,0.08) 0%, rgba(255,0,170,0.08) 100%);border-color:rgba(255,255,255,.15);transform:translateX(4px);}
  .leaderboard li:hover::before{opacity:1;}
  .leaderboard li.current-user{background:linear-gradient(135deg,rgba(255,0,170,0.12) 0%,rgba(0,212,255,0.12) 100%);border:2px solid #FF00AA;box-shadow:0 0 24px rgba(255,0,170,0.4), inset 0 1px 0 rgba(255,255,255,0.1);}
  .leaderboard li.current-user::before{opacity:1;width:5px;}
  .leaderboard li .pos{display:inline-flex;align-items:center;justify-content:center;min-width:48px;height:48px;background:linear-gradient(135deg, rgba(0,212,255,0.15) 0%, rgba(255,0,170,0.15) 100%);border-radius:10px;font-size:18px;font-weight:800;color:#00D4FF;border:1px solid rgba(0,212,255,0.3);flex-shrink:0;}
  .leaderboard li.current-user .pos{background:linear-gradient(135deg, #FF00AA 0%, #FF9500 100%);color:#fff;border-color:#FF00AA;box-shadow:0 4px 12px rgba(255,0,170,0.4);}
  .leaderboard li strong{font-size:16px;font-weight:700;color:#F8FAFF;flex-grow:1;}
  .leaderboard li .aa-muted{font-size:14px;color:#A5B4D6;font-weight:600;}
  .leaderboard .user-id-badge{background:linear-gradient(135deg,#FFD700 0%,#FFA500 100%);color:#0b1530;font-weight:800;font-size:11px;padding:4px 10px;border-radius:8px;margin-left:8px;box-shadow:0 2px 8px rgba(255,215,0,0.4);white-space:nowrap;}
  #apxBActivities::-webkit-scrollbar,#apxBBadges::-webkit-scrollbar{width:6px;}
  #apxBActivities::-webkit-scrollbar-track,#apxBBadges::-webkit-scrollbar-track{background:rgba(255,255,255,0.05);border-radius:3px;}
  #apxBActivities::-webkit-scrollbar-thumb,#apxBBadges::-webkit-scrollbar-thumb{background:rgba(255,0,170,0.5);border-radius:3px;}
  #apxBActivities::-webkit-scrollbar-thumb:hover,#apxBBadges::-webkit-scrollbar-thumb:hover{background:rgba(255,0,170,0.7);}
  #apxBActivitiesList li,#apxBBadgesList li{padding:4px 0;list-style:none;}
  #apxBActivitiesList li{color:#A5B4D6;}
  #apxBBadgesList li{color:#FFD700;}
  .apex-rank-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,rgba(0,212,255,0.2) 0%,rgba(255,0,170,0.2) 100%);border:1px solid #00D4FF;padding:8px 12px;border-radius:8px;font-size:13px;margin-top:8px;}
  .apex-rank-up{color:#00FF88;font-weight:800;}
  .apex-rank-down{color:#FF4444;font-weight:800;}
  
  /* Journey cards */
  .aa-journey-card{margin-bottom:32px;padding:20px;background:rgba(1,20,46,0.4);border-radius:16px;}
  .journey-title{font-size:1.4rem;font-weight:700;margin-bottom:16px;color:#00D4FF;}
  .journey-svg{width:100%;height:130px;background:rgba(0,0,0,0.3);border-radius:12px;margin-bottom:12px;}
  .waypoint-group{cursor:pointer;}
  .journey-waypoint{transition:all 0.3s ease;cursor:pointer;}
  .journey-waypoint:hover{filter:drop-shadow(0 0 12px rgba(255,149,0,0.8));r:15px;}
  .waypoint-completed{animation:pulse-solid 2s infinite;}
  @keyframes pulse-solid{0%,100%{filter:drop-shadow(0 0 4px currentColor);opacity:1;}50%{filter:drop-shadow(0 0 8px currentColor);opacity:0.8;}}
  .journey-progress-bar{width:100%;height:8px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;margin-top:12px;}
  .journey-progress-fill{height:100%;background:linear-gradient(90deg,#00D4FF 0%,#FF00AA 50%,#FF9500 100%);border-radius:4px;transition:width 0.3s ease;}
  .journey-stats{display:flex;justify-content:space-between;margin-top:12px;font-size:0.85rem;}
  .journey-stats div{color:#A5B4D6;}
  .journey-stats strong{color:#F8FAFF;}
  .coaching-box{margin-top:16px;padding:12px;background:linear-gradient(to bottom,rgba(1,42,74,0.5) 0%,rgba(1,8,40,0.5) 100%);border:1px solid rgba(255,255,255,.06);border-radius:8px;}
  .coaching-header{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-weight:600;color:#00D4FF;}
  .coaching-header::before{content:'🎯';font-size:16px;}
  .coaching-box p{margin:0;font-size:0.9rem;line-height:1.5;color:#B8C5E0;}
  
  /* Modern Badge Notification Modal (matches site-wide modal) */
  .apx-modal-backdrop{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);backdrop-filter:blur(8px);z-index:9998;display:none;animation:fadeIn 0.3s ease-out;}
  @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
  @keyframes slideUp{from{opacity:0;transform:translate(-50%,-60%);}to{opacity:1;transform:translate(-50%,-50%);}}
  .apx-modal{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:520px;max-width:95vw;max-height:90vh;background:linear-gradient(145deg,#0a1832 0%,#0d1b35 100%);border:2px solid #ff9500;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.5),0 0 0 1px rgba(255,149,0,0.1),inset 0 1px 0 rgba(255,255,255,0.05);z-index:9999;display:none;overflow:hidden;animation:slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);}
  .apx-modal-header{position:relative;padding:24px 24px 20px;background:linear-gradient(90deg,rgba(255,149,0,0.1) 0%,rgba(0,212,255,0.1) 100%);border-bottom:1px solid rgba(255,149,0,0.2);overflow:hidden;}
  .apx-modal-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:radial-gradient(circle at 20% 20%,rgba(255,149,0,0.1) 0%,transparent 50%),radial-gradient(circle at 80% 80%,rgba(0,212,255,0.1) 0%,transparent 50%);pointer-events:none;}
  .apx-modal-title{font-size:1.75rem;font-weight:800;color:#ffffff;margin:0;text-align:center;text-shadow:0 2px 4px rgba(0,0,0,0.3);background:linear-gradient(135deg,#ffd700 0%,#ff9500 50%,#00d4ff 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;position:relative;z-index:1;}
  .apx-modal-subtitle{font-size:0.9rem;color:#a5b4d6;text-align:center;margin:4px 0 0;font-weight:500;position:relative;z-index:1;}
  .apx-modal-progress{position:absolute;bottom:0;left:0;height:3px;background:linear-gradient(90deg,#ff9500 0%,#ffd700 50%,#00d4ff 100%);width:100%;transform-origin:left;animation:progressShrink 25s linear forwards;border-radius:0 0 20px 20px;}
  @keyframes progressShrink{from{transform:scaleX(1);}to{transform:scaleX(0);}}
  .apx-modal-close{position:absolute;top:16px;right:16px;background:rgba(255,149,0,0.2);border:2px solid rgba(255,149,0,0.4);color:#FF9500;cursor:pointer;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all 0.3s;z-index:20;padding:0;box-shadow:0 4px 12px rgba(0,0,0,0.3);}
  .apx-modal-close:hover{background:rgba(255,149,0,0.4);transform:rotate(90deg) scale(1.15);box-shadow:0 0 24px rgba(255,149,0,0.7);border-color:#FF9500;}
  .apx-modal-close svg{width:24px;height:24px;}
  .apx-modal-body{padding:24px;overflow-y:auto;max-height:calc(90vh - 120px);}
  .apx-modal-body::-webkit-scrollbar{width:8px;}
  .apx-modal-body::-webkit-scrollbar-track{background:rgba(255,255,255,0.05);border-radius:4px;}
  .apx-modal-body::-webkit-scrollbar-thumb{background:linear-gradient(180deg,rgba(255,149,0,0.6) 0%,rgba(255,149,0,0.3) 100%);border-radius:4px;}
  .apx-video-container{position:relative;width:100%;max-width:320px;aspect-ratio:1;border-radius:16px;overflow:hidden;margin:0 auto 24px;box-shadow:0 8px 32px rgba(0,0,0,0.3);background:linear-gradient(135deg,rgba(0,212,255,0.1) 0%,rgba(255,0,170,0.1) 50%,rgba(255,149,0,0.1) 100%);}
  .apx-badge-video{width:100%;height:100%;object-fit:contain;display:block;background:#000;}
  .apx-video-fullscreen-btn{position:absolute;bottom:12px;right:12px;width:36px;height:36px;background:rgba(0,0,0,0.8);border:2px solid rgba(255,149,0,0.5);border-radius:8px;color:#ffd700;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all 0.3s ease;z-index:10;}
  .apx-video-fullscreen-btn:hover{background:rgba(255,149,0,0.2);border-color:#ff9500;transform:scale(1.05);}
  .apx-badge-name-section{text-align:center;margin-bottom:24px;}
  .apx-badge-name{font-size:1.5rem;font-weight:800;color:#00d4ff;margin:0;text-shadow:0 2px 4px rgba(0,212,255,0.3);}
  .apx-badge-category{font-size:0.9rem;color:#a5b4d6;margin:4px 0 0;font-weight:500;}
  .apx-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}
  .apx-stat-card{background:linear-gradient(135deg,rgba(0,212,255,0.1) 0%,rgba(255,0,170,0.1) 100%);border:2px solid rgba(255,149,0,0.3);border-radius:12px;padding:20px;text-align:center;transition:all 0.3s ease;position:relative;overflow:hidden;}
  .apx-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#00d4ff 0%,#ff00aa 50%,#ff9500 100%);}
  .apx-stat-card:hover{transform:translateY(-2px);border-color:rgba(255,149,0,0.6);box-shadow:0 8px 25px rgba(255,149,0,0.2);}
  .apx-stat-icon{width:48px;height:48px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,rgba(0,212,255,0.2) 0%,rgba(255,0,170,0.2) 100%);border:2px solid rgba(255,149,0,0.4);}
  .apx-stat-icon svg,.apx-stat-icon img{width:24px;height:24px;}
  .apx-stat-value{font-size:1.5rem;font-weight:800;color:#ffd700;margin:0;text-shadow:0 1px 2px rgba(0,0,0,0.5);}
  .apx-stat-label{font-size:0.8rem;color:#a5b4d6;margin:4px 0 0;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
  .apx-info-section{background:rgba(255,255,255,0.02);border:1px solid rgba(255,149,0,0.2);border-radius:12px;padding:20px;margin-bottom:20px;}
  .apx-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
  .apx-info-item{display:flex;flex-direction:column;gap:4px;}
  .apx-info-label{font-size:0.8rem;color:#a5b4d6;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
  .apx-info-value{font-size:0.95rem;color:#ffffff;font-weight:500;}
  .apx-description-section{margin-bottom:20px;position:relative;}
  .apx-description-section::before{content:'📋';position:absolute;left:-8px;top:2px;font-size:1.2rem;opacity:0.8;}
  .apx-description-title{font-size:1rem;font-weight:700;color:#00d4ff;margin:0 0 8px 24px;display:flex;align-items:center;gap:8px;}
  .apx-description-title::before{content:'';width:4px;height:16px;background:linear-gradient(180deg,#00d4ff,#0099cc);border-radius:2px;}
  .apx-description-text{font-size:0.9rem;color:#e9f0ff;line-height:1.6;margin:0 0 0 24px;padding:12px 16px;background:rgba(255,255,255,0.02);border-left:3px solid rgba(0,212,255,0.4);border-radius:0 8px 8px 0;}
  .apx-activities-section{margin-bottom:20px;position:relative;display:block!important;visibility:visible!important;opacity:1!important;min-height:100px;}
  .apx-activities-section::before{content:'🎯';position:absolute;left:-8px;top:2px;font-size:1.2rem;opacity:0.8;}
  .apx-activities-title{font-size:1rem;font-weight:700;color:#00d4ff;margin:0 0 12px 24px;display:flex;align-items:center;gap:8px;}
  .apx-activities-title::before{content:'';width:4px;height:16px;background:linear-gradient(180deg,#00d4ff,#0099cc);border-radius:2px;}
  .apx-activities-list{list-style:none;margin:0 0 0 24px;padding:16px;background:rgba(255,255,255,0.02);border-left:3px solid rgba(0,212,255,0.4);border-radius:0 8px 8px 0;max-height:150px;overflow-y:auto;}
  .apx-activities-list::-webkit-scrollbar{width:6px;}
  .apx-activities-list::-webkit-scrollbar-track{background:rgba(255,255,255,0.05);border-radius:3px;}
  .apx-activities-list::-webkit-scrollbar-thumb{background:rgba(255,149,0,0.5);border-radius:3px;}
  .apx-activity-item{display:flex;align-items:center;gap:8px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;color:#e9f0ff;transition:all 0.2s ease;position:relative;}
  .apx-activity-item:hover{background:rgba(255,255,255,0.02);border-radius:4px;padding-left:8px;margin-left:-8px;margin-right:-8px;}
  .apx-activity-item:last-child{border-bottom:none;}
  .apx-activity-item::before{content:'✅';color:#00ff88;font-weight:800;font-size:0.9rem;flex-shrink:0;transition:transform 0.2s ease;}
  .apx-activity-item:hover::before{transform:scale(1.1);}
  .apx-badges-section{margin-bottom:20px;position:relative;}
  .apx-badges-section::before{content:'🏅';position:absolute;left:-8px;top:2px;font-size:1.2rem;opacity:0.8;}
  .apx-badges-title{font-size:1rem;font-weight:700;color:#ff00aa;margin:0 0 12px 24px;display:flex;align-items:center;gap:8px;}
  .apx-badges-title::before{content:'';width:4px;height:16px;background:linear-gradient(180deg,#ff00aa,#cc0088);border-radius:2px;}
  .apx-badges-list{list-style:none;margin:0 0 0 24px;padding:16px;background:rgba(255,255,255,0.02);border-left:3px solid rgba(255,0,170,0.4);border-radius:0 8px 8px 0;max-height:150px;overflow-y:auto;}
  .apx-badges-list::-webkit-scrollbar{width:6px;}
  .apx-badges-list::-webkit-scrollbar-track{background:rgba(255,255,255,0.05);border-radius:3px;}
  .apx-badges-list::-webkit-scrollbar-thumb{background:rgba(255,0,170,0.5);border-radius:3px;}
  .apx-badge-item{display:flex;align-items:center;gap:8px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;color:#ffd700;font-weight:600;transition:all 0.2s ease;}
  .apx-badge-item:hover{background:rgba(255,255,255,0.02);border-radius:4px;padding-left:8px;margin-left:-8px;margin-right:-8px;}
  .apx-badge-item:last-child{border-bottom:none;}
  .apx-rank-section{background:linear-gradient(135deg,rgba(0,212,255,0.1) 0%,rgba(255,0,170,0.1) 100%);border:2px solid rgba(0,212,255,0.3);border-radius:12px;padding:16px;margin-bottom:24px;text-align:center;position:relative;overflow:hidden;}
  .apx-rank-section::before{content:'🏅';position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:1.5rem;opacity:0.6;}
  .apx-rank-text{font-size:1rem;color:#ffffff;font-weight:600;margin:0;position:relative;z-index:1;}
  .apx-rank-change{font-size:0.9rem;font-weight:800;margin:4px 0 0;position:relative;z-index:1;}
  .apx-rank-change.up{color:#00ff88;}
  .apx-rank-change.down{color:#ff4444;}
  .apx-muted{color:#94a3b8;}
  .aa-timeline text{fill:#fff!important;font-family:"Montserrat",sans-serif!important;font-size:12px!important;}
  .aa-timeline .chartjs-tooltip{background:rgba(11,20,58,0.95)!important;color:#fff!important;border-radius:8px!important;}
  .aa-timeline .chartjs-tooltip-key{color:#fff!important;}
  /* Force chart axis, legend, and table text to white for legibility */
  .aa-timeline .chart-legend, .aa-timeline .chart-legend *,
  .aa-timeline .chart-axis, .aa-timeline .chart-axis *,
  .aa-timeline .chart-x-axis, .aa-timeline .chart-x-axis *,
  .aa-timeline .chart-y-axis, .aa-timeline .chart-y-axis *,
  .aa-timeline .chart-table, .aa-timeline .chart-table *,
  .aa-timeline .table, .aa-timeline .table *,
  .aa-timeline .chartjs-table, .aa-timeline .chartjs-table *,
  .aa-timeline .chartjs, .aa-timeline .chartjs * {
    color: #fff !important;
    fill: #fff !important;
    border-color: #fff !important;
    background: transparent !important;
  }
  .aa-timeline .chart-axis line, .aa-timeline .chart-x-axis line, .aa-timeline .chart-y-axis line {
    stroke: #fff !important;
  }
  .aa-timeline table, .aa-timeline th, .aa-timeline td {
    color: #fff !important;
    background: transparent !important;
    border-color: #fff !important;
  }
  .section--instructions{display:flex;justify-content:center;align-items:center;text-align:center;}
  .section--instructions p{margin:0;}
  .section--instructions a{color:#FFF;padding:12px 20px;border-radius:8px;display:inline-flex;align-items:center;gap:12px;text-decoration:none;transition:background .2s;}
  .section--instructions a:hover{background:rgba(255,255,255,0.1);}
  .instruction-icon{height:60px;object-fit:contain;}
  .section-title-icon{height:60px;width:auto;vertical-align:middle;}
  
  /* Responsive */
  @media (max-width: 768px) {
    .apexrewards-kpis { grid-template-columns: 1fr 1fr; }
    .apexrewards-kpis > * { height: 160px; }
  }
</style>

<div class="aa-wrap">
<div class="apexrewards">

  <!-- Instructions -->
  <div class="section section--instructions aa-card">
    <p><a href="<?php echo (new moodle_url('/local/ascend_rewards/html/index.html'))->out(false); ?>" target="_blank" onclick="window.open(this.href,'ascend_instructions','width=1600,height=900,scrollbars=yes,resizable=yes');return false;">
      <img class="instruction-icon" src="<?php echo s((new moodle_url('/local/ascend_rewards/pix/instructions.png'))->out(false)); ?>" alt="Instructions Icon" onerror="this.style.display='none';"/>
      Instructions: click here to see how Ascend works
    </a></p>
  </div>

  <!-- Celebration Banner -->
  <?php if (($has_earned || $show_level_up)): ?>
    <div class="aa-congrats">
      <video autoplay muted loop playsinline><source src="<?php echo (new moodle_url('/local/ascend_rewards/pix/reward_animation.mp4'))->out(false); ?>" type="video/mp4"></video>
      <div><div style="font-weight:800;font-size:18px;"><?php echo $show_level_up ? 'Level Up! You reached Level '.$level.'!' : 'New badge earned this week! The Gameboard has been unlocked!'; ?></div>
      <div class="aa-muted">Keep up the momentum — more Ascend Assets are on the way.</div></div>
    </div>
  <?php endif; ?>

  <!-- Balance & Rank Bars -->
  <div class="aa-balance-rank-container">
    <div class="aa-balance-bar">
      <img src="<?php echo s($coinimgurl->out(false)); ?>" alt="" onerror="this.src='<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>'">
      <div><div class="aa-muted">Your current balance</div><div class="rank-main"><?php echo number_format($coin_balance); ?> Ascend Assets</div></div>
    </div>
    
    <div class="aa-rank-bar">
      <img src="<?php echo s($stackimgurl->out(false)); ?>" alt="" onerror="this.src='<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false); ?>'">
      <div><div class="aa-muted">Your current rank</div><div class="rank-main"><?php echo $myrank !== null ? '#'.(int)$myrank.' of '.(int)$totalearners.' learners' : 'Not ranked'; ?></div></div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="aa-filter-bar">
    <h3>Filter by course</h3>
    <form method="get">
      <select name="courseid">
        <option value="">All courses</option>
        <?php foreach ($courseoptions as $cid => $label): ?>
          <option value="<?php echo s($cid); ?>" <?php echo ($courseid == $cid) ? 'selected' : ''; ?>><?php echo format_string($label); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Apply</button>
      <?php if ($courseid): ?><a href="<?php echo (new moodle_url('/local/ascend_rewards/index.php'))->out(false); ?>">Reset</a><?php endif; ?>
    </form>
  </div>

  <div class="aa-kpis">
    <div class="aa-card">
      <img src="<?php echo s($medal_gold_url->out(false)); ?>" alt="" style="width:60px;height:60px;margin-bottom:12px;">
      <div class="aa-kpi-label" style="font-weight:800;">Coins Earned</div>
      <div class="aa-kpi-value"><?php echo number_format($courseid ? $balance_course : $totalcoins); ?></div>
      <?php if ($courseid): ?><div class="aa-muted">In this course</div><?php endif; ?>
    </div>
    <div class="aa-card">
      <img src="<?php echo s($medal_silver_url->out(false)); ?>" alt="" style="width:60px;height:60px;margin-bottom:12px;">
      <div class="aa-kpi-label" style="font-weight:800;">Badges Earned</div>
      <div class="aa-kpi-value"><?php echo number_format($totalbadges); ?></div>
      <?php if ($courseid): ?><div class="aa-muted">In this course</div><?php endif; ?>
    </div>
    <div class="aa-rank-card">
      <img src="<?php echo s($medal_bronze_url->out(false)); ?>" alt="" style="width:60px;height:60px;margin-bottom:12px;">
      <div class="aa-kpi-label" style="font-weight:800;">Ranking</div>
      <div class="aa-kpi-value"><?php echo $myrank !== null ? '#'.(int)$myrank.' of '.(int)$totalearners : 'Not ranked'; ?></div>
      <?php if ($courseid): ?><div class="aa-muted">In this course</div><?php endif; ?>
    </div>
    <div class="aa-xp-card">
      <div class="xp-ring-container <?php echo $show_level_up ? 'level-up-flash' : ''; ?>">
        <svg class="xp-ring" viewBox="0 0 80 80" style="--final-offset: <?php echo 226 * (1 - ($xp_percent / 100)); ?>;">
          <defs>
            <linearGradient id="xpGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#00D4FF" />
              <stop offset="50%" stop-color="#FF00AA" />
              <stop offset="100%" stop-color="#FF9500" />
            </linearGradient>
          </defs>
          <circle cx="40" cy="40" r="36" class="bg-ring" />
          <circle class="progress" cx="40" cy="40" r="36" />
        </svg>
        <div class="xp-level">L<?php echo $level; ?></div>
      </div>
      <div class="aa-kpi-value" style="margin-top:12px;"><?php echo number_format($xp); ?> XP</div>
      <div style="font-size:14px;color:#94a3b8;margin-top:6px;">Level <span style="color:#FFD700;font-weight:800;"><?php echo $level; ?></span></div>
    </div>
  </div>

  <!-- Leaderboard -->
  <section class="aa-panel aa-leaderboard-wrap">
    <div class="aa-panel-head">
      <h3><img class="section-title-icon" src="<?php echo s($icon_leaderboard_url->out(false)); ?>" alt=""> Leaderboard <span id="leaderboardMode" style="color:#ec4899;font-size:14px;font-weight:600;">(Top 10<?php if (!is_null($courseid)) echo ' - ' . s($selectedcoursename); ?>)</span></h3>
      <i class="fa-solid fa-chevron-down"></i>
    </div>
    <div class="aa-panel-content">
      <!-- User ID Info (spiced-up) -->
      <?php $uid = (int)$USER->id; ?>
      <div class="leaderboard-banner" role="status" aria-live="polite">
        <div class="banner-icon">
          <svg viewBox="0 0 80 80" aria-hidden="true" fill="none" stroke="#01142E" stroke-width="3">
            <circle cx="40" cy="40" r="30"></circle>
            <text x="40" y="50" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">⭐</text>
          </svg>
        </div>
        <div class="banner-text">
          <div class="banner-row">
            <span class="banner-label">Your ID:</span>
            <span class="banner-value" style="background:linear-gradient(90deg,#FFDD57,#FFD100);color:#01142E;padding:4px 12px;border-radius:8px;display:inline-block;"><?php echo $uid; ?></span>
            <?php if ($myrank !== null): ?>
              <span style="color:#64748b;">|</span>
              <span class="banner-label">Rank:</span>
              <span class="banner-value">#<?php echo (int)$myrank; ?></span>
              <span style="color:#94a3b8;">of <?php echo (int)$totalearners; ?> learners</span>
            <?php endif; ?>
          </div>
          <div class="banner-row">
            <span class="banner-context">
              <?php if (!is_null($courseid)): ?>
                📚 <?php echo s($selectedcoursename); ?>
              <?php else: ?>
                🌐 Sitewide
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
      
      <div style="display:flex;gap:12px;margin-bottom:16px;">
        <button id="btnTopView" class="leaderboard-view-btn active" style="flex:1;background:#ec4899;border:none;color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">
          <i class="fa-solid fa-trophy"></i> Top 10
        </button>
        <button id="btnContextView" class="leaderboard-view-btn" style="flex:1;background:#01142E;border:2px solid #06b6d4;color:#06b6d4;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">
          <i class="fa-solid fa-location-crosshairs"></i> My Position
        </button>
      </div>
      
      <ul class="leaderboard" id="leaderboardList">
        <?php if (empty($top10)): ?>
          <li class="aa-muted">No leaderboard data yet.</li>
        <?php else: $rank = 1; foreach ($top10 as $row): 
          $is_current_user = ($row->userid === (int)$USER->id);
          $display_xp = isset($row->xp) ? (int)$row->xp : 0;
          $li_style = $is_current_user ? ' style="background:linear-gradient(135deg,rgba(255,0,170,0.12) 0%,rgba(0,212,255,0.12) 100%);border:2px solid #FF00AA;box-shadow:0 0 24px rgba(255,0,170,0.4), inset 0 1px 0 rgba(255,255,255,0.1);"' : '';
        ?>
          <li<?php echo $is_current_user ? ' class="current-user"' : ''; ?><?php echo $li_style; ?>>
            <span class="pos"><?php echo apex_medal_for_place($rank++); ?></span>
            <strong><?php echo $is_current_user ? 'You' : 'User #'.$row->userid; ?></strong>
            <?php if ($is_current_user): ?>
              <span class="user-id-badge">ID: <?php echo (int)$USER->id; ?></span>
            <?php endif; ?>
            <div class="xp-display" style="margin-left:auto;">
              <svg width="28" height="28" viewBox="0 0 80 80" aria-hidden="true">
                <defs><linearGradient id="xpIconGradLB<?php echo $rank-1; ?>" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>
                <circle cx="40" cy="40" r="36" fill="url(#xpIconGradLB<?php echo $rank-1; ?>)" />
                <text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="34" font-weight="800">X</text>
              </svg>
              <span class="aa-muted" style="font-weight:700;color:#e6e9f0;min-width:80px;text-align:right;display:inline-block;margin-left:8px"><?php echo number_format($display_xp); ?></span>
            </div>
          </li>
        <?php endforeach; 
          if ($current_user_data !== null): ?>
          <li class="current-user" style="margin-top:16px;border-top:2px solid rgba(255,0,170,0.08);padding-top:16px;padding-bottom:8px;">
            <span class="pos"><?php echo apex_medal_for_place((int)$current_user_data->rank); ?></span>
            <strong>You</strong>
            <span class="user-id-badge">ID: <?php echo (int)$USER->id; ?></span>
            <div class="xp-display" style="display:flex;align-items:center;gap:8px;margin-left:auto;">
              <svg width="28" height="28" viewBox="0 0 80 80" style="flex-shrink:0;">
                <defs><linearGradient id="xpIconGradLBCurrent" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>
                <circle cx="40" cy="40" r="36" fill="url(#xpIconGradLBCurrent)" />
                <text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="34" font-weight="800">X</text>
              </svg>
              <span class="aa-muted" style="font-weight:700;color:#e6e9f0;min-width:80px;text-align:right;"><?php echo number_format((int)$current_user_data->xp); ?></span>
            </div>
          </li>
        <?php endif; endif; ?>
      </ul>
    </div>
  </section>
  <section class="aa-panel" id="a_all_badges">
    <div class="aa-panel-head">
      <h3><img class="section-title-icon" src="<?php echo s($icon_badges_course_url->out(false)); ?>" alt="" onerror="this.style.display='none';"> Badges Earned</h3>
      <i class="fa-solid fa-chevron-down"></i>
    </div>
    <div class="aa-panel-content">
      <?php if (empty($bycourse)): ?>
        <div class="aa-muted">No badges earned yet.</div>
      <?php else: ?>
        <?php foreach ($bycourse as $coursename => $list): ?>
          <div style="margin-top:24px;">
            <div style="font-weight:800;margin-bottom:16px;font-size:18px;color:#F8FAFF;"><?php echo s($coursename); ?></div>
            <?php
              // Group badges by badge ID and count occurrences
              $badge_counts = [];
              $badge_data = [];
              foreach ($list as $r) {
                  $badge_key = $r->badgeid;
                  if (!isset($badge_counts[$badge_key])) {
                      $badge_counts[$badge_key] = 0;
                      $badge_data[$badge_key] = $r; // Store first occurrence
                  }
                  $badge_counts[$badge_key]++;
              }
              
              // Reorganize by category with unique badges
              $by_category = [];
              foreach ($badge_data as $badgeid => $r) {
                  $category = $badge_categories[$r->badgename_display] ?? 'Other';
                  if (!isset($by_category[$category])) {
                      $by_category[$category] = [];
                  }
                  $r->earn_count = $badge_counts[$badgeid];
                  $by_category[$category][] = $r;
              }
            ?>
            <?php foreach ($by_category as $category => $badges): ?>
              <?php
                $category_colors = [
                    'Progress-Based' => '#FF00AA',
                    'Timeliness & Discipline' => '#00D4FF',
                    'Quality & Growth' => '#FF9500',
                    'Course Mastery' => '#7A00FF'
                ];
                $category_color = $category_colors[$category] ?? '#A5B4D6';
              ?>
              <div style="margin-bottom:16px;">
                <div style="font-weight:700;margin-bottom:8px;color:<?php echo $category_color; ?>;font-size:14px;"><?php echo format_string($category); ?></div>
                <div class="badge-grid">
                  <?php foreach ($badges as $item): ?>
                    <div class="badge-card js-badge-detail" data-badge="<?php echo s($item->badgename_display); ?>" data-badgeid="<?php echo (int)$item->badgeid; ?>" data-courseid="<?php echo (int)$item->courseid; ?>" data-course="<?php echo s($coursename); ?>" data-when="<?php echo s($item->formatted_date); ?>" data-coins="<?php echo s($item->coins_text); ?>" data-xp="<?php echo $xp; ?>" data-level="<?php echo $level; ?>" data-xp-percent="<?php echo $xp_percent; ?>" data-why="<?php echo s($badge_descriptions[$item->badgename_display] ?? 'See badge rules.'); ?>" data-category="<?php echo s($category); ?>" style="cursor:pointer; position: relative;">
                      <?php if ($item->earn_count > 1): ?>
                        <div style="position: absolute; top: 8px; right: 8px; background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); color: #0b1530; font-weight: 800; font-size: 12px; padding: 4px 8px; border-radius: 12px; box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);">x<?php echo $item->earn_count; ?></div>
                      <?php endif; ?>
                      <img src="<?php echo s($item->icon_url); ?>" alt="<?php echo s($item->badgename_display); ?>" onerror="this.src=&quot;<?php echo s($coin_fallback->out(false)); ?>&quot;">
                      <div><div style="font-weight:700;"><?php echo format_string($item->badgename_display); ?></div><div class="aa-muted">Earned <?php echo $item->earn_count > 1 ? $item->earn_count . ' times' : 'on ' . $item->formatted_date; ?></div></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
  <!-- PERSONALIZED CHALLENGES SECTION DISABLED -->

<?php 
  // Course journeys section
  try {
    if (!empty($journeys)): ?>
<section class="aa-panel aa-journey-wrap">
  <div class="aa-panel-head">
    <h3><img class="section-title-icon" src="<?php echo s($icon_journey_url->out(false)); ?>" alt="" onerror="this.style.display='none';"> Course Journey</h3>
    <i class="fa-solid fa-chevron-down"></i>
  </div>
  <div class="aa-panel-content">
    <?php 
      // Always show all journeys - course filter does not apply to this section
      $journeys_to_show = $journeys;
    ?>
    <?php if (empty($journeys_to_show)): ?>
      <div class="aa-muted">No course journeys available.</div>
    <?php else: ?>
      <?php foreach ($journeys_to_show as $journey): ?>
      <div class="aa-journey-card">
        <div class="journey-title"><?php echo s($journey->coursename); ?></div>
        <?php 
          try {
            echo apex_generate_journey_svg($journey);
          } catch (Exception $e) {
            echo "<!-- Journey SVG error: " . htmlspecialchars($e->getMessage()) . " -->";
          } catch (Throwable $t) {
            echo "<!-- Journey SVG fatal: " . htmlspecialchars($t->getMessage()) . " -->";
          }
        ?>
        <div class="journey-progress-bar">
          <div class="journey-progress-fill" style="width: <?php echo $journey->progress; ?>%;"></div>
        </div>
        <div class="journey-stats">
          <div><strong><?php echo (int)$journey->completed_count; ?></strong> of <strong><?php echo (int)$journey->total_count; ?></strong> activities completed</div>
          <div><strong><?php echo round($journey->progress, 0); ?>%</strong> progress</div>
        </div>
        <?php 
          try {
            echo apex_get_coaching_insights($journey, $USER);
          } catch (Exception $e) {
            echo "<!-- Coaching error: " . htmlspecialchars($e->getMessage()) . " -->";
          } catch (Throwable $t) {
            echo "<!-- Coaching fatal: " . htmlspecialchars($t->getMessage()) . " -->";
          }
        ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
    <?php endif; // if !empty journeys
  } catch (Exception $e) {
    // Journey rendering error
  } catch (Throwable $t) {
    // Journey rendering fatal error
  }
?>

<?php
// Weekly gameboard section
try {
    $has_earned = local_ascend_rewards_gameboard::has_earned_badges_this_week($USER->id);
} catch (Exception $e) {
    $has_earned = false;
}

if ($has_earned) {
    try {
        $available_picks = local_ascend_rewards_gameboard::get_available_picks($USER->id);
        $picks_made = local_ascend_rewards_gameboard::get_picks_made($USER->id);
        $remaining_picks = local_ascend_rewards_gameboard::get_remaining_picks($USER->id);
        $card_values = local_ascend_rewards_gameboard::get_card_values($USER->id);
        $badge_layout = local_ascend_rewards_gameboard::get_badge_layout();
        $badge_names = local_ascend_rewards_gameboard::get_badge_names();
        list($week_start, $week_end) = local_ascend_rewards_gameboard::get_week_range();
    
        $week_start_str = userdate($week_start, '%d %b');
        $week_end_str = userdate($week_end, '%d %b %Y');
        
        // Verify we have all required data before rendering
        // Note: $picks_made can be empty array (0 picks is valid - user hasn't picked yet)
        if (empty($available_picks) || !is_array($picks_made) || empty($badge_layout) || empty($badge_names)) {
            throw new Exception('Gameboard data missing');
        }
        ?>
        <section class="aa-panel" id="a_weekly_gameboard">
            <div class="aa-panel-head">
                <h3><img class="section-title-icon" src="<?php echo (new moodle_url('/local/ascend_rewards/pix/gameboard.png'))->out(false); ?>" alt="" onerror="this.style.display='none';"> Weekly Gameboard <span class="aa-muted" style="font-size:14px;font-weight:400;margin-left:8px;">(<?php echo $week_start_str; ?> - <?php echo $week_end_str; ?>)</span></h3>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="aa-panel-content">
            <div style="background:#01142E;border-radius:16px;padding:24px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div style="font-size:14px;color:#94a3b8;margin-bottom:4px;">Picks Available</div>
                        <div style="font-size:28px;font-weight:800;color:#ec4899;"><?php echo $remaining_picks; ?></div>
                    </div>
                    <div>
                        <div style="font-size:14px;color:#94a3b8;margin-bottom:4px;">Normal Badges</div>
                        <div style="font-size:20px;font-weight:700;color:#ec4899;">1 pick × <?php echo $available_picks['normal']; ?></div>
                    </div>
                    <div>
                        <div style="font-size:14px;color:#94a3b8;margin-bottom:4px;">Meta Badges</div>
                        <div style="font-size:20px;font-weight:700;color:#ec4899;">2 picks × <?php echo $available_picks['meta']; ?></div>
                    </div>
                </div>
            </div>
            
            <div id="gameboardGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <?php 
                $flat_layout = [];
                foreach ($badge_layout as $row) {
                    $flat_layout = array_merge($flat_layout, $row);
                }
                
                for ($i = 0; $i < 16; $i++): 
                    $picked = in_array($i, $picks_made);
                    $coin_value = $card_values[$i];
                    $badge_id = $flat_layout[$i];
                    $badge_name = $badge_names[$badge_id];
                    // Convert badge name to lowercase underscore filename
                    $badge_filename = strtolower(str_replace(' ', '_', $badge_name));
                ?>
                    <div class="gameboard-card <?php echo $picked ? 'picked' : ''; ?>" 
                         data-position="<?php echo $i; ?>"
                         style="aspect-ratio:1;background:<?php echo $picked ? '#ffffff' : '#01142E'; ?>;border:3px solid #06b6d4;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:<?php echo $picked ? 'default' : 'pointer'; ?>;transition:all 0.3s ease;position:relative;overflow:hidden;">
                        
                        <?php if ($picked): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                                <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>" 
                                     alt="Coin" 
                                     style="width:48px;height:48px;object-fit:contain;">
                                <div style="font-size:20px;font-weight:800;color:#01142E;"><?php echo $coin_value; ?></div>
                            </div>
                        <?php else: ?>
                            <div class="card-content" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;pointer-events:none;">
                                <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/' . $badge_filename . '.png'))->out(false); ?>" 
                                     alt="<?php echo htmlspecialchars($badge_name, ENT_QUOTES, 'UTF-8'); ?>" 
                                     style="width:80%;height:80%;object-fit:contain;">
                            </div>
                            <div class="card-hover" style="position:absolute;inset:0;background:linear-gradient(135deg, rgb(4,120,140) 0%, rgb(190,50,120) 50%, rgb(200,90,30) 100%);display:none;align-items:center;justify-content:center;pointer-events:none;">
                                <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>" 
                                     alt="Pick" 
                                     class="hover-coin"
                                     style="width:60px;height:60px;object-fit:contain;">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    
    <style>
    .gameboard-card:not(.picked):hover .card-content {
        display: none;
    }
    .gameboard-card:not(.picked):hover .card-hover {
        display: flex !important;
    }
    .gameboard-card:not(.picked) .hover-coin {
        animation: coinPulse 1s ease-in-out infinite;
    }
    .gameboard-card.picked {
        pointer-events: none;
    }
    @keyframes cardFlip {
        0% { transform: rotateY(0deg); }
        50% { transform: rotateY(90deg); }
        100% { transform: rotateY(0deg); }
    }
    @keyframes coinPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
    </style>
        </div>
    </section>
    
    <script>
    // Gameboard pick handler
    document.addEventListener('DOMContentLoaded', function() {
        var cards = document.querySelectorAll('.gameboard-card:not(.picked)');
        
        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                var position = parseInt(card.getAttribute('data-position'));
                
                // Disable all cards during processing
                cards.forEach(function(c) { c.style.pointerEvents = 'none'; });
                
                // Make AJAX request
                var xhr = new XMLHttpRequest();
                xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/gameboard_pick.php');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var result = JSON.parse(xhr.responseText);
                            if (result.success) {
                                // Animate card flip
                                card.style.animation = 'cardFlip 0.6s ease-in-out';
                                
                                setTimeout(function() {
                                    // Update card to show result
                                    card.classList.add('picked');
                                    card.style.background = '#ffffff';
                                    card.style.cursor = 'default';
                                    card.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;gap:8px;">' +
                                        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/ascend_coin_main.png" alt="Coin" style="width:48px;height:48px;object-fit:contain;">' +
                                        '<div style="font-size:20px;font-weight:800;color:#01142E;">' + result.coins + '</div>' +
                                        '</div>';
                                    
                                    var remainingPicks = result.remaining;
                                    
                                    // Play sound
                                    try {
                                        var audio = document.getElementById('levelUpSound');
                                        if (audio) {
                                            audio.volume = 0.4;
                                            audio.currentTime = 0;
                                            audio.play().catch(function(){});
                                        }
                                    } catch(e) {}
                                    
                                    // Show success message
                                    setTimeout(function() {
                                        alert('🎉 You earned ' + result.coins + ' coins.');
                                        
                                        if (remainingPicks === 0) {
                                            location.reload();
                                        } else {
                                            // Re-enable remaining cards
                                            cards.forEach(function(c) { 
                                                if (!c.classList.contains('picked')) {
                                                    c.style.pointerEvents = 'auto'; 
                                                }
                                            });
                                        }
                                    }, 800);
                                }, 600);
                            } else {
                                alert('Error: ' + (result.error || 'Could not make pick'));
                                cards.forEach(function(c) { c.style.pointerEvents = 'auto'; });
                            }
                        } catch(e) {
                            alert('Error processing pick: ' + e.message);
                            cards.forEach(function(c) { c.style.pointerEvents = 'auto'; });
                        }
                    } else {
                        alert('Error making pick');
                        cards.forEach(function(c) { c.style.pointerEvents = 'auto'; });
                    }
                };
                xhr.send('position=' + position);
            });
        });
    });
    </script>
    
<?php
    } catch (Exception $e) {
        // Gameboard rendering exception
    } catch (Throwable $t) {
        // Gameboard rendering fatal error
    }
} else {
    // User hasn't earned badges this week
    ?>
    <section class="aa-panel" id="a_weekly_gameboard">
        <div class="aa-panel-head">
            <h3><img class="section-title-icon" src="<?php echo (new moodle_url('/local/ascend_rewards/pix/gameboard.png'))->out(false); ?>" alt="" onerror="this.style.display='none';"> Weekly Gameboard</h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="aa-panel-content">
            <div class="aa-muted">Complete activities and earn badges to unlock the gameboard!</div>
        </div>
    </section>
    <?php
}
?>
<?php
// Avatars section - DEMO VERSION: Only Level 1 with Elf and Imp
// Operational avatar sets: elf_lynx_dryad and imp_hamster_mole
$avatar_levels = [
    1 => ['elf.png', 'imp.png']
];

// Pet catalog - Only Lynx (elf) and Hamster (imp)
$avatar_pets_catalog = [
    100 => ['name' => 'Lynx', 'avatar' => 'elf.png', 'level' => 1, 'icon' => 'pets/lynx.png', 'video' => 'pets/videos/lynx.mp4', 'download_image' => 'pets/pets_circular/lynx.png', 'price' => 300],
    102 => ['name' => 'Hamster', 'avatar' => 'imp.png', 'level' => 1, 'icon' => 'pets/hamster.png', 'video' => 'pets/videos/hamster.mp4', 'download_image' => 'pets/pets_circular/hamster.png', 'price' => 300]
];

// Villain catalog - Only Dryad (elf's companion) and Mole (imp's companion)
$villain_catalog = [
    300 => ['name' => 'Dryad', 'pet_id' => 100, 'avatar' => 'elf.png', 'level' => 1, 'icon' => 'villains/elf_dryad.png', 'video' => 'villains/videos/imp_mole.mp4', 'price' => 500],
    302 => ['name' => 'Mole', 'pet_id' => 102, 'avatar' => 'imp.png', 'level' => 1, 'icon' => 'villains/imp_mole.png', 'video' => 'villains/videos/imp_mole.mp4', 'price' => 500]
];

// Get user's unlocked avatars from database
$unlocked_avatars_db = $DB->get_records('local_ascend_avatar_unlocks', ['userid' => $USER->id, 'pet_id' => null, 'villain_id' => null], 'timecreated');
$unlocked_avatars = [];
$avatar_unlock_types = [];
foreach ($unlocked_avatars_db as $record) {
    $unlocked_avatars[] = $record->avatar_name;
    $avatar_unlock_types[$record->avatar_name] = $record->unlock_type;
}

// Get user's pet unlocks from database
$pet_unlocks_db = $DB->get_records_sql(
    "SELECT * FROM {local_ascend_avatar_unlocks} WHERE userid = ? AND pet_id IS NOT NULL AND villain_id IS NULL",
    [$USER->id]
);
$owned_pets = [];
$pet_unlock_types = [];
foreach ($pet_unlocks_db as $record) {
    $owned_pets[] = $record->pet_id;
    $pet_unlock_types[$record->pet_id] = $record->unlock_type;
}

// Get user's villain unlocks from database
$villain_unlocks_db = $DB->get_records_sql(
    "SELECT * FROM {local_ascend_avatar_unlocks} WHERE userid = ? AND villain_id IS NOT NULL",
    [$USER->id]
);
$owned_villains = [];
$villain_unlock_types = [];
foreach ($villain_unlocks_db as $record) {
    $owned_villains[] = $record->villain_id;
    $villain_unlock_types[$record->villain_id] = $record->unlock_type;
}

// Get user's level tokens
$user_tokens = $DB->get_record('local_ascend_level_tokens', ['userid' => $USER->id]);
$tokens_available = $user_tokens ? $user_tokens->tokens_available - $user_tokens->tokens_used : 0;

// Determine which levels user can access (including epic levels 5-8)
$user_accessible_levels = [];
if ($level >= 1) $user_accessible_levels[] = 1;
if ($level >= 2) $user_accessible_levels[] = 2;
if ($level >= 3) $user_accessible_levels[] = 3;
if ($level >= 4) $user_accessible_levels[] = 4;
if ($level >= 5) $user_accessible_levels[] = 5;
if ($level >= 6) $user_accessible_levels[] = 6;
if ($level >= 7) $user_accessible_levels[] = 7;
if ($level >= 8) $user_accessible_levels[] = 8;
?>

<!-- ========== AVATAR SECTION STARTS HERE (v2025-12-08) ========== -->
<?php
?>
<!-- Avatar/Pet/Villain Modal Functions -->
<script src="<?php echo (new moodle_url('/local/ascend_rewards/avatar_modals.js'))->out(false); ?>"></script>

<?php 
include(__DIR__ . '/avatar_section_new.php');
?>

<?php
// Store section

// Build store items array
$store_items = [
    [
        'id' => 4,
        'name' => 'XP Multiplier (24h)',
        'description' => 'Double your XP gains for 24 hours! Activate after purchase.',
        'price' => 100,
        'icon' => 'ai_streak.png',
        'category' => 'power-ups',
        'stock' => 'unlimited'
    ],
    [
        'id' => 5,
        'name' => 'Mystery Box',
        'description' => 'Random reward with transparent odds! Guaranteed Rare+ every 5 boxes.',
        'price' => 50,
        'icon' => 'mystery_box.png',
        'category' => 'mystery',
        'stock' => 'unlimited',
        'special' => 'mysterybox'
    ]
];

// Add available pets to store (only if avatar is unlocked)
foreach ($avatar_pets_catalog as $pet_id => $pet) {
    $avatar_unlocked = in_array($pet['avatar'], $unlocked_avatars);
    $pet_owned = in_array($pet_id, $owned_pets);
    
    // Add to store items with availability info
    $store_items[] = [
        'id' => $pet_id,
        'name' => $pet['name'],
        'description' => $avatar_unlocked ? 'Rare companion pet for your ' . str_replace('.png', '', $pet['avatar']) . ' avatar!' : 'Unlock ' . str_replace('.png', '', $pet['avatar']) . ' avatar first to purchase this pet.',
        'price' => $pet['price'],
        'icon' => $pet['icon'],
        'category' => 'pets',
        'stock' => 1,
        'avatar_required' => $pet['avatar'],
        'avatar_unlocked' => $avatar_unlocked,
        'pet_owned' => $pet_owned
    ];
}

// Check if XP multiplier is active
$xp_multiplier_active = false;
$xp_multiplier_expires = 0;
$xp_multiplier_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);
if ($xp_multiplier_end > time()) {
    $xp_multiplier_active = true;
    $xp_multiplier_expires = $xp_multiplier_end;
}

// Get user's purchased items from preferences (inventory)
$inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
$inventory = $inventory_str ? json_decode($inventory_str, true) : [];

// Get user's activated items (one-time purchases that have been used)
$activated_items_str = get_user_preferences('ascend_store_activated', '', $USER->id);
$activated_items = $activated_items_str ? json_decode($activated_items_str, true) : [];
?>

<!-- Ascend Store Section -->
<?php include(__DIR__ . '/store_section.php'); ?>
<section class="aa-panel" id="a_store" style="display:none;">
    <div class="aa-panel-head">
      <h3><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/store.png'))->out(false); ?>" alt="Store" style="width:60px;height:60px;vertical-align:middle;margin-right:8px;"> Ascend Store <span style="color:#ec4899;font-size:14px;font-weight:600;margin-left:8px;">(<?php echo number_format($coin_balance); ?> coins available)</span></h3>
        <i class="fa-solid fa-chevron-down"></i>
    </div>
    <div class="aa-panel-content">
        <?php if ($xp_multiplier_active): ?>
            <div style="background:linear-gradient(135deg,#ec4899,#FF00AA);border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;">
                <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:8px;">🔥 XP Multiplier Active!</div>
                <div style="font-size:14px;color:rgba(255,255,255,0.9);">You're earning 2x XP! Expires in: <strong id="xpMultiplierTimer_dashboard" data-expires="<?php echo $xp_multiplier_expires; ?>"></strong></div>
            </div>
        <?php endif; ?>
        
        <p class="aa-muted" style="margin-bottom:20px;">
            Purchase power-ups, pets, and mystery boxes! Pets require their associated avatar to be unlocked first.
        </p>
        
        <!-- Category Filters -->
        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
            <button class="store-filter-btn active" data-category="all" style="background:#ec4899;border:none;color:#fff;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">All Items</button>
            <button class="store-filter-btn" data-category="power-ups" style="background:#01142E;border:2px solid #00D4FF;color:#00D4FF;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">Power-Ups</button>
            <button class="store-filter-btn" data-category="pets" style="background:#01142E;border:2px solid #FF9500;color:#FF9500;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">Pets</button>
            <button class="store-filter-btn" data-category="mystery" style="background:#01142E;border:2px solid #FFD700;color:#FFD700;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;">Mystery</button>
        </div>
        
        <!-- Store Items Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
            <?php foreach ($store_items as $item): 
                $item_id = $item['id'];
                $in_inventory = isset($inventory[$item_id]) && $inventory[$item_id] > 0;
                $inventory_count = $in_inventory ? $inventory[$item_id] : 0;
                $can_afford = $coin_balance >= $item['price'];
                $fallback_icon = (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false);
            ?>
                <div class="store-item-card" 
                     data-category="<?php echo $item['category']; ?>"
                     data-item-id="<?php echo $item_id; ?>"
                     style="background:linear-gradient(135deg,#01142E,#010828);border:2px solid <?php 
                         if ($item['category'] === 'pets' && isset($item['pet_owned']) && $item['pet_owned']) {
                             echo '#06b6d4';
                         } elseif ($in_inventory) {
                             echo '#00D4FF';
                         } else {
                             echo 'rgba(255,255,255,0.1)';
                         }
                     ?>;border-radius:12px;padding:24px;position:relative;transition:all 0.3s ease;">
                    
                    <?php if ($item['category'] === 'pets' && isset($item['pet_owned']) && $item['pet_owned']): ?>
                        <div style="position:absolute;top:12px;right:12px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#01142E;font-size:11px;font-weight:800;padding:6px 12px;border-radius:8px;box-shadow:0 2px 8px rgba(6,182,212,0.3);">
                            🐾 OWNED
                        </div>
                    <?php elseif ($in_inventory): ?>
                        <div style="position:absolute;top:12px;right:12px;background:linear-gradient(135deg,#00D4FF,#06b6d4);color:#01142E;font-size:11px;font-weight:800;padding:6px 12px;border-radius:8px;box-shadow:0 2px 8px rgba(0,212,255,0.3);">
                            OWNED: <?php echo $inventory_count; ?>x
                        </div>
                    <?php endif; ?>
                    
                    <div style="text-align:center;margin-bottom:20px;">
                        <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/' . $item['icon']))->out(false); ?>" 
                             alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                             onerror="this.src='<?php echo $fallback_icon; ?>'"
                             style="width:120px;height:120px;object-fit:contain;margin-bottom:12px;filter:drop-shadow(0 4px 12px rgba(236,72,153,0.3));">
                        <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-bottom:8px;"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-size:14px;color:#94a3b8;line-height:1.6;"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    
                    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:16px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:12px;">
                            <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>" 
                                 alt="Coins" 
                                 style="width:28px;height:28px;">
                            <span style="font-size:24px;font-weight:800;color:#FFD700;"><?php echo number_format($item['price']); ?></span>
                        </div>
                        
                        <?php if (isset($item['special']) && $item['special'] === 'mysterybox'): ?>
                            <!-- Mystery Box Special Button -->
                            <?php if (!$can_afford): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                    Not Enough Coins
                                </button>
                            <?php else: ?>
                                <button class="mysterybox-open-btn" 
                                        data-price="<?php echo $item['price']; ?>"
                                        style="width:100%;background:linear-gradient(135deg,#FFD700,#FFA500);border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(255,215,0,0.4);">
                                    🎁 Open Mystery Box
                                </button>
                            <?php endif; ?>
                        <?php elseif ($item['category'] === 'pets'): ?>
                            <!-- Pet Purchase Button -->
                            <?php if (isset($item['pet_owned']) && $item['pet_owned']): ?>
                                <button disabled style="width:100%;background:#06b6d4;border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:not-allowed;">
                                    ✅ Pet Owned
                                </button>
                            <?php elseif (!isset($item['avatar_unlocked']) || !$item['avatar_unlocked']): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                    🔒 Avatar Required
                                </button>
                            <?php elseif (!$can_afford): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                    Not Enough Coins
                                </button>
                            <?php else: ?>
                                <button class="pet-buy-btn" 
                                        data-item-id="<?php echo $item_id; ?>"
                                        data-item-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-price="<?php echo $item['price']; ?>"
                                        style="width:100%;background:linear-gradient(135deg,#FF9500,#FF6B00);border:none;color:#fff;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(255,149,0,0.4);">
                                    🐾 Adopt Pet for <?php echo number_format($item['price']); ?> Coins
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Regular Store Items -->
                            <?php if (!$can_afford): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;margin-bottom:8px;">
                                    Not Enough Coins
                                </button>
                            <?php else: ?>
                                <button class="store-buy-btn" 
                                        data-item-id="<?php echo $item_id; ?>"
                                        data-item-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-price="<?php echo $item['price']; ?>"
                                        style="width:100%;background:linear-gradient(135deg,#ec4899,#FF00AA);border:none;color:#fff;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(236,72,153,0.4);margin-bottom:8px;">
                                    💰 Purchase for <?php echo number_format($item['price']); ?> Coins
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($in_inventory): ?>
                                <button class="store-activate-btn" 
                                        data-item-id="<?php echo $item_id; ?>"
                                        data-item-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        style="width:100%;background:linear-gradient(135deg,#00D4FF,#06b6d4);border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(0,212,255,0.4);">
                                    ⚡ Activate (<?php echo $inventory_count; ?> available)
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
.store-item-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(6,182,212,0.3);
}
.store-filter-btn:hover {
    transform: scale(1.05);
}
.store-filter-btn.active {
    background: #ec4899 !important;
    border-color: #ec4899 !important;
    color: #fff !important;
}
.store-buy-btn:hover {
    background: #ff00aa;
    transform: scale(1.05);
}
</style>

<script>
// Store functionality
(function() {
    // Category filtering
    var filterBtns = document.querySelectorAll('.store-filter-btn');
    var storeCards = document.querySelectorAll('.store-item-card');
    
    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var category = btn.getAttribute('data-category');
            
            // Update active button
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            
            // Filter cards
            storeCards.forEach(function(card) {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Store purchase and activation handlers are in store_section.php
    
    // Pet Purchase Handling - Use modal system (same as store section)
    var petBuyBtns = document.querySelectorAll('.pet-buy-btn');
    
    petBuyBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var petId = parseInt(btn.getAttribute('data-item-id'));
            var petName = btn.getAttribute('data-item-name');
            var petPrice = parseInt(btn.getAttribute('data-item-price'));
            
            // Map pet IDs to video files
            var petVideoMap = {
                100: 'lynx.mp4', 101: 'tortoise.mp4', 102: 'hamster.mp4',
                103: 'falcon.mp4', 104: 'gryphon.mp4', 105: 'boar.mp4',
                106: 'viper.mp4', 107: 'swan.mp4', 108: 'mischiefcap.mp4',
                109: 'otter.mp4', 110: 'kinkajou.mp4', 111: 'seahorse.mp4',
                112: 'dragonet.mp4', 113: 'mastiff.mp4', 114: 'raven.mp4',
                115: 'tiger.mp4', 116: 'wolf.mp4', 117: 'polar_bear.mp4'
            };
            
            var petVideo = petVideoMap[petId] || 'lynx.mp4';
            
            // Call the modal function (requires avatar_modals.js)
            if (typeof showPetUnlockModal === 'function') {
                showPetUnlockModal(petId, petName, petPrice, petVideo, <?php echo $tokens_available; ?>, <?php echo $coin_balance; ?>);
            } else {
                alert('Error: Pet unlock system not loaded');
            }
        });
    });
    
    // XP Multiplier Timer (Dashboard)
    var timerEl = document.getElementById('xpMultiplierTimer_dashboard');
    if (timerEl) {
        function updateTimer() {
            var expiresAt = parseInt(timerEl.getAttribute('data-expires'));
            var now = Math.floor(Date.now() / 1000);
            var remaining = expiresAt - now;
            
            if (remaining <= 0) {
                timerEl.textContent = 'Expired';
                return;
            }
            
            var hours = Math.floor(remaining / 3600);
            var minutes = Math.floor((remaining % 3600) / 60);
            var seconds = remaining % 60;
            
            timerEl.textContent = hours + 'h ' + minutes + 'm ' + seconds + 's';
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    }
    
    // Mystery box UI is handled centrally in store_section.php to avoid duplicate behavior.
    // No-op here to prevent duplicate handlers when store_section.php is included.
    // If you need to debug, open the store panel's JS in `store_section.php`.
})();
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes scaleIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
@keyframes glow {
    0%, 100% { text-shadow: 0 0 20px currentColor; }
    50% { text-shadow: 0 0 40px currentColor; }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
</style>

<?php
// ===== END STORE =====
?>

</div>
</div>

<audio id="levelUpSound" preload="auto"><source src="<?php echo (new moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false); ?>" type="audio/mpeg"></audio>
<div id="apxModalBackdrop" class="apx-modal-backdrop"></div>
<div id="apxModal" class="apx-modal" role="dialog" aria-modal="true" aria-labelledby="apxModalTitle">
  <div class="apx-modal-header">
    <h1 class="apx-modal-title" id="apxModalTitle">🏆 Badge Earned!</h1>
    <p class="apx-modal-subtitle">Congratulations on your achievement</p>
    <div class="apx-modal-progress"></div>
    <button class="apx-modal-close" id="apxModalClose" aria-label="Close">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </div>
  
  <div class="apx-modal-body">
    <!-- Video Section -->
    <div class="apx-video-container">
      <video id="apxRewardVideo" class="apx-badge-video" playsinline loop>
        <source id="apxRewardVideoSource" src="" type="video/mp4">
      </video>
      <button class="apx-video-fullscreen-btn" id="apxVideoFullscreen" title="Fullscreen">⛶</button>
    </div>
    
    <!-- Badge Name -->
    <div class="apx-badge-name-section">
      <h2 id="apxBName" class="apx-badge-name">Badge Name</h2>
      <p class="apx-badge-category"><span id="apxBCategory">General</span> Badge</p>
    </div>
    
    <!-- Stats -->
    <div class="apx-stats-grid">
      <div class="apx-stat-card">
        <div class="apx-stat-icon">
          <svg width="48" height="48" viewBox="0 0 80 80">
            <defs><linearGradient id="xpIconGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>
            <circle cx="40" cy="40" r="36" fill="url(#xpIconGrad)" />
            <text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">X</text>
          </svg>
        </div>
        <div id="apxBXP" class="apx-stat-value">+0 XP</div>
        <div class="apx-stat-label">Experience Points</div>
      </div>
      
      <div class="apx-stat-card">
        <div class="apx-stat-icon">
          <img id="apxCoinIcon" src="<?php echo s($stack_fallback->out(false)); ?>" alt="Coins" style="width:32px;height:32px;object-fit:contain;" onerror="this.style.display='none';">
        </div>
        <div id="apxBCoins" class="apx-stat-value">+0 Coins</div>
        <div class="apx-stat-label">Ascend Assets</div>
      </div>
    </div>
    
    <!-- Info Grid -->
    <div class="apx-info-section">
      <div class="apx-info-grid">
        <div class="apx-info-item">
          <div class="apx-info-label">Course</div>
          <div id="apxBCourse" class="apx-info-value"></div>
        </div>
        <div class="apx-info-item">
          <div class="apx-info-label">Earned On</div>
          <div id="apxBWhen" class="apx-info-value"></div>
        </div>
      </div>
    </div>
    
    <!-- Description -->
    <div class="apx-description-section">
      <h3 class="apx-description-title">Achievement Details</h3>
      <p id="apxBWhy" class="apx-description-text"></p>
    </div>
    
    <!-- Activities -->
    <div id="apxBActivities" class="apx-activities-section" style="display:none;">
      <h3 class="apx-activities-title">Qualifying Activities</h3>
      <ul id="apxBActivitiesList" class="apx-activities-list"></ul>
    </div>

    <!-- Contributing Badges -->
    <div id="apxBBadges" class="apx-badges-section" style="display:none;">
      <h3 class="apx-badges-title">Contributing Badges</h3>
      <ul id="apxBBadgesList" class="apx-badges-list"></ul>
    </div>
    
    <!-- Rank -->
    <div id="apxBRank" class="apx-rank-section" style="display:none;"></div>
  </div>
</div>
<script>
// Chevron toggle for collapsible panels
document.addEventListener('DOMContentLoaded', function() {
  var panels = document.querySelectorAll('.aa-panel-head');
  panels.forEach(function(h) {
    h.addEventListener('click', function() {
      this.classList.toggle('open');
    });
  });
});

(function(){
  function q(sel){return document.querySelector(sel);}
  
  const modal = q('#apxModal');
  const backdrop = q('#apxModalBackdrop');
  const closeBtn = q('#apxModalClose');
  const video = q('#apxRewardVideo');
  const videoSource = q('#apxRewardVideoSource');
  const fullscreenBtn = q('#apxVideoFullscreen');
  
  // Map badge IDs to their video files
  const badgeVideos = {
    6: 'Getting Started/getting_started_2.mp4',
    4: 'On a Roll/on_a_roll_2.mp4',
    5: 'Halfway Hero/halfway_hero_1.mp4',
    8: 'Master Navigator/master_navigator_3.mp4',
    9: 'Early Bird/early_bird_1.mp4',
    11: 'Sharp Shooter/sharp_shooter_1.mp4',
    10: 'Deadline Burner/deadline_burner_1.mp4',
    12: 'Time Tamer/time_tamer_4.mp4',
    13: 'Feedback Follower/feedback_follower_1.mp4',
    15: 'Steady Improver/steady_improver_1.mp4',
    14: 'Tenacious Tiger/tenacious_tiger_1.mp4',
    16: 'Glory Guide/glory_guide_3.mp4',
    19: 'High Flyer/high_flyer_3.mp4',
    17: 'Activity Ace/activity_ace_3.mp4',
    7: 'Mission Complete/mission_complete_1.mp4',
    20: 'Learning Legend/learning_legend_5.mp4'
  };
  
  function openModal(el){
    const d = el.dataset;
    q('#apxBName').textContent = d.badge || '';
    q('#apxBCategory').textContent = d.category || '';
    q('#apxBCourse').textContent = d.course || '';
    q('#apxBWhen').textContent = d.when || '';
    q('#apxBWhy').textContent = d.why || '';
    
    // Extract coins value from the text (e.g., "+10 Ascend Assets" -> 10)
    const coinsText = d.coins || '+0 Ascend Assets';
    const coins = parseInt(coinsText.match(/\d+/)) || 0;
    
    // Calculate badge XP (coins / 2)
    const badgeXp = Math.floor(coins / 2);
    
    q('#apxBXP').textContent = '+' + badgeXp + ' XP';
    q('#apxBCoins').textContent = '+' + coins + ' Coins';
    
    // Fetch and display completed activities or contributing badges
    const courseid = parseInt(d.courseid) || 0;
    const badgeId = parseInt(d.badgeid) || 0;
    const metaBadges = [8, 12, 16, 20]; // Master Navigator, Time Tamer, Glory Guide, Learning Legend
    const isMeta = metaBadges.includes(badgeId);
    
    if(courseid > 0) {
      const fetchStart = performance.now();
      fetch(M.cfg.wwwroot + '/local/ascend_rewards/get_activities.php?courseid=' + courseid + '&badgeid=' + badgeId + '&force=1')
        .then(response => response.json())
        .then(data => {
          const fetchEnd = performance.now();
          const fetchTime = Math.round(fetchEnd - fetchStart);
          console.log('=== BADGE MODAL DEBUG ===');
          console.log('BadgeId:', badgeId, 'CourseId:', courseid, 'isMeta:', isMeta);
          console.log('Activities returned:', data.activities);
          console.log('Metadata returned:', data.metadata);
          console.log('Cached:', data.cached);
          console.log('========================');
          if(data && data.activities && data.activities.length > 0) {
            // Check if these are badge names (meta badges) or activity names
            const knownBadgeNames = ['Getting Started', 'On a Roll', 'Halfway Hero', 'Early Bird', 
                                     'Sharp Shooter', 'Deadline Burner', 'Feedback Follower', 
                                     'Steady Improver', 'Tenacious Tiger', 'High Flyer', 
                                     'Activity Ace', 'Mission Complete'];
            const areBadges = data.activities.some(item => knownBadgeNames.includes(item));

            // Always show grouped activities for all badges except meta badges
            if(isMeta) {
              // Show as contributing badges
              const badgesDiv = q('#apxBBadges');
              const badgesList = q('#apxBBadgesList');
              badgesList.innerHTML = '';
              data.activities.forEach(function(badge) {
                const li = document.createElement('li');
                li.className = 'apx-badge-item';
                li.textContent = badge;
                badgesList.appendChild(li);
              });
              badgesDiv.style.display = 'block';
              q('#apxBActivities').style.display = 'none';
            } else {
              // Show as qualifying activities with award indicators (x1, x2, x3 like badge count) for ALL badges
              const activitiesDiv = q('#apxBActivities');
              const activitiesList = q('#apxBActivitiesList');
              
              console.log('Populating activities div...');
              console.log('activitiesDiv exists:', !!activitiesDiv);
              console.log('activitiesList exists:', !!activitiesList);
              
              activitiesList.innerHTML = '';

              const metadata = data.metadata || [];
              const hasMetadata = metadata.length > 0;
              
              console.log('hasMetadata:', hasMetadata, 'metadata length:', metadata.length);

              let currentAward = 0;

              data.activities.forEach(function(activity, index) {
                const meta = hasMetadata ? metadata[index] : null;

                // Check if we're starting a new award group
                if(meta && meta.award_number && meta.award_number !== currentAward) {
                  // Add spacing between groups (except before first group)
                  if(currentAward > 0) {
                    const spacer = document.createElement('li');
                    spacer.style.height = '12px';
                    spacer.style.listStyle = 'none';
                    activitiesList.appendChild(spacer);
                  }

                  const header = document.createElement('li');
                  header.className = 'apx-award-header';
                  header.style.fontWeight = '700';
                  header.style.fontSize = '0.95em';
                  header.style.color = '#FFD700';
                  header.style.marginTop = '8px';
                  header.style.marginBottom = '6px';
                  header.style.listStyle = 'none';

                  // Create badge count indicator like rewards page (gold gradient)
                  const badgeCount = document.createElement('span');
                  badgeCount.className = 'aa-badge-count';
                  badgeCount.style.background = 'linear-gradient(135deg, #FFD700 0%, #FFA500 100%)';
                  badgeCount.style.color = '#0b1530';
                  badgeCount.style.padding = '4px 8px';
                  badgeCount.style.borderRadius = '12px';
                  badgeCount.style.fontSize = '0.85em';
                  badgeCount.style.fontWeight = '800';
                  badgeCount.style.marginRight = '8px';
                  badgeCount.style.display = 'inline-block';
                  badgeCount.style.boxShadow = '0 2px 8px rgba(255, 215, 0, 0.4)';
                  badgeCount.textContent = 'x' + meta.award_number;

                  header.appendChild(badgeCount);
                  header.appendChild(document.createTextNode('Award #' + meta.award_number + ':'));

                  activitiesList.appendChild(header);
                  currentAward = meta.award_number;
                }

                // Add the activity
                const li = document.createElement('li');
                li.className = 'apx-activity-item';
                li.style.marginLeft = '20px';
                li.style.fontSize = '0.9em';
                li.style.color = '#A5B4D6';
                li.style.marginBottom = '4px';

                let activityHTML = '';

                // Steady Improver: Show fail→pass with icons
                if(meta && meta.failed_grade !== undefined && meta.passed_grade !== undefined) {
                  activityHTML = '<span style="color:#ff4444;font-weight:600;">✗ Failed (' + meta.failed_grade + '%)</span> → <span style="color:#00cc66;font-weight:600;">✓ Passed (' + meta.passed_grade + '%)</span><br/><span style="margin-left:20px;">' + activity + '</span>';
                }
                // Feedback Follower: Show improvement
                else if(meta && meta.old_grade !== undefined && meta.new_grade !== undefined) {
                  activityHTML = activity + ' <span style="color:#4ECDC4;font-weight:600;">(' + meta.old_grade + '% → ' + meta.new_grade + '%)</span>';
                }
                // Regular activity
                else {
                  activityHTML = activity;
                }

                li.innerHTML = activityHTML;
                activitiesList.appendChild(li);
              });

              console.log('Final activities list children:', activitiesList.children.length);
              console.log('Final activities list HTML:', activitiesList.innerHTML);
              activitiesDiv.style.display = 'block';
              q('#apxBBadges').style.display = 'none';
              
            }
          } else {
            q('#apxBActivities').style.display = 'none';
            q('#apxBBadges').style.display = 'none';
          }
        })
        .catch((err) => {
          q('#apxBActivities').style.display = 'none';
          q('#apxBBadges').style.display = 'none';
        });
    }
    // Don't hide activities here - let the fetch handler control visibility
    
    // Load the correct video for this badge (badgeId already declared above)
    const videoFile = badgeVideos[badgeId] || 'reward_animation_2.mp4';
    const videoUrl = M.cfg.wwwroot + '/local/ascend_rewards/pix/' + videoFile;
    
    if(video && videoSource){
      videoSource.src = videoUrl;
      video.load();
      video.style.display='block';
      video.currentTime=0;
      video.play().catch(()=>{});
      if(fullscreenBtn) fullscreenBtn.style.display='block';
    }
    modal.style.display='block';
    backdrop.style.display='block';
    document.body.style.overflow='hidden';
  }
  
  function closeModal(){
    modal.style.display='none';
    backdrop.style.display='none';
    document.body.style.overflow='';
    if(video){
      video.pause();
      video.style.display='none';
    }
    if(fullscreenBtn) fullscreenBtn.style.display='none';
  }
  
  document.querySelectorAll('.js-badge-detail').forEach(function(el){
    el.addEventListener('click', function(){ openModal(el); });
  });
  
  if(closeBtn) closeBtn.addEventListener('click', closeModal);
  if(backdrop) backdrop.addEventListener('click', closeModal);
  
  // Fullscreen button handler
  if(fullscreenBtn && video) {
    fullscreenBtn.addEventListener('click', function() {
      if(video.requestFullscreen) {
        video.requestFullscreen();
      } else if(video.webkitRequestFullscreen) {
        video.webkitRequestFullscreen();
      } else if(video.msRequestFullscreen) {
        video.msRequestFullscreen();
      }
    });
  }
  
  document.addEventListener('keydown', function(e){
    if(e.key==='Escape' && modal.style.display==='block') closeModal();
  });
})();

// Leaderboard Context View
(function() {
  const btnTopView = document.getElementById('btnTopView');
  const btnContextView = document.getElementById('btnContextView');
  const leaderboardList = document.getElementById('leaderboardList');
  const leaderboardMode = document.getElementById('leaderboardMode');
  
  let originalHTML = leaderboardList ? leaderboardList.innerHTML : '';
  let isContextView = false;
  
  function switchToContextView() {
    if (!leaderboardList) return;
    
    // Show loading
    leaderboardList.innerHTML = '<li class="aa-muted" style="text-align:center;padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading your position...</li>';
    
    // Fetch context data
    const courseid = <?php echo (int)$courseid; ?>;
    const url = '<?php echo (new moodle_url('/local/ascend_rewards/index.php'))->out(false); ?>?apex_action=get_leaderboard_context&courseid=' + courseid + '&sesskey=' + M.cfg.sesskey;
    
    fetch(url)
      .then(res => res.json())
      .then(data => {
        if (!data.success || !data.users) {
          leaderboardList.innerHTML = '<li class="aa-muted">Error loading leaderboard context.</li>';
          return;
        }
        
        let html = '';
        data.users.forEach((user, idx) => {
          const isCurrentUser = user.is_current_user;
          const displayName = isCurrentUser ? 'You' : 'User #' + user.userid;
          const userIdBadge = isCurrentUser ? '<span class="user-id-badge">ID: ' + <?php echo (int)$USER->id; ?> + '</span>' : '';
          const medal = user.medal;
          const xp = user.xp.toLocaleString();
          const currentClass = isCurrentUser ? ' class="current-user"' : '';
          const gradId = 'xpIconGradLBCtx' + idx;
          
          html += '<li' + currentClass + '>';
          html += '<span class="pos">' + medal + '</span>';
          html += '<strong>' + displayName + '</strong>';
          html += userIdBadge;
          html += '<div class="xp-display" style="display:flex;align-items:center;gap:8px;margin-left:auto;">';
          html += '<svg width="24" height="24" viewBox="0 0 80 80" style="flex-shrink:0;">';
          html += '<defs><linearGradient id="' + gradId + '" x1="0%" y1="0%" x2="100%" y2="100%">';
          html += '<stop offset="0%" stop-color="#00D4FF" />';
          html += '<stop offset="50%" stop-color="#FF00AA" />';
          html += '<stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>';
          html += '<circle cx="40" cy="40" r="36" fill="url(#' + gradId + ')" />';
          html += '<text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">X</text>';
          html += '</svg>';
          html += '<span class="aa-muted" style="font-weight:700;color:#e6e9f0;">' + xp + '</span>';
          html += '</div>';
          html += '</li>';
        });
        
        leaderboardList.innerHTML = html;
        leaderboardMode.textContent = '(Ranks ' + data.start_rank + '-' + data.end_rank + ' of ' + data.total_users + ')';
        
        // Scroll to current user
        setTimeout(() => {
          const currentUserLi = leaderboardList.querySelector('li.current-user');
          if (currentUserLi) {
            currentUserLi.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }, 100);
        
        isContextView = true;
        btnTopView.classList.remove('active');
        btnContextView.classList.add('active');
        btnTopView.style.background = '#01142E';
        btnTopView.style.border = '2px solid #ec4899';
        btnTopView.style.color = '#ec4899';
        btnContextView.style.background = '#06b6d4';
        btnContextView.style.border = 'none';
        btnContextView.style.color = '#fff';
      })
      .catch(err => {
        leaderboardList.innerHTML = '<li class="aa-muted">Error loading context view.</li>';
      });
  }
  
  function switchToTopView() {
    if (!leaderboardList) return;
    
    leaderboardList.innerHTML = originalHTML;
    leaderboardMode.textContent = '(Top 10<?php if (!is_null($courseid)) echo ' - ' . s($selectedcoursename); ?>)';
    
    isContextView = false;
    btnTopView.classList.add('active');
    btnContextView.classList.remove('active');
    btnTopView.style.background = '#ec4899';
    btnTopView.style.border = 'none';
    btnTopView.style.color = '#fff';
    btnContextView.style.background = '#01142E';
    btnContextView.style.border = '2px solid #06b6d4';
    btnContextView.style.color = '#06b6d4';
  }
  
  // Event listeners
  if (btnContextView) {
    btnContextView.addEventListener('click', switchToContextView);
  }
  
  if (btnTopView) {
    btnTopView.addEventListener('click', switchToTopView);
  }
})();
</script>

<?php
echo $OUTPUT->footer();
