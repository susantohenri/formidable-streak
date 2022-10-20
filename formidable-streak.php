<?php

/**
 * Formidable Streak
 *
 * @package     FormidableStreak
 * @author      Henri Susanto
 * @copyright   2022 Henri Susanto
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Formidable Streak
 * Plugin URI:  https://github.com/susantohenri
 * Description: Formidable form add-on for calculating user streak
 * Version:     1.0.0
 * Author:      Henri Susanto
 * Author URI:  https://github.com/susantohenri
 * Text Domain: Formidable-Streak
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// replace 999 with date field id

add_shortcode('formidable-streak-last-999', function () {
    $user_meta = get_user_meta(get_current_user_id(), "ENTRY_USER_META_LAST_STREAK_999");
    return isset($user_meta[0]) ? $user_meta[0] : 0;
});

add_shortcode('formidable-streak-best-999', function () {
    $user_meta = get_user_meta(get_current_user_id(), "ENTRY_USER_META_BEST_STREAK_999");
    return isset($user_meta[0]) ? $user_meta[0] : 0;
});

add_shortcode('formidable-streak-achieved-10-999', function () {
    $user_meta = get_user_meta(get_current_user_id(), "ENTRY_USER_META_STREAK_10_999");
    return isset($user_meta[0]) ? $user_meta[0] : 'Not achieved yet';
});

add_shortcode('formidable-streak-achieved-100-999', function () {
    $user_meta = get_user_meta(get_current_user_id(), "ENTRY_USER_META_STREAK_100_999");
    return isset($user_meta[0]) ? $user_meta[0] : 'Not achieved yet';
});

add_action('frm_after_create_entry', 'streak_submission_999', 30, 2);
add_action('frm_before_destroy_entry', 'streak_deletion_999', 10, 2);

function streak_submission_999($entry_id)
{
    $user_id = detect_date_field_999($entry_id);
    if (!$user_id) return true;

    $entries = get_all_entries_999($user_id);
    calculate_streak_999($user_id, $entries);
}

function streak_deletion_999($entry_id)
{
    $user_id = detect_date_field_999($entry_id);
    if (!$user_id) return true;

    $entries = get_all_entries_999($user_id);
    $entries = array_filter($entries, function ($entry) use ($entry_id) {
        return $entry->entry_id != $entry_id;
    });
    calculate_streak_999($user_id, $entries);
}

function detect_date_field_999($entry_id)
{
    global $wpdb;
    $detector = $wpdb->prepare("
        SELECT
            entry.user_id
        FROM {$wpdb->prefix}frm_items entry
        LEFT JOIN {$wpdb->prefix}frm_forms form ON entry.form_id = form.id
        LEFT JOIN {$wpdb->prefix}frm_fields field ON field.form_id = form.id
        WHERE TRUE
        AND entry.id = %d
        AND field.id = %d
    ", $entry_id, 999);
    $detected = $wpdb->get_row($detector);
    return !is_null($detected) ? $detected->user_id : false;
}

function get_all_entries_999($user_id)
{
    global $wpdb;
    $entries = $wpdb->prepare("
        SELECT
            entry.id entry_id
            , answer.meta_value answer_value
        FROM {$wpdb->prefix}frm_items entry
        RIGHT JOIN {$wpdb->prefix}frm_item_metas answer ON answer.item_id = entry.id
        WHERE TRUE
        AND entry.user_id = %d
        AND answer.field_id = %d
    ", $user_id, 999);
    return $wpdb->get_results($entries);
}

function calculate_streak_999($user_id, $entries)
{
    $today = (new DateTime())->format('Y-m-d');
    $dates = array_map(function ($date) {
        return new DateTime($date->answer_value);
    }, $entries);

    // EXCLUDE FUTURE DATES
    $dates = array_filter($dates, function ($date) use ($today) {
        return $date <= new DateTime($today);
    });

    $first_streak = 0 < count($dates) ? min($dates) : new DateTime($today);

    $streak_group = 0;
    $streaks = [$streak_group => 0];
    $day = new DateTime($today);
    while ($day >= $first_streak) {
        if (in_array($day, $dates)) $streaks[$streak_group] += 1;
        else {
            if (0 != $streaks[$streak_group]) {
                $streak_group++;
                $streaks[$streak_group] = 0;
            }
        }
        $day->modify('-1 day');
    }

    // UPDATE STREAK
    update_user_meta((int)$user_id, "ENTRY_USER_META_LAST_STREAK_999", $streaks[0]);

    // UPDATE BEST STREAK
    arsort($streaks);
    update_user_meta((int)$user_id, "ENTRY_USER_META_BEST_STREAK_999", $streaks[0]);

    // UPDATE ACHIEVED-10
    $streak_10_exists = array_filter($streaks, function ($streak) {
        return $streak >= 10;
    });
    if (count($streak_10_exists) > 0) update_user_meta((int)$user_id, "ENTRY_USER_META_STREAK_10_999", 'Achieved');
    else update_user_meta((int)$user_id, "ENTRY_USER_META_STREAK_10_999", 'Not achieved yet');

    // UPDATE ACHIEVED-100
    $streak_100_exists = array_filter($streaks, function ($streak) {
        return $streak >= 100;
    });
    if (count($streak_100_exists) > 0) update_user_meta((int)$user_id, "ENTRY_USER_META_STREAK_100_999", 'Achieved');
    else update_user_meta((int)$user_id, "ENTRY_USER_META_STREAK_100_999", 'Not achieved yet');

    // UPDATE FIRST DAY OF LAST STREAK
    update_user_meta((int)$user_id, "ENTRY_USER_META_FIRST_DAY_LAST_STREAK_999", $first_streak->format('Y-m-d'));
}
