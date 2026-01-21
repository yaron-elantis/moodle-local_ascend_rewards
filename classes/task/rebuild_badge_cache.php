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

namespace local_ascend_rewards\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to rebuild badge activity cache at 3am
 * This ensures all cache is up-to-date and validates the data
 */
class rebuild_badge_cache extends \core\task\scheduled_task {
    
    /**
     * Get task name
     */
    public function get_name() {
        return get_string('task_rebuild_badge_cache', 'local_ascend_rewards');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        mtrace('Starting badge cache rebuild...');
        
        // Rebuild missing cache entries (max 2000 per night)
        $helper = new \local_ascend_rewards\badge_cache_helper();
        $rebuilt = $helper->rebuild_all_cache(2000);
        
        mtrace("Rebuilt {$rebuilt} cache entries");
        
        // Verify existing cache entries (sample 500 random entries)
        // Note: Using RAND() for randomization instead of sql_random()
        $cached_entries = $DB->get_records_sql(
            "SELECT * FROM {local_ascend_badge_cache}
          ORDER BY RAND()
             LIMIT 500"
        );
        
        $verified = 0;
        $corrected = 0;
        
        foreach ($cached_entries as $entry) {
            // Recalculate and compare
            $fresh = $helper->calculate_activities($entry->userid, $entry->courseid, $entry->badgeid);
            
            $cached_activities = json_decode($entry->activities, true);
            $cached_metadata = json_decode($entry->metadata, true);
            
            // Simple comparison - if different, update
            if (json_encode($cached_activities) !== json_encode($fresh['activities']) ||
                json_encode($cached_metadata) !== json_encode($fresh['metadata'])) {
                
                // Update cache with corrected data
                $entry->activities = json_encode($fresh['activities']);
                $entry->metadata = json_encode($fresh['metadata']);
                $entry->timemodified = time();
                $DB->update_record('local_ascend_badge_cache', $entry);
                
                $corrected++;
            }
            
            $verified++;
        }
        
        mtrace("Verified {$verified} cache entries, corrected {$corrected}");
        
        // Clean up old cache entries for deleted users/courses
        $sql = "DELETE FROM {local_ascend_badge_cache}
                 WHERE userid NOT IN (SELECT id FROM {user} WHERE deleted = 0)
                    OR courseid NOT IN (SELECT id FROM {course})";
        $DB->execute($sql);
        
        mtrace('Badge cache rebuild complete');
    }
}
