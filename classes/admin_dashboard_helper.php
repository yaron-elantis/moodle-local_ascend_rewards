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
 * Admin dashboard helper for building template context.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for admin dashboard template context.
 */
class admin_dashboard_helper {
    /**
     * Build context for the metrics tab.
     *
     * @param array $badge_names Badge ID to display name map.
     * @param \renderer_base $output Renderer for asset URLs.
     * @return array
     */
    public static function metrics_context(array $badge_names, \renderer_base $output): array {
        global $DB;

        $metrics_heading = get_string('admin_metrics_heading', 'local_ascend_rewards');
        $total_badges = $DB->count_records('local_ascend_rewards_coins');
        $users_with_badges = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.userid) " .
            "FROM {local_ascend_rewards_coins} c " .
            "JOIN {user} u ON u.id = c.userid " .
            "WHERE u.suspended = 0 AND u.deleted = 0"
        );
        $top_badge = $DB->get_records_sql(
            "SELECT c.badgeid, COUNT(*) as cnt " .
            "FROM {local_ascend_rewards_coins} c " .
            "JOIN {user} u ON u.id = c.userid " .
            "WHERE u.suspended = 0 AND u.deleted = 0 " .
            "GROUP BY c.badgeid " .
            "ORDER BY cnt DESC " .
            "LIMIT 1"
        );
        $top_badge_id = $top_badge ? reset($top_badge)->badgeid : null;
        $top_badge_count = $top_badge ? reset($top_badge)->cnt : 0;
        $badge_icons = [
            4 => '', 6 => '', 5 => '', 8 => '',
            9 => '', 11 => '', 10 => '', 12 => '',
            13 => '', 15 => '', 14 => '', 16 => '',
            19 => '', 17 => '', 7 => '', 20 => '',
        ];
        $top_badge_name = $top_badge_id && isset($badge_names[$top_badge_id])
            ? $badge_names[$top_badge_id]
            : ($top_badge_id ?: get_string('not_applicable_label', 'local_ascend_rewards'));
        $top_badge_icon = $top_badge_id && isset($badge_icons[$top_badge_id]) ? $badge_icons[$top_badge_id] : '';

        $badge_counts = $DB->get_records_sql(
            "SELECT badgeid, COUNT(*) as cnt " .
            "FROM {local_ascend_rewards_coins} " .
            "WHERE badgeid > 0 " .
            "GROUP BY badgeid " .
            "ORDER BY cnt DESC"
        );
        $badge_count_rows = [];
        foreach ($badge_counts as $row) {
            $bid = (int)$row->badgeid;
            $bname = isset($badge_names[$bid]) ? $badge_names[$bid] : get_string('badge_number_label', 'local_ascend_rewards', $bid);
            $bicon = isset($badge_icons[$bid]) ? $badge_icons[$bid] : '';
            $badge_count_rows[] = [
                'name' => $bname,
                'count' => $row->cnt,
                'icon_html' => $bicon,
            ];
        }

        $leaders = $DB->get_records_sql(
            "SELECT x.userid, x.xp " .
            "FROM {local_ascend_rewards_xp} x " .
            "JOIN {user} u ON u.id = x.userid " .
            "WHERE x.courseid = 0 AND x.xp > 0 AND u.suspended = 0 AND u.deleted = 0 " .
            "ORDER BY x.xp DESC " .
            "LIMIT 10"
        );
        $leader_rows = [];
        $rank = 1;
        foreach ($leaders as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid],
                'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,username', IGNORE_MISSING);
            $uname = $user
                ? get_string('name_id_display', 'local_ascend_rewards', (object)[
                    'name' => fullname($user),
                    'id' => $user->id,
                ])
                : get_string('user_number_label', 'local_ascend_rewards', $row->userid);
            $leader_rows[] = [
                'rank' => $rank,
                'name' => $uname,
                'xp' => $row->xp,
            ];
            $rank++;
        }

        $badge_img_map = [
            6 => $output->image_url('getting_started', 'local_ascend_rewards')->out(false),
            5 => $output->image_url('halfway_hero', 'local_ascend_rewards')->out(false),
            8 => $output->image_url('master_navigator', 'local_ascend_rewards')->out(false),
            13 => $output->image_url('feedback_follower', 'local_ascend_rewards')->out(false),
            15 => $output->image_url('steady_improver', 'local_ascend_rewards')->out(false),
            14 => $output->image_url('tenacious_tiger', 'local_ascend_rewards')->out(false),
            16 => $output->image_url('glory_guide', 'local_ascend_rewards')->out(false),
        ];
        $blank_img = '/local/ascend_rewards/pix/Blank_Badge_Image.png';
        $max_awards = 0;
        foreach ($badge_counts as $row) {
            if ($row->cnt > $max_awards) {
                $max_awards = $row->cnt;
            }
        }
        $badge_chart = [];
        foreach ($badge_counts as $row) {
            $bid = (int)$row->badgeid;
            $bname = isset($badge_names[$bid]) ? $badge_names[$bid] : get_string('badge_number_label', 'local_ascend_rewards', $bid);
            $img = isset($badge_img_map[$bid]) ? $badge_img_map[$bid] : $blank_img;
            $height = $max_awards ? (60 + 120 * ($row->cnt / $max_awards)) : 60;
            $badge_chart[] = [
                'img' => $img,
                'name' => $bname,
                'count' => $row->cnt,
                'height' => round($height),
            ];
        }

        $badge_options = [];
        foreach ($badge_names as $bid => $bname) {
            $badgeoption = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $bname,
                'id' => $bid,
            ]);
            $badge_options[] = [
                'id' => $bid,
                'label' => $badgeoption,
            ];
        }

        return [
            'metrics_heading' => $metrics_heading,
            'total_badges_label' => get_string('admin_total_badges_awarded', 'local_ascend_rewards'),
            'total_badges' => $total_badges,
            'users_with_badges_label' => get_string('admin_users_with_badges', 'local_ascend_rewards'),
            'users_with_badges' => $users_with_badges,
            'most_awarded_badge_label' => get_string('admin_most_awarded_badge', 'local_ascend_rewards'),
            'top_badge_name' => $top_badge_name,
            'top_badge_count_label' => get_string('admin_awards_count', 'local_ascend_rewards', $top_badge_count),
            'top_badge_icon' => $top_badge_icon,
            'badges_awarded_by_type_heading' => get_string('admin_badges_awarded_by_type', 'local_ascend_rewards'),
            'badge_label' => get_string('badge_label', 'local_ascend_rewards'),
            'awards_label' => get_string('awards_label', 'local_ascend_rewards'),
            'badge_counts' => $badge_count_rows,
            'leaderboard_heading' => get_string('admin_user_leaderboard_by_xp', 'local_ascend_rewards'),
            'rank_heading' => get_string('rank_heading', 'local_ascend_rewards'),
            'user_label' => get_string('user_label', 'local_ascend_rewards'),
            'total_xp_label' => get_string('total_xp_label', 'local_ascend_rewards'),
            'leader_rows' => $leader_rows,
            'badge_distribution_heading' => get_string('admin_badge_distribution', 'local_ascend_rewards'),
            'badge_chart' => $badge_chart,
            'blank_badge_img' => $blank_img,
            'test_badge_popup_heading' => get_string('admin_test_badge_popup', 'local_ascend_rewards'),
            'test_badge_popup_desc' => get_string('admin_test_badge_popup_desc', 'local_ascend_rewards'),
            'badge_options' => $badge_options,
            'show_modal_button' => get_string('admin_show_modal_button', 'local_ascend_rewards'),
            'sesskey' => sesskey(),
            'level_up_sound_url' => (new \moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false),
            'close_label' => s(get_string('close_label', 'local_ascend_rewards')),
            'fullscreen_label' => s(get_string('fullscreen_label', 'local_ascend_rewards')),
            'badge_name_placeholder' => s(get_string('admin_badge_name_placeholder', 'local_ascend_rewards')),
            'category_label' => s(get_string('category_label', 'local_ascend_rewards')),
            'badge_category_default' => s(get_string('badge_category_default', 'local_ascend_rewards')),
            'xp_default_display' => s(get_string('xp_default_display', 'local_ascend_rewards')),
            'experience_points_label' => s(get_string('experience_points_label', 'local_ascend_rewards')),
            'medal_url' => s($output->image_url('medal_gold', 'local_ascend_rewards')->out(false)),
            'assets_label' => s(get_string('assets_label', 'local_ascend_rewards')),
            'assets_default_display' => s(get_string('assets_default_display', 'local_ascend_rewards')),
            'assets_earned_label' => s(get_string('assets_earned_label', 'local_ascend_rewards')),
            'course_label' => s(get_string('course_label', 'local_ascend_rewards')),
            'achieved_on_label' => s(get_string('achieved_on_label', 'local_ascend_rewards')),
            'admin_badge_why_heading' => s(get_string('admin_badge_why_heading', 'local_ascend_rewards')),
            'qualifying_activities_label' => s(get_string('qualifying_activities_label', 'local_ascend_rewards')),
            'contributing_badges_label' => s(get_string('contributing_badges_label', 'local_ascend_rewards')),
        ];
    }

    /**
     * Build context for the users tab.
     *
     * @param array $badge_names Badge ID to display name map.
     * @param string $idpattern Regex to extract IDs from labels.
     * @return array
     */
    public static function users_context(array $badge_names, string $idpattern): array {
        global $DB;

        $search_type = optional_param('search_type', 'user', PARAM_ALPHA);
        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $badgeid_search = optional_param('badgeid_search', '', PARAM_TEXT);

        $userid = 0;
        if ($userid_str && preg_match($idpattern, $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }

        $badgeid = 0;
        if ($badgeid_search && preg_match($idpattern, $badgeid_search, $matches)) {
            $badgeid = (int)$matches[1];
        }

        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, " .
            "u.middlename, u.alternatename, u.username " .
            "FROM {user} u " .
            "JOIN {local_ascend_rewards_coins} c ON c.userid = u.id " .
            "WHERE u.suspended = 0 AND u.deleted = 0 " .
            "ORDER BY u.lastname, u.firstname"
        );
        $users_list = [];
        foreach ($users as $u) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => fullname($u),
                'id' => $u->id,
            ]);
            $users_list[] = ['label' => $label];
        }

        $badges_list = [];
        foreach ($badge_names as $bid => $bname) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $bname,
                'id' => $bid,
            ]);
            $badges_list[] = ['label' => $label];
        }

        $user_results = false;
        $user_badges_heading = '';
        $user_badges_rows = [];
        $user_no_badges = '';
        if ($search_type === 'user' && $userid) {
            $badges = $DB->get_records_sql(
                "SELECT * FROM {local_ascend_rewards_coins} WHERE userid = ? AND badgeid > 0 ORDER BY timecreated DESC",
                [$userid]
            );
            if ($badges) {
                $user = $DB->get_record('user', ['id' => $userid],
                    'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);
                $uname = $user ? fullname($user) : get_string('user_number_label', 'local_ascend_rewards', $userid);
                $user_badges_heading = get_string('admin_badges_for_user_heading', 'local_ascend_rewards', $uname);
                foreach ($badges as $badge) {
                    $bname = isset($badge_names[$badge->badgeid])
                        ? $badge_names[$badge->badgeid]
                        : get_string('badge_number_label', 'local_ascend_rewards', $badge->badgeid);
                    $course = $DB->get_record('course', ['id' => $badge->courseid], 'fullname', IGNORE_MISSING);
                    $cname = $course ? $course->fullname : get_string('unknown_course_label', 'local_ascend_rewards');
                    $user_badges_rows[] = [
                        'name' => $bname,
                        'course' => $cname,
                        'coins' => $badge->coins,
                        'date' => userdate($badge->timecreated),
                    ];
                }
                $user_results = true;
            } else {
                $user_no_badges = get_string('admin_no_badges_for_user', 'local_ascend_rewards');
            }
        }

        $badge_results = false;
        $badge_recipients_heading = '';
        $badge_recipients_rows = [];
        $badge_no_recipients = '';
        if ($search_type === 'badge' && $badgeid) {
            $badge_record = $DB->get_record('badge', ['id' => $badgeid], 'name', IGNORE_MISSING);
            $badge_name = $badge_record ? $badge_record->name
                : (isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : get_string('unknown_badge_label', 'local_ascend_rewards'));

            $badge_recipients = $DB->get_records_sql(
                "SELECT DISTINCT c.userid, c.coins, c.timecreated, c.courseid, u.firstname, u.lastname, u.email
                 FROM {local_ascend_rewards_coins} c
                 JOIN {user} u ON u.id = c.userid
                 WHERE c.badgeid = ? AND u.deleted = 0 AND u.suspended = 0
                 ORDER BY c.timecreated DESC",
                [$badgeid]
            );

            if ($badge_recipients) {
                $recipient_count = count($badge_recipients);
                $user_label = $recipient_count === 1
                    ? get_string('user_label_singular', 'local_ascend_rewards')
                    : get_string('user_label_plural', 'local_ascend_rewards');
                $badge_recipients_heading = get_string('admin_badge_recipients_heading', 'local_ascend_rewards', (object)[
                    'badge' => $badge_name,
                    'count' => $recipient_count,
                    'userlabel' => $user_label,
                ]);
                foreach ($badge_recipients as $recipient) {
                    $course = $DB->get_record('course', ['id' => $recipient->courseid], 'fullname', IGNORE_MISSING);
                    $course_name = $course ? $course->fullname : get_string('unknown_course_label', 'local_ascend_rewards');
                    $badge_recipients_rows[] = [
                        'name' => fullname($recipient),
                        'email' => $recipient->email,
                        'course' => $course_name,
                        'coins' => $recipient->coins,
                        'date' => userdate($recipient->timecreated),
                    ];
                }
                $badge_results = true;
            } else {
                $badge_no_recipients = get_string('admin_no_recipients_for_badge', 'local_ascend_rewards');
            }
        }

        return [
            'search_user' => ($search_type === 'user'),
            'search_badge' => ($search_type === 'badge'),
            'users_list' => $users_list,
            'badges_list' => $badges_list,
            'userid_str' => $userid_str,
            'badgeid_str' => $badgeid_search,
            'search_by_user_label' => get_string('admin_search_by_user', 'local_ascend_rewards'),
            'search_by_badge_label' => get_string('admin_search_by_badge', 'local_ascend_rewards'),
            'user_label' => get_string('user_label', 'local_ascend_rewards'),
            'badge_label' => get_string('badge_label', 'local_ascend_rewards'),
            'view_label' => get_string('view_label', 'local_ascend_rewards'),
            'user_search_placeholder' => get_string('admin_user_search_placeholder', 'local_ascend_rewards'),
            'badge_search_placeholder' => get_string('admin_badge_search_placeholder', 'local_ascend_rewards'),
            'user_results' => $user_results,
            'user_badges_heading' => $user_badges_heading,
            'user_badges_rows' => $user_badges_rows,
            'user_no_badges' => $user_no_badges,
            'badge_results' => $badge_results,
            'badge_recipients_heading' => $badge_recipients_heading,
            'badge_recipients_rows' => $badge_recipients_rows,
            'badge_no_recipients' => $badge_no_recipients,
            'badge_name_label' => get_string('badge_name_label', 'local_ascend_rewards'),
            'course_name_label' => get_string('course_name_label', 'local_ascend_rewards'),
            'coins_label' => get_string('coins_label', 'local_ascend_rewards'),
            'date_awarded_label' => get_string('date_awarded_label', 'local_ascend_rewards'),
            'email_label' => get_string('email_label', 'local_ascend_rewards'),
            'course_label' => get_string('course_label', 'local_ascend_rewards'),
        ];
    }

    /**
     * Build context for the award tab.
     *
     * @param array $badge_names Badge ID to display name map.
     * @return array
     */
    public static function award_context(array $badge_names): array {
        global $DB;

        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, " .
            "u.middlename, u.alternatename, u.username " .
            "FROM {user} u " .
            "JOIN {local_ascend_rewards_coins} c ON c.userid = u.id " .
            "WHERE u.suspended = 0 AND u.deleted = 0 " .
            "ORDER BY u.lastname, u.firstname"
        );
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id,fullname');
        $users_list = [];
        foreach ($users as $u) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => fullname($u),
                'id' => $u->id,
            ]);
            $users_list[] = ['label' => $label];
        }
        $courses_list = [];
        foreach ($courses as $c) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $c->fullname,
                'id' => $c->id,
            ]);
            $courses_list[] = ['label' => $label];
        }
        $badges_list = [];
        foreach ($badge_names as $bid => $bname) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $bname,
                'id' => $bid,
            ]);
            $badges_list[] = ['label' => $label];
        }

        return [
            'award_heading' => get_string('admin_award_revoke_heading', 'local_ascend_rewards'),
            'sesskey' => sesskey(),
            'users_list' => $users_list,
            'courses_list' => $courses_list,
            'badges_list' => $badges_list,
            'user_label' => get_string('user_label', 'local_ascend_rewards'),
            'course_label' => get_string('course_label', 'local_ascend_rewards'),
            'badge_label' => get_string('badge_label', 'local_ascend_rewards'),
            'user_search_placeholder' => get_string('admin_user_search_placeholder', 'local_ascend_rewards'),
            'course_search_placeholder' => get_string('admin_course_search_placeholder', 'local_ascend_rewards'),
            'badge_search_placeholder' => get_string('admin_badge_search_placeholder', 'local_ascend_rewards'),
            'award_button_label' => get_string('award_button_label', 'local_ascend_rewards'),
            'revoke_button_label' => get_string('revoke_button_label', 'local_ascend_rewards'),
        ];
    }

    /**
     * Build context for the gifts tab.
     *
     * @return array
     */
    public static function gifts_context(): array {
        return [
            'gift_heading' => get_string('admin_gift_rewards_heading', 'local_ascend_rewards'),
            'pro_message' => get_string('pro_version_only_message', 'local_ascend_rewards'),
        ];
    }

    /**
     * Build context for the cleanup tab.
     *
     * @param bool $ispost Whether the request is a POST.
     * @param string $action Requested action.
     * @return array
     */
    public static function cleanup_context(bool $ispost, string $action): array {
        global $DB;

        $cleanup_notice = '';
        $cleanup_notice_class = '';
        if ($ispost && $action !== '') {
            if ($action === 'cleanup_orphaned') {
                $orphaned = $DB->get_records_sql("
                    SELECT c.id
                    FROM {local_ascend_rewards_coins} c
                    LEFT JOIN {course} crs ON crs.id = c.courseid
                    WHERE c.courseid > 0 AND crs.id IS NULL
                ");
                $count = count($orphaned);
                if ($count > 0) {
                    foreach ($orphaned as $rec) {
                        $DB->delete_records('local_ascend_rewards_coins', ['id' => $rec->id]);
                    }
                    $cleanup_notice = get_string('admin_cleanup_orphaned_success', 'local_ascend_rewards', $count);
                } else {
                    $cleanup_notice = get_string('admin_cleanup_orphaned_none', 'local_ascend_rewards');
                }
                $cleanup_notice_class = 'alert alert-success';
            } else if ($action === 'cleanup_deleted_users') {
                $deleted_users = $DB->get_records_sql("
                    SELECT c.id
                    FROM {local_ascend_rewards_coins} c
                    LEFT JOIN {user} u ON u.id = c.userid
                    WHERE u.id IS NULL OR u.deleted = 1
                ");
                $count = count($deleted_users);
                if ($count > 0) {
                    foreach ($deleted_users as $rec) {
                        $DB->delete_records('local_ascend_rewards_coins', ['id' => $rec->id]);
                    }
                    $cleanup_notice = get_string('admin_cleanup_deleted_users_success', 'local_ascend_rewards', $count);
                } else {
                    $cleanup_notice = get_string('admin_cleanup_deleted_users_none', 'local_ascend_rewards');
                }
                $cleanup_notice_class = 'alert alert-success';
            }
        }

        return [
            'cleanup_heading' => get_string('admin_cleanup_heading', 'local_ascend_rewards'),
            'sesskey' => sesskey(),
            'cleanup_notice' => $cleanup_notice ?: null,
            'cleanup_notice_class' => $cleanup_notice_class,
            'cleanup_orphaned_label' => get_string('admin_cleanup_orphaned_label', 'local_ascend_rewards'),
            'cleanup_orphaned_desc' => get_string('admin_cleanup_orphaned_desc', 'local_ascend_rewards'),
            'cleanup_deleted_users_label' => get_string('admin_cleanup_deleted_users_label', 'local_ascend_rewards'),
            'cleanup_deleted_users_desc' => get_string('admin_cleanup_deleted_users_desc', 'local_ascend_rewards'),
            'note_label' => get_string('note_label', 'local_ascend_rewards'),
            'cleanup_note' => get_string('admin_cleanup_note', 'local_ascend_rewards'),
            'cleanup_orphaned_button' => get_string('admin_cleanup_orphaned_button', 'local_ascend_rewards'),
            'cleanup_deleted_users_button' => get_string('admin_cleanup_deleted_users_button', 'local_ascend_rewards'),
        ];
    }

    /**
     * Build context for the debug tab.
     *
     * @param array $badge_names Badge ID to display name map.
     * @param string $idpattern Regex to extract IDs from labels.
     * @return array
     */
    public static function debug_context(array $badge_names, string $idpattern): array {
        global $DB;

        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $courseid_str = optional_param('courseid', '', PARAM_TEXT);
        $badgeid_str = optional_param('badgeid', '', PARAM_TEXT);

        $userid = 0;
        if ($userid_str && preg_match($idpattern, $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }
        $courseid = 0;
        if ($courseid_str && preg_match($idpattern, $courseid_str, $matches)) {
            $courseid = (int)$matches[1];
        }
        $badgeid = 0;
        if ($badgeid_str && preg_match($idpattern, $badgeid_str, $matches)) {
            $badgeid = (int)$matches[1];
        }

        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, " .
            "u.middlename, u.alternatename, u.username " .
            "FROM {user} u " .
            "JOIN {local_ascend_rewards_coins} c ON c.userid = u.id " .
            "WHERE u.suspended = 0 AND u.deleted = 0 " .
            "ORDER BY u.lastname, u.firstname"
        );
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id,fullname');
        $users_list = [];
        foreach ($users as $u) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => fullname($u),
                'id' => $u->id,
            ]);
            $users_list[] = ['label' => $label];
        }
        $courses_list = [];
        foreach ($courses as $c) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $c->fullname,
                'id' => $c->id,
            ]);
            $courses_list[] = ['label' => $label];
        }
        $badges_list = [];
        foreach ($badge_names as $bid => $bname) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $bname,
                'id' => $bid,
            ]);
            $badges_list[] = ['label' => $label];
        }

        $debug_selected = ($userid && $courseid_str !== '' && $badgeid);

        $context = [
            'debug_heading' => get_string('admin_debug_heading', 'local_ascend_rewards'),
            'userid_str' => $userid_str,
            'courseid_str' => $courseid_str,
            'badgeid_str' => $badgeid_str,
            'users_list' => $users_list,
            'courses_list' => $courses_list,
            'badges_list' => $badges_list,
            'user_label' => get_string('user_label', 'local_ascend_rewards'),
            'course_label' => get_string('course_label', 'local_ascend_rewards'),
            'badge_label' => get_string('badge_label', 'local_ascend_rewards'),
            'user_search_placeholder' => get_string('admin_user_search_placeholder', 'local_ascend_rewards'),
            'course_search_placeholder' => get_string('admin_course_search_placeholder', 'local_ascend_rewards'),
            'badge_search_placeholder' => get_string('admin_badge_search_placeholder', 'local_ascend_rewards'),
            'debug_button_label' => get_string('debug_button_label', 'local_ascend_rewards'),
            'global_course_option' => get_string('admin_global_course_option', 'local_ascend_rewards', 0),
            'debug_selected' => $debug_selected,
            'debug_invalid_badge' => null,
            'debug_badge_heading' => '',
            'user_display' => '',
            'course_display' => '',
            'award_present' => false,
            'award_times_heading' => '',
            'award_rows' => [],
            'award_none_heading' => '',
            'award_none_desc' => '',
            'debug_step1_heading' => '',
            'debug_found_activities_text' => '',
            'has_completions' => false,
            'completion_headers' => [],
            'completion_rows' => [],
            'show_course_column' => false,
            'debug_step2_heading' => '',
            'requirement_label' => get_string('admin_requirement_label', 'local_ascend_rewards'),
            'requirement_text' => '',
            'detection_method_label' => get_string('admin_detection_method_label', 'local_ascend_rewards'),
            'detection_method' => '',
            'qualification_reason' => '',
            'qualifies' => false,
            'qualification_pass_heading' => '',
            'qualification_pass_desc' => '',
            'qualification_fail_heading' => '',
            'qualification_fail_desc' => '',
            'award_this_badge_button' => get_string('admin_award_this_badge_button', 'local_ascend_rewards'),
            'debug_select_prompt' => get_string('admin_debug_select_prompt', 'local_ascend_rewards'),
            'sesskey' => sesskey(),
        ];

        if ($debug_selected) {
            $badge_info = self::debug_badge_info();
            $info = $badge_info[$badgeid] ?? null;
            if (!$info) {
                $context['debug_invalid_badge'] = get_string('admin_invalid_badge_id', 'local_ascend_rewards');
            } else {
                $user = $DB->get_record('user', ['id' => $userid],
                    'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);

                if ($courseid == 0) {
                    $course_display = get_string('admin_all_courses_global', 'local_ascend_rewards');
                } else {
                    $course = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);
                    $course_display = get_string('name_id_display', 'local_ascend_rewards', (object)[
                        'name' => $course->fullname,
                        'id' => $courseid,
                    ]);
                }

                $context['debug_badge_heading'] = get_string('admin_badge_deep_dive_heading', 'local_ascend_rewards', $info['name']);
                $context['user_display'] = get_string('name_id_display', 'local_ascend_rewards', (object)[
                    'name' => fullname($user),
                    'id' => $userid,
                ]);
                $context['course_display'] = $course_display;

                if ($courseid == 0) {
                    $all_awards = $DB->get_records('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                    ], 'timecreated ASC');
                } else {
                    $all_awards = $DB->get_records('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'courseid' => $courseid,
                    ], 'timecreated ASC');
                }

                $award_count = count($all_awards);
                if ($award_count > 0) {
                    $context['award_present'] = true;
                    $context['award_times_heading'] = get_string('admin_badge_awarded_times', 'local_ascend_rewards', $award_count);
                    $award_rows = [];
                    foreach ($all_awards as $idx => $award) {
                        $award_num = $idx + 1;
                        $award_label = get_string('award_number_label_prefix', 'local_ascend_rewards')
                            . $award_num
                            . get_string('award_number_label_suffix', 'local_ascend_rewards');
                        $award_details = get_string('admin_award_coins_on', 'local_ascend_rewards', (object)[
                            'coins' => $award->coins,
                            'date' => userdate($award->timecreated),
                        ]);
                        $award_rows[] = [
                            'label' => $award_label,
                            'details' => $award_details,
                        ];
                    }
                    $context['award_rows'] = $award_rows;
                } else {
                    $context['award_none_heading'] = get_string('admin_badge_not_awarded_heading', 'local_ascend_rewards');
                    $context['award_none_desc'] = get_string('admin_badge_not_awarded_desc', 'local_ascend_rewards');
                }

                if ($courseid == 0) {
                    $completions = $DB->get_records_sql("
                        SELECT cm.id as cmid, cm.course, cm.module, cm.instance, m.name as modname, cmc.completionstate, cmc.timemodified,
                               cm.completionexpected, gi.grademax, gi.gradepass, gg.finalgrade,
                               gg.timemodified as grade_time, c.fullname as coursename
                        FROM {course_modules} cm
                        INNER JOIN {modules} m ON m.id = cm.module
                        INNER JOIN {course} c ON c.id = cm.course
                        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                        LEFT JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name AND gi.courseid = cm.course AND gi.itemtype = 'mod'
                        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid2
                        WHERE cm.course > 1
                          AND cm.deletioninprogress = 0
                          AND cm.completion > 0
                        ORDER BY cm.course, cm.id ASC
                    ", ['userid' => $userid, 'userid2' => $userid]);
                } else {
                    $completions = $DB->get_records_sql("
                        SELECT cm.id as cmid, cm.module, cm.instance, m.name as modname, cmc.completionstate, cmc.timemodified,
                               cm.completionexpected, gi.grademax, gi.gradepass, gg.finalgrade,
                               gg.timemodified as grade_time
                        FROM {course_modules} cm
                        INNER JOIN {modules} m ON m.id = cm.module
                        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                        LEFT JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name AND gi.courseid = cm.course AND gi.itemtype = 'mod'
                        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid2
                        WHERE cm.course = :courseid
                          AND cm.deletioninprogress = 0
                          AND cm.completion > 0
                        ORDER BY cm.id ASC
                    ", ['userid' => $userid, 'userid2' => $userid, 'courseid' => $courseid]);
                }

                $completed_count = 0;
                $total_count = count($completions);
                $consecutive_completions = 0;
                $max_consecutive = 0;
                $before_deadline_count = 0;
                $consecutive_before_deadline = 0;
                $max_consecutive_deadline = 0;
                $early_bird_count = 0;
                $passed_count = 0;
                $consecutive_passes = 0;
                $max_consecutive_passes = 0;

                $context['debug_step1_heading'] = get_string('admin_debug_step1_heading', 'local_ascend_rewards');
                if ($courseid == 0) {
                    $context['debug_found_activities_text'] = get_string('admin_debug_found_activities_all', 'local_ascend_rewards', $total_count);
                } else {
                    $context['debug_found_activities_text'] = get_string('admin_debug_found_activities_course', 'local_ascend_rewards', $total_count);
                }

                if ($completions) {
                    $context['show_course_column'] = ($courseid == 0);
                    $completion_headers = [];
                    if ($context['show_course_column']) {
                        $completion_headers[] = get_string('course_label', 'local_ascend_rewards');
                    }
                    $completion_headers[] = get_string('activity_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('type_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('completed_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('grade_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('pass_grade_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('passed_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('due_date_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('before_deadline_label', 'local_ascend_rewards');
                    $completion_headers[] = get_string('early_label', 'local_ascend_rewards');
                    $context['completion_headers'] = $completion_headers;

                    $completion_rows = [];
                    foreach ($completions as $comp) {
                        $is_completed = $comp->completionstate > 0;
                        $completed_date = $is_completed ? userdate($comp->timemodified) : '';

                        $grade = isset($comp->finalgrade) ? round($comp->finalgrade, 1) : '-';
                        $grademax = isset($comp->grademax) ? round($comp->grademax, 1) : '-';
                        $pass_grade = isset($comp->gradepass) ? round($comp->gradepass, 1) : '-';

                        $is_passed = false;
                        if (isset($comp->finalgrade) && isset($comp->gradepass) && $comp->gradepass > 0) {
                            $is_passed = $comp->finalgrade >= $comp->gradepass;
                        } else if (isset($comp->finalgrade) && isset($comp->grademax) && $comp->grademax > 0) {
                            $is_passed = $comp->finalgrade >= ($comp->grademax * 0.5);
                        }

                        $actual_deadline = 0;
                        if ($comp->modname == 'assign') {
                            $assign = $DB->get_record('assign', ['id' => $comp->instance], 'duedate');
                            if ($assign && $assign->duedate > 0) {
                                $actual_deadline = $assign->duedate;
                            }
                        } else if ($comp->modname == 'quiz') {
                            $quiz = $DB->get_record('quiz', ['id' => $comp->instance], 'timeclose');
                            if ($quiz && $quiz->timeclose > 0) {
                                $actual_deadline = $quiz->timeclose;
                            }
                        }

                        $due_date = $actual_deadline > 0 ? userdate($actual_deadline) : get_string('no_deadline_label', 'local_ascend_rewards');
                        $before_deadline = false;
                        $is_early = false;

                        if ($is_completed && $actual_deadline > 0) {
                            $before_deadline = $comp->timemodified < $actual_deadline;
                            $hours_early = ($actual_deadline - $comp->timemodified) / 3600;
                            $is_early = $hours_early >= 48;
                            if ($is_early) {
                                $early_bird_count++;
                            }
                        }

                        if ($is_completed) {
                            $completed_count++;
                            $consecutive_completions++;
                            $max_consecutive = max($max_consecutive, $consecutive_completions);
                        } else {
                            $consecutive_completions = 0;
                        }

                        if ($actual_deadline > 0) {
                            if ($before_deadline) {
                                $before_deadline_count++;
                                $consecutive_before_deadline++;
                                $max_consecutive_deadline = max($max_consecutive_deadline, $consecutive_before_deadline);
                            } else {
                                $consecutive_before_deadline = 0;
                            }
                        }

                        if ($is_passed) {
                            $passed_count++;
                            $consecutive_passes++;
                            $max_consecutive_passes = max($max_consecutive_passes, $consecutive_passes);
                        } else {
                            $consecutive_passes = 0;
                        }

                        $before_deadline_display = $before_deadline
                            ? get_string('yes_label', 'local_ascend_rewards')
                            : ($actual_deadline > 0 ? get_string('no_label', 'local_ascend_rewards') : get_string('dash_label', 'local_ascend_rewards'));
                        $early_display = $is_early
                            ? get_string('yes_label', 'local_ascend_rewards')
                            : get_string('dash_label', 'local_ascend_rewards');
                        $passed_display = $is_passed ? get_string('pass_label', 'local_ascend_rewards') : '';

                        $completion_rows[] = [
                            'course' => ($courseid == 0) ? $comp->coursename : '',
                            'activity' => $comp->modname,
                            'type' => strtoupper($comp->modname),
                            'completed' => $completed_date,
                            'grade' => $grade . ' / ' . $grademax,
                            'pass_grade' => $pass_grade,
                            'passed' => $passed_display,
                            'due_date' => $due_date,
                            'before_deadline' => $before_deadline_display,
                            'early' => $early_display,
                        ];
                    }

                    $context['completion_rows'] = $completion_rows;
                    $context['has_completions'] = true;
                }

                $context['debug_step2_heading'] = get_string('admin_debug_step2_heading', 'local_ascend_rewards', $info['name']);
                $context['requirement_text'] = $info['desc'];
                $context['detection_method'] = $info['method'] . '()';

                $qualifies = false;
                $qualification_reason = '';

                switch ($badgeid) {
                    case 4:
                        $qualifies = $max_consecutive >= 2;
                        $qualification_reason = get_string('admin_debug_reason_max_consecutive', 'local_ascend_rewards', (object)[
                            'count' => $max_consecutive,
                            'required' => 2,
                        ]);
                        break;
                    case 6:
                        $qualifies = $completed_count >= 1;
                        $qualification_reason = get_string('admin_debug_reason_completed_activities', 'local_ascend_rewards', (object)[
                            'count' => $completed_count,
                            'required' => 1,
                        ]);
                        break;
                    case 5:
                        $progress = $total_count > 0 ? ($completed_count / $total_count) * 100 : 0;
                        $qualifies = $progress >= 50;
                        $qualification_reason = get_string('admin_debug_reason_completion_percent', 'local_ascend_rewards', (object)[
                            'percent' => round($progress, 1),
                            'required' => 50,
                        ]);
                        break;
                    case 9:
                        $qualifies = $early_bird_count >= 1;
                        $qualification_reason = get_string('admin_debug_reason_early_completions', 'local_ascend_rewards', (object)[
                            'count' => $early_bird_count,
                            'required' => 1,
                        ]);
                        break;
                    case 11:
                        $qualifies = $max_consecutive_deadline >= 2;
                        $qualification_reason = get_string('admin_debug_reason_max_consecutive_deadline', 'local_ascend_rewards', (object)[
                            'count' => $max_consecutive_deadline,
                            'required' => 2,
                        ]);
                        break;
                    case 10:
                        $activities_with_deadlines = 0;
                        foreach ($completions as $comp) {
                            if ($comp->completionstate > 0) {
                                $has_deadline = false;
                                if ($comp->modname == 'assign') {
                                    $assign = $DB->get_record('assign', ['id' => $comp->instance], 'duedate');
                                    $has_deadline = ($assign && $assign->duedate > 0);
                                } else if ($comp->modname == 'quiz') {
                                    $quiz = $DB->get_record('quiz', ['id' => $comp->instance], 'timeclose');
                                    $has_deadline = ($quiz && $quiz->timeclose > 0);
                                }
                                if ($has_deadline) {
                                    $activities_with_deadlines++;
                                }
                            }
                        }
                        $all_before = ($activities_with_deadlines > 0) && ($before_deadline_count == $activities_with_deadlines);
                        $qualifies = $all_before;
                        $qualification_reason = get_string('admin_debug_reason_before_deadline', 'local_ascend_rewards', (object)[
                            'count' => $before_deadline_count,
                            'total' => $activities_with_deadlines,
                        ]);
                        break;
                    case 19:
                        $qualifies = $max_consecutive_passes >= 2;
                        $qualification_reason = get_string('admin_debug_reason_max_consecutive_passes', 'local_ascend_rewards', (object)[
                            'count' => $max_consecutive_passes,
                            'required' => 2,
                        ]);
                        break;
                    case 17:
                        $all_passed_first = ($completed_count > 0) && ($passed_count == $completed_count);
                        $qualifies = $all_passed_first;
                        $qualification_reason = get_string('admin_debug_reason_passed_first_attempt', 'local_ascend_rewards', (object)[
                            'passed' => $passed_count,
                            'total' => $completed_count,
                        ]);
                        break;
                    case 7:
                        $all_completed = ($completed_count == $total_count);
                        $qualifies = $all_completed;
                        $qualification_reason = get_string('admin_debug_reason_completed_all', 'local_ascend_rewards', (object)[
                            'completed' => $completed_count,
                            'total' => $total_count,
                        ]);
                        break;
                    case 8:
                        $base_badges = [6, 4, 5];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $qualification_reason = get_string('admin_debug_reason_progress_badges', 'local_ascend_rewards', (object)[
                            'earned' => $earned,
                            'total' => 3,
                            'required' => 2,
                            'list' => implode(', ', $earned_names),
                        ]);
                        break;
                    case 12:
                        $base_badges = [9, 11, 10];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $qualification_reason = get_string('admin_debug_reason_timeliness_badges', 'local_ascend_rewards', (object)[
                            'earned' => $earned,
                            'total' => 3,
                            'required' => 2,
                            'list' => implode(', ', $earned_names),
                        ]);
                        break;
                    case 16:
                        $base_badges = [13, 15, 14];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $qualification_reason = get_string('admin_debug_reason_quality_badges', 'local_ascend_rewards', (object)[
                            'earned' => $earned,
                            'total' => 3,
                            'required' => 2,
                            'list' => implode(', ', $earned_names),
                        ]);
                        break;
                    case 20:
                        $base_badges = [19, 17, 7];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $qualification_reason = get_string('admin_debug_reason_mastery_badges', 'local_ascend_rewards', (object)[
                            'earned' => $earned,
                            'total' => 3,
                            'required' => 2,
                            'list' => implode(', ', $earned_names),
                        ]);
                        break;
                }

                $context['qualification_reason'] = $qualification_reason;
                $context['qualifies'] = $qualifies;

                if ($qualifies) {
                    $context['qualification_pass_heading'] = get_string('admin_qualification_pass', 'local_ascend_rewards');
                    $context['qualification_pass_desc'] = get_string('admin_qualification_pass_desc', 'local_ascend_rewards', $info['name']);
                } else {
                    $context['qualification_fail_heading'] = get_string('admin_qualification_fail', 'local_ascend_rewards');
                    $context['qualification_fail_desc'] = get_string('admin_qualification_fail_desc', 'local_ascend_rewards', $info['name']);
                }
            }
        }

        return $context;
    }

    /**
     * Build context for the audit tab.
     *
     * @param array $badge_names Badge ID to display name map.
     * @param string $idpattern Regex to extract IDs from labels.
     * @return array
     */
    public static function audit_context(array $badge_names, string $idpattern): array {
        global $DB;

        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $badgeid_str = optional_param('badgeid', '', PARAM_TEXT);

        $userid = 0;
        if ($userid_str && preg_match($idpattern, $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }
        $badgeid = 0;
        if ($badgeid_str && preg_match($idpattern, $badgeid_str, $matches)) {
            $badgeid = (int)$matches[1];
        }

        $users = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic,
                            u.lastnamephonetic, u.middlename, u.alternatename, u.username
              FROM {user} u
              JOIN {local_ascend_rewards_badgerlog} l ON l.userid = u.id
             WHERE u.suspended = 0
               AND u.deleted = 0
               AND l.badgeid > 0
          ORDER BY u.lastname, u.firstname
        ");
        $users_list = [];
        foreach ($users as $u) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => fullname($u),
                'id' => $u->id,
            ]);
            $users_list[] = ['label' => $label];
        }

        $badges_list = [];
        foreach ($badge_names as $bid => $bname) {
            $label = get_string('name_id_display', 'local_ascend_rewards', (object)[
                'name' => $bname,
                'id' => $bid,
            ]);
            $badges_list[] = ['label' => $label];
        }

        $params = [];
        $where = ['badgeid > 0'];
        if ($userid) {
            $where[] = 'userid = :userid';
            $params['userid'] = $userid;
        }
        if ($badgeid) {
            $where[] = 'badgeid = :badgeid';
            $params['badgeid'] = $badgeid;
        }
        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM {local_ascend_rewards_badgerlog}" . ($where_clause ? " WHERE $where_clause" : "") .
            " ORDER BY timecreated DESC LIMIT 100";
        $records = $DB->get_records_sql($sql, $params);

        $audit_rows = [];
        foreach ($records as $rec) {
            $user = $DB->get_record('user', ['id' => $rec->userid],
                'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);
            $uname = $user ? fullname($user) : get_string('unknown_user_label', 'local_ascend_rewards');
            $bname = isset($badge_names[$rec->badgeid]) ? $badge_names[$rec->badgeid] : get_string('unknown_badge_label', 'local_ascend_rewards');
            $audit_rows[] = [
                'id' => $rec->id,
                'userid' => $rec->userid,
                'username' => $uname,
                'badgeid' => $rec->badgeid,
                'badgename' => $bname,
                'status' => $rec->status,
                'message' => $rec->message,
                'timecreated' => userdate($rec->timecreated),
            ];
        }

        $audit_headers = [
            get_string('record_id_label', 'local_ascend_rewards'),
            get_string('user_id_label', 'local_ascend_rewards'),
            get_string('user_name_label', 'local_ascend_rewards'),
            get_string('badge_id_label', 'local_ascend_rewards'),
            get_string('badge_name_label', 'local_ascend_rewards'),
            get_string('status_label', 'local_ascend_rewards'),
            get_string('message_label', 'local_ascend_rewards'),
            get_string('time_created_label', 'local_ascend_rewards'),
        ];

        return [
            'audit_heading' => get_string('audit_trail', 'local_ascend_rewards'),
            'userid_str' => $userid_str,
            'badgeid_str' => $badgeid_str,
            'users_list' => $users_list,
            'badges_list' => $badges_list,
            'user_label' => get_string('user_label', 'local_ascend_rewards'),
            'badge_label' => get_string('badge_label', 'local_ascend_rewards'),
            'search_label' => get_string('search_label', 'local_ascend_rewards'),
            'user_search_placeholder' => get_string('admin_user_search_placeholder', 'local_ascend_rewards'),
            'badge_search_placeholder' => get_string('admin_badge_search_placeholder', 'local_ascend_rewards'),
            'audit_records' => !empty($audit_rows),
            'audit_records_heading' => get_string('admin_audit_records_heading', 'local_ascend_rewards'),
            'audit_headers' => $audit_headers,
            'audit_rows' => $audit_rows,
            'no_audit_records_label' => get_string('no_audit_records_label', 'local_ascend_rewards'),
        ];
    }

    /**
     * Get debug badge definitions.
     *
     * @return array
     */
    private static function debug_badge_info(): array {
        return [
            4 => [
                'name' => get_string('badge_name_on_a_roll', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_on_a_roll', 'local_ascend_rewards'),
                'method' => 'on_a_roll',
            ],
            6 => [
                'name' => get_string('badge_name_getting_started', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_getting_started', 'local_ascend_rewards'),
                'method' => 'getting_started',
            ],
            5 => [
                'name' => get_string('badge_name_halfway_hero', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_halfway_hero', 'local_ascend_rewards'),
                'method' => 'halfway_hero',
            ],
            8 => [
                'name' => get_string('badge_name_master_navigator', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_master_navigator', 'local_ascend_rewards'),
                'method' => 'meta',
            ],
            9 => [
                'name' => get_string('badge_name_early_bird', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_early_bird', 'local_ascend_rewards'),
                'method' => 'early_bird',
            ],
            11 => [
                'name' => get_string('badge_name_sharp_shooter', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_sharp_shooter', 'local_ascend_rewards'),
                'method' => 'sharp_shooter',
            ],
            10 => [
                'name' => get_string('badge_name_deadline_burner', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_deadline_burner', 'local_ascend_rewards'),
                'method' => 'deadline_burner',
            ],
            12 => [
                'name' => get_string('badge_name_time_tamer', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_time_tamer', 'local_ascend_rewards'),
                'method' => 'meta',
            ],
            13 => [
                'name' => get_string('badge_name_feedback_follower', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_feedback_follower', 'local_ascend_rewards'),
                'method' => 'feedback_follower',
            ],
            15 => [
                'name' => get_string('badge_name_steady_improver', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_steady_improver', 'local_ascend_rewards'),
                'method' => 'steady_improver',
            ],
            14 => [
                'name' => get_string('badge_name_tenacious_tiger', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_tenacious_tiger', 'local_ascend_rewards'),
                'method' => 'tenacious_tiger',
            ],
            16 => [
                'name' => get_string('badge_name_glory_guide', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_glory_guide', 'local_ascend_rewards'),
                'method' => 'meta',
            ],
            19 => [
                'name' => get_string('badge_name_high_flyer', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_high_flyer', 'local_ascend_rewards'),
                'method' => 'high_flyer',
            ],
            17 => [
                'name' => get_string('badge_name_activity_ace', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_activity_ace', 'local_ascend_rewards'),
                'method' => 'activity_ace',
            ],
            7 => [
                'name' => get_string('badge_name_mission_complete', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_mission_complete', 'local_ascend_rewards'),
                'method' => 'mission_complete',
            ],
            20 => [
                'name' => get_string('badge_name_learning_legend', 'local_ascend_rewards'),
                'desc' => get_string('admin_debug_badge_desc_learning_legend', 'local_ascend_rewards'),
                'method' => 'meta',
            ],
        ];
    }
}
