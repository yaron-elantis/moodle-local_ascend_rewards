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

defined('MOODLE_INTERNAL') || die();

namespace local_ascend_rewards\hook_callbacks;

/**
 * Hook callbacks for output.
 *
 * @package    local_ascend_rewards
 * @copyright  2026 Ascend Rewards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    
    /**
     * Hook callback to inject badge notification on all pages.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        $output = local_ascend_rewards_before_standard_top_of_body_html();
        $hook->add_html($output);
    }
}
