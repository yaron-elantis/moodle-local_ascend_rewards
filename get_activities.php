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

$perf_start = microtime(true);
$perf_log = [];

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

$perf_log['init'] = round((microtime(true) - $perf_start) * 1000, 2);

header('Content-Type: application/json');

// Helper function to get activity name by course module ID
function get_activity_name_by_cmid($cmid) {
    global $DB;
    $cm = $DB->get_record_sql("
        SELECT m.name as modname, cm.instance
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module
         WHERE cm.id = :cmid
    ", ['cmid' => $cmid]);
    
    if (!$cm) return null;
    
    $modtable = $cm->modname;
    $mod = $DB->get_record($modtable, ['id' => $cm->instance], 'name');
    
    if (!$mod) return null;
    
    return ucfirst($cm->modname) . ": {$mod->name}";
}

$courseid = optional_param('courseid', 0, PARAM_INT);
$badgeid = optional_param('badgeid', 0, PARAM_INT);
$force_recalculate = optional_param('force', 0, PARAM_BOOL);
$debug_timing = optional_param('debug', 0, PARAM_BOOL);

if ($courseid <= 0 || $badgeid <= 0) {
    echo json_encode(['activities' => [], 'metadata' => []]);
    exit;
}

// Try to get from cache first (unless forced to recalculate)
$cache_start = microtime(true);
if (!$force_recalculate) {
    $cached = $DB->get_record('local_ascend_badge_cache', [
        'userid' => $USER->id,
        'courseid' => $courseid,
        'badgeid' => $badgeid
    ]);
    
    $perf_log['cache_check'] = round((microtime(true) - $cache_start) * 1000, 2);
    
    if ($cached) {
        $decode_start = microtime(true);
        $activities_data = json_decode($cached->activities, true);
        $metadata_data = json_decode($cached->metadata, true);
        $perf_log['decode'] = round((microtime(true) - $decode_start) * 1000, 2);
        $perf_log['total'] = round((microtime(true) - $perf_start) * 1000, 2);
        
        // Return cached data
        $response = [
            'activities' => $activities_data,
            'metadata' => $metadata_data,
            'cached' => true
        ];
        
        if ($debug_timing) {
            $response['performance'] = $perf_log;
        }
        
        echo json_encode($response);
        exit;
    }
} else {
    $perf_log['cache_check'] = 0; // Skipped
}

// Always calculate qualifying activities based on badge award logic
$activities = [];
$metadata = []; // Store additional info like groups, pass/fail status, improvements

// Calculate actual qualifying activities based on badge type
{
    // Meta badges show contributing badges instead of activities
    $meta_badges = [8, 12, 16, 20]; // Master Navigator, Time Tamer, Glory Guide, Learning Legend
    
    if (in_array($badgeid, $meta_badges)) {
        // Get contributing badges for meta badges
        $contributing_badge_ids = [];
        switch ($badgeid) {
            case 8: // Master Navigator
                $contributing_badge_ids = [6, 4, 5]; // Getting Started, On a Roll, Halfway Hero
                break;
            case 12: // Time Tamer
                $contributing_badge_ids = [9, 11, 10]; // Early Bird, Sharp Shooter, Deadline Burner
                break;
            case 16: // Glory Guide
                $contributing_badge_ids = [13, 15, 14]; // Feedback Follower, Steady Improver, Tenacious Tiger
                break;
            case 20: // Learning Legend
                $contributing_badge_ids = [19, 17, 7]; // High Flyer, Activity Ace, Mission Complete
                break;
        }
        
        // Get names of earned contributing badges
        $badge_names = [
            4 => 'On a Roll', 5 => 'Halfway Hero', 6 => 'Getting Started', 7 => 'Mission Complete',
            9 => 'Early Bird', 10 => 'Deadline Burner', 11 => 'Sharp Shooter',
            13 => 'Feedback Follower', 14 => 'Tenacious Tiger', 15 => 'Steady Improver',
            17 => 'Activity Ace', 19 => 'High Flyer'
        ];
        
        foreach ($contributing_badge_ids as $cbid) {
            $exists = $DB->record_exists('local_ascend_rewards_coins', [
                'userid' => $USER->id,
                'badgeid' => $cbid,
                'courseid' => $courseid
            ]);
            if ($exists && isset($badge_names[$cbid])) {
                $activities[] = $badge_names[$cbid];
            }
        }
    } else {
        // Regular badges - get qualifying activities based on badge award logic
        switch ($badgeid) {
            case 4: // On a Roll - 2 consecutive activities per award, show activity pairs grouped by award
                // Read pipe-delimited pair activities from preferences
                $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
                $pref_json = get_user_preferences($pref_key, '', $USER->id);
                
                if ($pref_json) {
                    $all_pairs = json_decode($pref_json, true);
                    
                    if (is_array($all_pairs) && count($all_pairs) > 0) {
                        $total_pairs = count($all_pairs);
                        $pair_count = 0;
                        
                        // Parse each pipe-delimited pair
                        foreach ($all_pairs as $pair_key) {
                            $pair_parts = explode('|', $pair_key, 2);
                            
                            if (count($pair_parts) === 2) {
                                $pair_count++;
                                $award_number = $pair_count;
                                
                                // Check if this is cmid-based (numeric) or name-based (text) format
                                $is_cmid_format = is_numeric($pair_parts[0]) && is_numeric($pair_parts[1]);
                                
                                if ($is_cmid_format) {
                                    // Convert cmids to activity names
                                    $cmid1 = intval($pair_parts[0]);
                                    $cmid2 = intval($pair_parts[1]);
                                    
                                    $activity_names = array_filter([
                                        get_activity_name_by_cmid($cmid1),
                                        get_activity_name_by_cmid($cmid2)
                                    ]);
                                    
                                    if (count($activity_names) === 2) {
                                        foreach ($activity_names as $activity_name) {
                                            $activities[] = $activity_name;
                                            $metadata[] = [
                                                'award_number' => $award_number,
                                                'award_count' => $total_pairs,
                                                'is_pair_member' => true
                                            ];
                                        }
                                    }
                                } else {
                                    // Old format: activity names are stored directly
                                    foreach ($pair_parts as $activity_name) {
                                        $activities[] = $activity_name;
                                        $metadata[] = [
                                            'award_number' => $award_number,
                                            'award_count' => $total_pairs,
                                            'is_pair_member' => true
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
                break;
                
            case 6: // Getting Started - 1+ completed activities
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                      ORDER BY cmc.timemodified ASC
                         LIMIT 1";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                foreach ($completed as $cm) {
                    $modtable = $cm->modname;
                    try {
                        if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => 1,
                                'award_count' => 1,
                                'single_activity' => true
                            ];
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                break;
                
            case 5: // Halfway Hero - 50% course completion
            case 7: // Mission Complete - 100% course completion
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                      ORDER BY cmc.timemodified DESC
                         LIMIT 10";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                foreach ($completed as $cm) {
                    $modtable = $cm->modname;
                    try {
                        if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => 1,
                                'award_count' => 1
                            ];
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                break;
                
            case 9: // Early Bird - each activity awards badge once, show with x1, x2, x3
                $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
                $rawpref = get_user_preferences($pref_key, '', $USER->id);
                if ($rawpref) {
                    // Determine format: legacy numeric CSV of cmids OR JSON array of activity names
                    $is_numeric_csv = preg_match('/^\d+(,\d+)*$/', $rawpref);
                    if ($is_numeric_csv) {
                        // Legacy path (cm ids stored as comma-separated numbers)
                        $awarded_array = explode(',', $rawpref);
                        $total_awards = count($awarded_array);
                        list($insql, $params) = $DB->get_in_or_equal($awarded_array, SQL_PARAMS_NAMED);
                        $params['courseid'] = $courseid;
                        $sql = "SELECT cm.id, cm.instance, m.name as modname
                                  FROM {course_modules} cm
                                  JOIN {modules} m ON m.id = cm.module
                                 WHERE cm.id $insql
                                   AND cm.course = :courseid";
                        $cms = $DB->get_records_sql($sql, $params);
                        // Group by module type for batch instance fetch
                        $by_module = [];
                        foreach ($cms as $cm) {
                            if (!isset($by_module[$cm->modname])) {
                                $by_module[$cm->modname] = [];
                            }
                            $by_module[$cm->modname][] = $cm->instance;
                        }
                        $activity_names = [];
                        foreach ($by_module as $modname => $instances) {
                            list($insql2, $params2) = $DB->get_in_or_equal($instances, SQL_PARAMS_NAMED);
                            $instances_data = $DB->get_records_select($modname, "id $insql2", $params2, '', 'id,name');
                            foreach ($instances_data as $inst) {
                                $activity_names[$inst->id] = $inst->name;
                            }
                        }
                        foreach ($awarded_array as $idx => $cmid) {
                            if (isset($cms[$cmid])) {
                                $cm = $cms[$cmid];
                                if (isset($activity_names[$cm->instance])) {
                                    $activities[] = $activity_names[$cm->instance];
                                    $metadata[] = [
                                        'award_number' => $idx + 1,
                                        'award_count' => $total_awards,
                                        'single_activity' => true
                                    ];
                                }
                            }
                        }
                    } else {
                        // New path: JSON array of activity names stored in preference
                        $decoded = json_decode($rawpref, true);
                        if (is_array($decoded)) {
                            $total_awards = count($decoded);
                            foreach ($decoded as $idx => $name) {
                                $activities[] = $name;
                                $metadata[] = [
                                    'award_number' => $idx + 1,
                                    'award_count' => $total_awards,
                                    'single_activity' => true
                                ];
                            }
                        }
                    }
                }
                break;
                
            case 10: // Deadline Burner - ALL activities completed before their deadlines
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                           AND m.name IN ('assign', 'quiz')
                      ORDER BY cmc.timemodified DESC";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                foreach ($completed as $cm) {
                    try {
                        if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => 1,
                                'award_count' => 1
                            ];
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                break;
                
            case 11: // Sharp Shooter - preferences store JSON array of cmid pairs (e.g., ["2|3", "4|5"])
                $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
                $rawpref = get_user_preferences($pref_key, '', $USER->id);
                if ($rawpref) {
                    $decoded = json_decode($rawpref, true);
                    if (is_array($decoded) && count($decoded) > 0) {
                        $award_num = 1;
                        $total_awards = 0;
                        
                        // Count valid pairs (pipe-delimited cmid pairs)
                        foreach ($decoded as $entry) {
                            if (strpos($entry, '|') !== false && preg_match('/^\d+\|\d+$/', $entry)) {
                                $total_awards++;
                            }
                        }
                        
                        // Process each pair entry
                        foreach ($decoded as $entry) {
                            if (strpos($entry, '|') !== false && preg_match('/^\d+\|\d+$/', $entry)) {
                                // This is a cmid-based pair (e.g., "2|3")
                                $cmids = explode('|', $entry);
                                foreach ($cmids as $cmid) {
                                    $name = get_activity_name_by_cmid((int)$cmid);
                                    if ($name) {
                                        $activities[] = $name;
                                        $metadata[] = [
                                            'award_number' => $award_num,
                                            'award_count' => $total_awards
                                        ];
                                    }
                                }
                                $award_num++;
                            } else if (!strpos($entry, '|') && !is_numeric($entry)) {
                                // Legacy format: activity name (e.g., "Assign: Test 1")
                                // Keep for backward compatibility with old data
                                $activities[] = $entry;
                                if (!isset($legacy_total_awards)) {
                                    $legacy_total_awards = count(array_filter($decoded, 
                                        function($e) { return !strpos($e, '|') && !is_numeric($e); }
                                    ));
                                }
                                $metadata[] = [
                                    'award_number' => isset($legacy_award_num) ? $legacy_award_num : 1,
                                    'award_count' => isset($legacy_total_awards) ? $legacy_total_awards : 1
                                ];
                                if (!isset($legacy_award_num)) { $legacy_award_num = 1; }
                                if ($entry === end(array_filter($decoded, 
                                    function($e) { return !strpos($e, '|') && !is_numeric($e); }))) {
                                    $legacy_award_num++;
                                }
                            }
                        }
                    }
                }
                break;
                
            case 13: // Feedback Follower - each improved activity awards badge, show with x1, x2, x3
                $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
                $awarded_ids = get_user_preferences($pref_key, '', $USER->id);
                
                if ($awarded_ids) {
                    $awarded_array = explode(',', $awarded_ids);
                    $total_awards = count($awarded_array);
                    
                    foreach ($awarded_array as $idx => $cmid) {
                        if ($cm = $DB->get_record('course_modules', ['id' => $cmid])) {
                            $mod = $DB->get_record('modules', ['id' => $cm->module]);
                            if ($mod && $modinstance = $DB->get_record($mod->name, ['id' => $cm->instance], 'name')) {
                                // Get grade info to show improvement
                                $grade_item = $DB->get_record('grade_items', [
                                    'itemtype' => 'mod',
                                    'itemmodule' => $mod->name,
                                    'iteminstance' => $cm->instance,
                                    'courseid' => $courseid
                                ]);
                                
                                $old_grade = 0;
                                $new_grade = 0;
                                if ($grade_item) {
                                    $grades = $DB->get_records('grade_grades', 
                                        ['itemid' => $grade_item->id, 'userid' => $USER->id], 
                                        'timemodified ASC'
                                    );
                                    if (count($grades) >= 2) {
                                        $grades_array = array_values($grades);
                                        $first = $grades_array[0];
                                        $last = end($grades_array);
                                        $old_grade = $first->finalgrade ? round(($first->finalgrade / $grade_item->grademax) * 100) : 0;
                                        $new_grade = $last->finalgrade ? round(($last->finalgrade / $grade_item->grademax) * 100) : 0;
                                    }
                                }
                                
                                $activities[] = $modinstance->name;
                                $metadata[] = [
                                    'award_number' => $idx + 1,
                                    'award_count' => $total_awards,
                                    'single_activity' => true,
                                    'old_grade' => $old_grade,
                                    'new_grade' => $new_grade
                                ];
                            }
                        }
                    }
                }
                break;
                
            case 14: // Tenacious Tiger - 2+ improved activities (show only activities with improvements)
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                           AND m.name IN ('assign', 'quiz')
                      ORDER BY cmc.timemodified DESC";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                
                // Filter for only activities with grade improvements
                foreach ($completed as $cm) {
                    try {
                        $grade_item = $DB->get_record('grade_items', [
                            'itemtype' => 'mod',
                            'itemmodule' => $cm->modname,
                            'iteminstance' => $cm->instance,
                            'courseid' => $courseid
                        ]);
                        
                        if ($grade_item) {
                            $grades = $DB->get_records('grade_grades', 
                                ['itemid' => $grade_item->id, 'userid' => $USER->id], 
                                'timemodified ASC'
                            );
                            
                            // Only include if there are multiple attempts with improvement
                            if (count($grades) >= 2) {
                                $grades_array = array_values($grades);
                                $first = $grades_array[0];
                                $last = end($grades_array);
                                
                                if ($last->finalgrade > $first->finalgrade) {
                                    if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                                        $old_pct = $first->finalgrade ? round(($first->finalgrade / $grade_item->grademax) * 100) : 0;
                                        $new_pct = $last->finalgrade ? round(($last->finalgrade / $grade_item->grademax) * 100) : 0;
                                        
                                        $activities[] = $modinstance->name;
                                        $metadata[] = [
                                            'award_number' => 1,
                                            'award_count' => 1,
                                            'old_grade' => $old_pct,
                                            'new_grade' => $new_pct
                                        ];
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                break;
                
            case 15: // Steady Improver - each failed→passed activity awards badge, show with x1, x2, x3
                $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
                $awarded_ids = get_user_preferences($pref_key, '', $USER->id);
                
                if ($awarded_ids) {
                    $awarded_array = explode(',', $awarded_ids);
                    $total_awards = count($awarded_array);
                    
                    foreach ($awarded_array as $idx => $cmid) {
                        if ($cm = $DB->get_record('course_modules', ['id' => $cmid])) {
                            $mod = $DB->get_record('modules', ['id' => $cm->module]);
                            if ($mod && $modinstance = $DB->get_record($mod->name, ['id' => $cm->instance], 'name')) {
                                // Get grade progression
                                $grade_item = $DB->get_record('grade_items', [
                                    'itemtype' => 'mod',
                                    'itemmodule' => $mod->name,
                                    'iteminstance' => $cm->instance,
                                    'courseid' => $courseid
                                ]);
                                
                                $failed_grade = 0;
                                $passed_grade = 0;
                                if ($grade_item) {
                                    $grades = $DB->get_records('grade_grades', 
                                        ['itemid' => $grade_item->id, 'userid' => $USER->id], 
                                        'timemodified ASC'
                                    );
                                    if (count($grades) >= 2) {
                                        $grades_array = array_values($grades);
                                        $first = $grades_array[0];
                                        $last = end($grades_array);
                                        $failed_grade = $first->finalgrade ? round(($first->finalgrade / $grade_item->grademax) * 100) : 0;
                                        $passed_grade = $last->finalgrade ? round(($last->finalgrade / $grade_item->grademax) * 100) : 0;
                                    }
                                }
                                
                                $activities[] = $modinstance->name;
                                $metadata[] = [
                                    'award_number' => $idx + 1,
                                    'award_count' => $total_awards,
                                    'single_activity' => true,
                                    'failed_grade' => $failed_grade,
                                    'passed_grade' => $passed_grade
                                ];
                            }
                        }
                    }
                }
                break;
                
            case 17: // Activity Ace - 100% first-attempt passes on ALL graded activities
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                           AND m.name IN ('assign', 'quiz')
                      ORDER BY cmc.timemodified DESC";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                foreach ($completed as $cm) {
                    try {
                        if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => 1,
                                'award_count' => 1
                            ];
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                break;
                
            case 19: // High Flyer - awards every 2 passed activities, show each pair with x1, x2, x3
                // Count how many times this badge has been awarded
                $award_count = $DB->count_records('local_ascend_rewards_coins', [
                    'userid' => $USER->id,
                    'badgeid' => $badgeid,
                    'courseid' => $courseid
                ]);
                
                // Get all passed activities in order
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance, cmc.timemodified
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                           AND m.name IN ('assign', 'quiz')
                      ORDER BY cmc.timemodified ASC";
                $all_completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                
                // Filter for passed activities (grade >= 60%)
                $passed = [];
                foreach ($all_completed as $cm) {
                    $grade_item = $DB->get_record('grade_items', [
                        'itemtype' => 'mod',
                        'itemmodule' => $cm->modname,
                        'iteminstance' => $cm->instance,
                        'courseid' => $courseid
                    ]);
                    
                    if ($grade_item) {
                        $grade = $DB->get_record('grade_grades', [
                            'itemid' => $grade_item->id,
                            'userid' => $USER->id
                        ]);
                        
                        if ($grade && $grade->finalgrade !== null) {
                            $percentage = ($grade->finalgrade / $grade_item->grademax) * 100;
                            if ($percentage >= 60) {
                                $passed[] = $cm;
                            }
                        }
                    }
                }
                
                // Show all pairs up to award count
                $total_pairs = $award_count > 0 ? $award_count : 1;
                
                for ($pair = 0; $pair < $total_pairs; $pair++) {
                    $start_index = $pair * 2;
                    $pair_activities = array_slice($passed, $start_index, 2);
                    
                    foreach ($pair_activities as $cm) {
                        try {
                            if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                                $activities[] = $modinstance->name;
                                $metadata[] = [
                                    'award_number' => $pair + 1,
                                    'award_count' => $total_pairs
                                ];
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
                break;
                
            default: // Other badges - show recent completed activities
                $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cmc.userid = :userid
                           AND cmc.completionstate IN (1, 2)
                      ORDER BY cmc.timemodified DESC
                         LIMIT 10";
                $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);
                $award_num = 1;
                foreach ($completed as $cm) {
                    $modtable = $cm->modname;
                    try {
                        if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => $award_num,
                                'award_count' => count($completed),
                                'single_activity' => true
                            ];
                            $award_num++;
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
        }
    }
}

// Cache the results for fast future retrieval
$now = time();
$cache_record = $DB->get_record('local_ascend_badge_cache', [
    'userid' => $USER->id,
    'courseid' => $courseid,
    'badgeid' => $badgeid
]);

if ($cache_record) {
    // Update existing cache
    $cache_record->activities = json_encode($activities);
    $cache_record->metadata = json_encode($metadata);
    $cache_record->timemodified = $now;
    $DB->update_record('local_ascend_badge_cache', $cache_record);
} else {
    // Insert new cache
    $DB->insert_record('local_ascend_badge_cache', (object)[
        'userid' => $USER->id,
        'courseid' => $courseid,
        'badgeid' => $badgeid,
        'activities' => json_encode($activities),
        'metadata' => json_encode($metadata),
        'timecreated' => $now,
        'timemodified' => $now
    ]);
}

$perf_log['calculation'] = round((microtime(true) - $cache_start) * 1000, 2);
$perf_log['total'] = round((microtime(true) - $perf_start) * 1000, 2);

$response = [
    'activities' => $activities,
    'metadata' => $metadata,
    'cached' => false
];

if ($debug_timing) {
    $response['performance'] = $perf_log;
}

echo json_encode($response);
