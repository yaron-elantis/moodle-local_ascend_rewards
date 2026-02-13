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

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to rebuild the badge activity cache nightly.
 *
 * This ensures cache data is up to date and validated.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rebuild_badge_cache extends \core\task\scheduled_task {
    /**
     * Return a cross-DB SQL expression for random ordering.
     *
     * @param \moodle_database $db
     * @return string
     */
    protected function get_random_order_sql(\moodle_database $db): string {
        switch ($db->get_dbfamily()) {
            case 'postgres':
                return 'RANDOM()';
            case 'mssql':
                return 'NEWID()';
            case 'oracle':
                return 'DBMS_RANDOM.VALUE';
            case 'mysql':
            default:
                return 'RAND()';
        }
    }

    /**
     * Get the task name shown in scheduled tasks UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_rebuild_badge_cache', 'local_ascend_rewards');
    }

    /**
     * Execute the badge cache rebuild workflow.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        mtrace('Starting badge cache rebuild...');

        // Rebuild missing cache entries (max 2000 per night).
        $helper = new \local_ascend_rewards\badge_cache_helper();
        $rebuilt = $helper->rebuild_all_cache(2000);

        mtrace("Rebuilt {$rebuilt} cache entries");

        // Verify existing cache entries (sample 500 random entries).
        $randomorder = $this->get_random_order_sql($DB);
        try {
            $cachedentries = $DB->get_recordset_sql(
                "SELECT *
                   FROM {local_ascend_rewards_badge_cache}
               ORDER BY {$randomorder}",
                [],
                0,
                500
            );
        } catch (\dml_exception $e) {
            mtrace('Random ordering unavailable, using fallback ordering for cache verification');
            $cachedentries = $DB->get_recordset_sql(
                "SELECT *
                   FROM {local_ascend_rewards_badge_cache}
               ORDER BY timemodified DESC",
                [],
                0,
                500
            );
        }

        $verified = 0;
        $corrected = 0;

        foreach ($cachedentries as $entry) {
            try {
                // Recalculate and compare.
                $fresh = $helper->calculate_activities($entry->userid, $entry->courseid, $entry->badgeid);

                $cachedactivities = json_decode($entry->activities, true);
                $cachedmetadata = json_decode($entry->metadata, true);

                // Simple comparison - if different, update.
                if (
                    json_encode($cachedactivities) !== json_encode($fresh['activities']) ||
                    json_encode($cachedmetadata) !== json_encode($fresh['metadata'])
                ) {
                    // Update cache with corrected data.
                    $entry->activities = json_encode($fresh['activities']);
                    $entry->metadata = json_encode($fresh['metadata']);
                    $entry->timemodified = time();
                    $DB->update_record('local_ascend_rewards_badge_cache', $entry);

                    $corrected++;
                }
            } catch (\Throwable $e) {
                mtrace('Skipped cache verification for one entry due to: ' . $e->getMessage());
            }

            $verified++;
        }
        $cachedentries->close();

        mtrace("Verified {$verified} cache entries, corrected {$corrected}");

        // Clean up old cache entries for deleted users/courses.
        $sql = "DELETE FROM {local_ascend_rewards_badge_cache}
                 WHERE userid NOT IN (SELECT id FROM {user} WHERE deleted = 0)
                    OR courseid NOT IN (SELECT id FROM {course})";
        $DB->execute($sql);

        mtrace('Badge cache rebuild complete');
    }
}
