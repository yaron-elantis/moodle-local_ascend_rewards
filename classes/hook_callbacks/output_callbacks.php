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

namespace local_ascend_rewards\hook_callbacks;
/**
 * Hook callbacks for output.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    /**
     * Hook callback to load plugin CSS in the page head.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {

        $cssurl = (new \moodle_url('/local/ascend_rewards/styles.css'))->out(false);
        $hook->add_html('<link rel="stylesheet" href="' . s($cssurl) . '">');
    }
    /**
     * Hook callback to inject badge notification on all pages.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {

        global $CFG;
        require_once($CFG->dirroot . '/local/ascend_rewards/lib.php');
        $output = \local_ascend_rewards_build_badge_notification_html();
        $hook->add_html($output);
        $hook->add_html(\html_writer::script("(function(){"
            . "var run=function(){"
            . "if (typeof require !== 'undefined') {"
            . "require(['local_ascend_rewards/notifications'], function(mod){ mod.init(); });"
            . "}"
            . "};"
            . "if (document.readyState === 'complete') { run(); }"
            . "else { window.addEventListener('load', run); }"
            . "})();"));
    }
}
