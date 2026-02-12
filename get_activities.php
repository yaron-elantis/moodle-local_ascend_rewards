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
 * AJAX endpoint returning activities contributing to a badge.
 *
 * This file remains as a shared implementation used by ajax_service in
 * include-mode (`$returndata = true`) and as a backward-compatible
 * direct endpoint.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (empty($returndata)) {
    define('AJAX_SCRIPT', true);
}
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
require_capability('local/ascend_rewards:view', $context);
// This endpoint favors stability over broad renaming/reflow.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
$perfstart = microtime(true);
$perflog = [];

global $DB, $USER;

$perflog['init'] = round((microtime(true) - $perfstart) * 1000, 2);

if (empty($returndata)) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['activities' => [], 'metadata' => [], 'cached' => false]);
        exit;
    }
    require_sesskey();
    header('Content-Type: application/json');
}

/**
 * Helper function to get activity name by course module id.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param int $cmid Course module id.
 * @return string|null
 */
function local_ascend_rewards_get_activity_name_by_cmid($cmid) {
    global $DB;
    $cm = $DB->get_record_sql("
        SELECT m.name as modname, cm.instance
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module
         WHERE cm.id = :cmid
    ", ['cmid' => $cmid]);

    if (!$cm) {
        return null;
    }

    $modtable = $cm->modname;
    $mod = $DB->get_record($modtable, ['id' => $cm->instance], 'name');

    if (!$mod) {
        return null;
    }

    return ucfirst($cm->modname) . ": {$mod->name}";
}

$courseid = isset($courseid) ? (int)$courseid : optional_param('courseid', 0, PARAM_INT);
$badgeid = isset($badgeid) ? (int)$badgeid : optional_param('badgeid', 0, PARAM_INT);
$forcerecalculate = isset($forcerecalculate) ? (bool)$forcerecalculate : optional_param('force', 0, PARAM_BOOL);
$debugtiming = isset($debugtiming) ? (bool)$debugtiming : optional_param('debug', 0, PARAM_BOOL);

if ($courseid <= 0 || $badgeid <= 0) {
    $response = ['activities' => [], 'metadata' => []];
    if (!empty($returndata)) {
        return $response;
    }
    echo json_encode($response);
    exit;
}

// Try to get from cache first (unless forced to recalculate)
$cachestart = microtime(true);
if (!$forcerecalculate) {
    $cached = $DB->get_record('local_ascend_rewards_badge_cache', [
        'userid' => $USER->id,
        'courseid' => $courseid,
        'badgeid' => $badgeid,
    ]);

    $perflog['cache_check'] = round((microtime(true) - $cachestart) * 1000, 2);

    if ($cached) {
        $decodestart = microtime(true);
        $activitiesdata = json_decode($cached->activities, true);
        $metadatadata = json_decode($cached->metadata, true);
        $perflog['decode'] = round((microtime(true) - $decodestart) * 1000, 2);
        $perflog['total'] = round((microtime(true) - $perfstart) * 1000, 2);

        // Return cached data
        $response = [
            'activities' => $activitiesdata,
            'metadata' => $metadatadata,
            'cached' => true,
        ];

        if ($debugtiming) {
            $response['performance'] = $perflog;
        }

        if (!empty($returndata)) {
            return $response;
        }
        echo json_encode($response);
        exit;
    }
} else {
    $perflog['cache_check'] = 0; // Skipped
}

// Always calculate qualifying activities based on badge award logic
$activities = [];
$metadata = []; // Store additional info like groups, pass/fail status, improvements

// Calculate actual qualifying activities based on badge type
{
    // Meta badges show contributing badges instead of activities
    $metabadges = [8, 12, 16, 20]; // Master Navigator, Time Tamer, Glory Guide, Learning Legend

if (in_array($badgeid, $metabadges)) {
    // Get contributing badges for meta badges
    $contributingbadgeids = [];
    switch ($badgeid) {
        case 8: // Master Navigator
            $contributingbadgeids = [6, 4, 5]; // Getting Started, On a Roll, Halfway Hero
            break;
        case 12: // Time Tamer
            $contributingbadgeids = [9, 11, 10]; // Early Bird, Sharp Shooter, Deadline Burner
            break;
        case 16: // Glory Guide
            $contributingbadgeids = [13, 15, 14]; // Feedback Follower, Steady Improver, Tenacious Tiger
            break;
        case 20: // Learning Legend
            $contributingbadgeids = [19, 17, 7]; // High Flyer, Activity Ace, Mission Complete
            break;
    }

    // Get names of earned contributing badges
    $badgenames = [
        4 => 'On a Roll', 5 => 'Halfway Hero', 6 => 'Getting Started', 7 => 'Mission Complete',
        9 => 'Early Bird', 10 => 'Deadline Burner', 11 => 'Sharp Shooter',
        13 => 'Feedback Follower', 14 => 'Tenacious Tiger', 15 => 'Steady Improver',
        17 => 'Activity Ace', 19 => 'High Flyer',
    ];

    foreach ($contributingbadgeids as $cbid) {
        $exists = $DB->record_exists('local_ascend_rewards_coins', [
            'userid' => $USER->id,
            'badgeid' => $cbid,
            'courseid' => $courseid,
        ]);
        if ($exists && isset($badgenames[$cbid])) {
            $activities[] = $badgenames[$cbid];
        }
    }
} else {
    // Regular badges - get qualifying activities based on badge award logic
    switch ($badgeid) {
        case 4: // On a Roll - 2 consecutive activities per award, show activity pairs grouped by award
            // Read pipe-delimited pair activities from preferences
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $prefjson = get_user_preferences($prefkey, '', $USER->id);

            if ($prefjson) {
                $allpairs = json_decode($prefjson, true);

                if (is_array($allpairs) && count($allpairs) > 0) {
                    $totalpairs = count($allpairs);
                    $paircount = 0;

                    // Parse each pipe-delimited pair
                    foreach ($allpairs as $pairkey) {
                        $pairparts = explode('|', $pairkey, 2);

                        if (count($pairparts) === 2) {
                            $paircount++;
                            $awardnumber = $paircount;

                            // Check if this is cmid-based (numeric) or name-based (text) format
                            $iscmidformat = is_numeric($pairparts[0]) && is_numeric($pairparts[1]);

                            if ($iscmidformat) {
                                // Convert cmids to activity names
                                $cmid1 = intval($pairparts[0]);
                                $cmid2 = intval($pairparts[1]);

                                $activitynames = array_filter([
                                    local_ascend_rewards_get_activity_name_by_cmid($cmid1),
                                    local_ascend_rewards_get_activity_name_by_cmid($cmid2),
                                ]);

                                if (count($activitynames) === 2) {
                                    foreach ($activitynames as $activityname) {
                                        $activities[] = $activityname;
                                        $metadata[] = [
                                            'award_number' => $awardnumber,
                                            'award_count' => $totalpairs,
                                            'is_pair_member' => true,
                                        ];
                                    }
                                }
                            } else {
                                // Old format: activity names are stored directly
                                foreach ($pairparts as $activityname) {
                                    $activities[] = $activityname;
                                    $metadata[] = [
                                        'award_number' => $awardnumber,
                                        'award_count' => $totalpairs,
                                        'is_pair_member' => true,
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
                            'single_activity' => true,
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
                            'award_count' => 1,
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            break;

        case 9: // Early Bird - each activity awards badge once, show with x1, x2, x3
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $rawpref = get_user_preferences($prefkey, '', $USER->id);
            if ($rawpref) {
                // Determine format: legacy numeric CSV of cmids OR JSON array of activity names
                $isnumericcsv = preg_match('/^\d+(,\d+)*$/', $rawpref);
                if ($isnumericcsv) {
                    // Legacy path (cm ids stored as comma-separated numbers)
                    $awardedarray = explode(',', $rawpref);
                    $totalawards = count($awardedarray);
                    [$insql, $params] = $DB->get_in_or_equal($awardedarray, SQL_PARAMS_NAMED);
                    $params['courseid'] = $courseid;
                    $sql = "SELECT cm.id, cm.instance, m.name as modname
                                  FROM {course_modules} cm
                                  JOIN {modules} m ON m.id = cm.module
                                 WHERE cm.id $insql
                                   AND cm.course = :courseid";
                    $cms = $DB->get_records_sql($sql, $params);
                    // Group by module type for batch instance fetch
                    $bymodule = [];
                    foreach ($cms as $cm) {
                        if (!isset($bymodule[$cm->modname])) {
                            $bymodule[$cm->modname] = [];
                        }
                        $bymodule[$cm->modname][] = $cm->instance;
                    }
                    $activitynames = [];
                    foreach ($bymodule as $modname => $instances) {
                        [$insql2, $params2] = $DB->get_in_or_equal($instances, SQL_PARAMS_NAMED);
                        $instancesdata = $DB->get_records_select($modname, "id $insql2", $params2, '', 'id,name');
                        foreach ($instancesdata as $inst) {
                            $activitynames[$inst->id] = $inst->name;
                        }
                    }
                    foreach ($awardedarray as $idx => $cmid) {
                        if (isset($cms[$cmid])) {
                            $cm = $cms[$cmid];
                            if (isset($activitynames[$cm->instance])) {
                                $activities[] = $activitynames[$cm->instance];
                                $metadata[] = [
                                    'award_number' => $idx + 1,
                                    'award_count' => $totalawards,
                                    'single_activity' => true,
                                ];
                            }
                        }
                    }
                } else {
                    // New path: JSON array of activity names stored in preference
                    $decoded = json_decode($rawpref, true);
                    if (is_array($decoded)) {
                        $totalawards = count($decoded);
                        foreach ($decoded as $idx => $name) {
                            $activities[] = $name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
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
                            'award_count' => 1,
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            break;

        case 11: // Sharp Shooter - preferences store JSON array of cmid pairs (e.g., ["2|3", "4|5"])
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $rawpref = get_user_preferences($prefkey, '', $USER->id);
            if ($rawpref) {
                $decoded = json_decode($rawpref, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $awardnum = 1;
                    $totalawards = 0;

                    // Count valid pairs (pipe-delimited cmid pairs)
                    foreach ($decoded as $entry) {
                        if (strpos($entry, '|') !== false && preg_match('/^\d+\|\d+$/', $entry)) {
                            $totalawards++;
                        }
                    }

                    // Process each pair entry
                    foreach ($decoded as $entry) {
                        if (strpos($entry, '|') !== false && preg_match('/^\d+\|\d+$/', $entry)) {
                            // This is a cmid-based pair (e.g., "2|3")
                            $cmids = explode('|', $entry);
                            foreach ($cmids as $cmid) {
                                $name = local_ascend_rewards_get_activity_name_by_cmid((int)$cmid);
                                if ($name) {
                                    $activities[] = $name;
                                    $metadata[] = [
                                        'award_number' => $awardnum,
                                        'award_count' => $totalawards,
                                    ];
                                }
                            }
                            $awardnum++;
                        } else if (!strpos($entry, '|') && !is_numeric($entry)) {
                            // Legacy format: activity name (e.g., "Assign: Test 1")
                            // Keep for backward compatibility with old data
                            $activities[] = $entry;
                            if (!isset($legacytotalawards)) {
                                $legacytotalawards = count(array_filter(
                                    $decoded,
                                    function ($e) {
                                        return !strpos($e, '|') && !is_numeric($e);
                                    }
                                ));
                            }
                            $metadata[] = [
                                'award_number' => isset($legacyawardnum) ? $legacyawardnum : 1,
                                'award_count' => isset($legacytotalawards) ? $legacytotalawards : 1,
                            ];
                            if (!isset($legacyawardnum)) {
                                $legacyawardnum = 1;
                            }
                            if (
                                $entry === end(array_filter(
                                    $decoded,
                                    function ($e) {
                                        return !strpos($e, '|') && !is_numeric($e);
                                    }
                                ))
                            ) {
                                $legacyawardnum++;
                            }
                        }
                    }
                }
            }
            break;

        case 13: // Feedback Follower - each improved activity awards badge, show with x1, x2, x3
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $awardedids = get_user_preferences($prefkey, '', $USER->id);

            if ($awardedids) {
                $decoded = json_decode($awardedids, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $allnumeric = true;
                    foreach ($decoded as $entry) {
                        if (!is_numeric($entry)) {
                            $allnumeric = false;
                            break;
                        }
                    }

                    if (!$allnumeric) {
                        $totalawards = count($decoded);
                        foreach ($decoded as $idx => $name) {
                            $activities[] = $name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                            ];
                        }
                        break;
                    }

                    $awardedarray = $decoded;
                } else {
                    $awardedarray = explode(',', $awardedids);
                }

                $totalawards = count($awardedarray);

                foreach ($awardedarray as $idx => $cmid) {
                    if ($cm = $DB->get_record('course_modules', ['id' => $cmid])) {
                        $mod = $DB->get_record('modules', ['id' => $cm->module]);
                        if ($mod && $modinstance = $DB->get_record($mod->name, ['id' => $cm->instance], 'name')) {
                            // Get grade info to show improvement
                            $gradeitem = $DB->get_record('grade_items', [
                                'itemtype' => 'mod',
                                'itemmodule' => $mod->name,
                                'iteminstance' => $cm->instance,
                                'courseid' => $courseid,
                            ]);

                            $oldgrade = 0;
                            $newgrade = 0;
                            if ($gradeitem) {
                                $grades = $DB->get_records(
                                    'grade_grades',
                                    ['itemid' => $gradeitem->id, 'userid' => $USER->id],
                                    'timemodified ASC'
                                );
                                if (count($grades) >= 2) {
                                    $gradesarray = array_values($grades);
                                    $first = $gradesarray[0];
                                    $last = end($gradesarray);
                                    $oldgrade = $first->finalgrade ? round(($first->finalgrade / $gradeitem->grademax) * 100) : 0;
                                    $newgrade = $last->finalgrade ? round(($last->finalgrade / $gradeitem->grademax) * 100) : 0;
                                }
                            }

                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                                'old_grade' => $oldgrade,
                                'new_grade' => $newgrade,
                            ];
                        }
                    }
                }
            }
            break;

        case 14: // Tenacious Tiger - 2+ improved activities (show only activities with improvements)
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $awardedids = get_user_preferences($prefkey, '', $USER->id);
            if ($awardedids) {
                $decoded = json_decode($awardedids, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $allnumeric = true;
                    foreach ($decoded as $entry) {
                        if (!is_numeric($entry)) {
                            $allnumeric = false;
                            break;
                        }
                    }

                    $totalawards = count($decoded);
                    if (!$allnumeric) {
                        foreach ($decoded as $idx => $name) {
                            $activities[] = $name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                            ];
                        }
                        break;
                    }

                    foreach ($decoded as $idx => $cmid) {
                        $activityname = local_ascend_rewards_get_activity_name_by_cmid((int)$cmid);
                        if ($activityname) {
                            $activities[] = $activityname;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                            ];
                        }
                    }
                    if (!empty($activities)) {
                        break;
                    }
                }
            }

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
                    $gradeitem = $DB->get_record('grade_items', [
                        'itemtype' => 'mod',
                        'itemmodule' => $cm->modname,
                        'iteminstance' => $cm->instance,
                        'courseid' => $courseid,
                    ]);

                    if ($gradeitem) {
                        $grades = $DB->get_records(
                            'grade_grades',
                            ['itemid' => $gradeitem->id, 'userid' => $USER->id],
                            'timemodified ASC'
                        );

                        // Only include if there are multiple attempts with improvement
                        if (count($grades) >= 2) {
                            $gradesarray = array_values($grades);
                            $first = $gradesarray[0];
                            $last = end($gradesarray);

                            if ($last->finalgrade > $first->finalgrade) {
                                if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                                    $oldpct = $first->finalgrade ? round(($first->finalgrade / $gradeitem->grademax) * 100) : 0;
                                    $newpct = $last->finalgrade ? round(($last->finalgrade / $gradeitem->grademax) * 100) : 0;

                                    $activities[] = $modinstance->name;
                                    $metadata[] = [
                                    'award_number' => 1,
                                    'award_count' => 1,
                                    'old_grade' => $oldpct,
                                    'new_grade' => $newpct,
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

        case 15: // Steady Improver - each failed->passed activity awards badge, show with x1, x2, x3
            $prefkey = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $awardedids = get_user_preferences($prefkey, '', $USER->id);

            if ($awardedids) {
                $decoded = json_decode($awardedids, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $allnumeric = true;
                    foreach ($decoded as $entry) {
                        if (!is_numeric($entry)) {
                            $allnumeric = false;
                            break;
                        }
                    }

                    if (!$allnumeric) {
                        $totalawards = count($decoded);
                        foreach ($decoded as $idx => $name) {
                            $activities[] = $name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                            ];
                        }
                        break;
                    }

                    $awardedarray = $decoded;
                } else {
                    $awardedarray = explode(',', $awardedids);
                }

                $totalawards = count($awardedarray);

                foreach ($awardedarray as $idx => $cmid) {
                    if ($cm = $DB->get_record('course_modules', ['id' => $cmid])) {
                        $mod = $DB->get_record('modules', ['id' => $cm->module]);
                        if ($mod && $modinstance = $DB->get_record($mod->name, ['id' => $cm->instance], 'name')) {
                            // Get grade progression
                            $gradeitem = $DB->get_record('grade_items', [
                                'itemtype' => 'mod',
                                'itemmodule' => $mod->name,
                                'iteminstance' => $cm->instance,
                                'courseid' => $courseid,
                            ]);

                            $failedgrade = 0;
                            $passedgrade = 0;
                            if ($gradeitem) {
                                $grades = $DB->get_records(
                                    'grade_grades',
                                    ['itemid' => $gradeitem->id, 'userid' => $USER->id],
                                    'timemodified ASC'
                                );
                                if (count($grades) >= 2) {
                                    $gradesarray = array_values($grades);
                                    $first = $gradesarray[0];
                                    $last = end($gradesarray);
                                    $failedgrade = $first->finalgrade ? round(($first->finalgrade / $gradeitem->grademax) * 100) : 0;
                                    $passedgrade = $last->finalgrade ? round(($last->finalgrade / $gradeitem->grademax) * 100) : 0;
                                }
                            }

                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => $idx + 1,
                                'award_count' => $totalawards,
                                'single_activity' => true,
                                'failed_grade' => $failedgrade,
                                'passed_grade' => $passedgrade,
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
                            'award_count' => 1,
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            break;

        case 19: // High Flyer - awards every 2 passed activities, show each pair with x1, x2, x3
            // Count how many times this badge has been awarded
            $awardcount = $DB->count_records('local_ascend_rewards_coins', [
                'userid' => $USER->id,
                'badgeid' => $badgeid,
                'courseid' => $courseid,
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
            $allcompleted = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);

            // Filter for passed activities (grade >= 60%)
            $passed = [];
            foreach ($allcompleted as $cm) {
                $gradeitem = $DB->get_record('grade_items', [
                    'itemtype' => 'mod',
                    'itemmodule' => $cm->modname,
                    'iteminstance' => $cm->instance,
                    'courseid' => $courseid,
                ]);

                if ($gradeitem) {
                    $grade = $DB->get_record('grade_grades', [
                    'itemid' => $gradeitem->id,
                    'userid' => $USER->id,
                    ]);

                    if ($grade && $grade->finalgrade !== null) {
                        $percentage = ($grade->finalgrade / $gradeitem->grademax) * 100;
                        if ($percentage >= 60) {
                            $passed[] = $cm;
                        }
                    }
                }
            }

            // Show all pairs up to award count
            $totalpairs = $awardcount > 0 ? $awardcount : 1;

            for ($pair = 0; $pair < $totalpairs; $pair++) {
                $startindex = $pair * 2;
                $pairactivities = array_slice($passed, $startindex, 2);

                foreach ($pairactivities as $cm) {
                    try {
                        if ($modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'name')) {
                            $activities[] = $modinstance->name;
                            $metadata[] = [
                                'award_number' => $pair + 1,
                                'award_count' => $totalpairs,
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
            $awardnum = 1;
            foreach ($completed as $cm) {
                $modtable = $cm->modname;
                try {
                    if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                        $activities[] = $modinstance->name;
                        $metadata[] = [
                            'award_number' => $awardnum,
                            'award_count' => count($completed),
                            'single_activity' => true,
                        ];
                        $awardnum++;
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
$cacherecord = $DB->get_record('local_ascend_rewards_badge_cache', [
    'userid' => $USER->id,
    'courseid' => $courseid,
    'badgeid' => $badgeid,
]);

if ($cacherecord) {
    // Update existing cache
    $cacherecord->activities = json_encode($activities);
    $cacherecord->metadata = json_encode($metadata);
    $cacherecord->timemodified = $now;
    $DB->update_record('local_ascend_rewards_badge_cache', $cacherecord);
} else {
    // Insert new cache
    $DB->insert_record('local_ascend_rewards_badge_cache', (object)[
        'userid' => $USER->id,
        'courseid' => $courseid,
        'badgeid' => $badgeid,
        'activities' => json_encode($activities),
        'metadata' => json_encode($metadata),
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

$perflog['calculation'] = round((microtime(true) - $cachestart) * 1000, 2);
$perflog['total'] = round((microtime(true) - $perfstart) * 1000, 2);

$response = [
    'activities' => $activities,
    'metadata' => $metadata,
    'cached' => false,
];

if ($debugtiming) {
    $response['performance'] = $perflog;
}

if (!empty($returndata)) {
    return $response;
}
echo json_encode($response);
