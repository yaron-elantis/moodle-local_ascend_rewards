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

class award_badges extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('awardbadges', 'local_ascend_rewards');
    }

    public function execute() {
        global $DB;

        $t0 = microtime(true);
        $reads0  = $DB->perf_get_reads();
        $writes0 = $DB->perf_get_writes();

        try {
            mtrace('local_ascend_rewards: starting award_badges task');
            $summary = \local_ascend_rewards\badge_awarder::run();

            if (is_object($summary)) {
                $awarded = (int)($summary->awarded ?? 0);
                $skipped = (int)($summary->skipped ?? 0);
                $errors  = (int)($summary->errors ?? 0);
                mtrace("local_ascend_rewards: awarded={$awarded}, skipped={$skipped}, errors={$errors}");
            } elseif (is_array($summary)) {
                mtrace('local_ascend_rewards: run() returned array length=' . count($summary));
            } else {
                mtrace('local_ascend_rewards: run() returned no summary');
            }

        } catch (\Throwable $e) {
            mtrace('local_ascend_rewards: ERROR in award_badges -> ' . $e->getMessage());
            throw $e;
        } finally {
            $elapsed = microtime(true) - $t0;
            $dbq = ($DB->perf_get_reads() + $DB->perf_get_writes()) - $reads0 - $writes0;
            mtrace(sprintf('local_ascend_rewards: task finished in %.3fs, dbqueries=%d', $elapsed, $dbq));
        }
    }
}