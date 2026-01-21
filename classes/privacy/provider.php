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

namespace local_ascend_rewards\privacy;

use core_privacy\local\metadata\collection;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\provider {

    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_ascend_rewards_coins', [
            'userid' => 'privacy:metadata:userid',
            'badgeid' => 'privacy:metadata:badgeid',
            'coins' => 'privacy:metadata:coins',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_badgerlog', [
            'userid' => 'privacy:metadata:userid',
            'badgeid' => 'privacy:metadata:badgeid',
            'status' => 'privacy:metadata:status',
            'message' => 'privacy:metadata:message',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_badgerlog');

        return $items;
    }
}
