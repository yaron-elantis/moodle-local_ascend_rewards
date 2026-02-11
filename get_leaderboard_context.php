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
 * Legacy AJAX endpoint for leaderboard context.
 *
 * This wrapper is kept for backward compatibility and delegates to
 * local_ascend_rewards\ajax_service. New code should use the external
 * service: local_ascend_rewards_get_leaderboard_context.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/ascend_rewards:view', $context);

header('Content-Type: application/json');

try {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $neighbors = optional_param('neighbors', 20, PARAM_INT);
    $result = \local_ascend_rewards\ajax_service::get_leaderboard_context($courseid, $neighbors);
    echo json_encode($result);
} catch (\Throwable $t) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'users' => [],
        'start_rank' => 0,
        'end_rank' => 0,
        'total_users' => 0,
        'myrank' => 0,
    ]);
}
