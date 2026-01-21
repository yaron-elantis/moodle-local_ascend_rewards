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

class local_ascend_rewards_navigation {

    /**
     * Add Apex Rewards to primary navigation.
     *
     * @param \core\event\base $event
     */
    public static function extend_primary_navigation(\core\event\base $event) {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if ($PAGE->pagetype == 'local-ascend_rewards-index') {
            return;
        }

        $navigation = $PAGE->navigation;
        if (!$navigation) {
            return;
        }

        $url = new moodle_url('/local/ascend_rewards/index.php');
        $icon = new pix_icon('t/award', get_string('nav_rewards', 'local_ascend_rewards'), 'core');

        $node = $navigation->add(
            get_string('nav_rewards', 'local_ascend_rewards'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'apexrewards',
            $icon
        );
        $node->showinflatnavigation = true;
    }
}