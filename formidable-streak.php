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

add_action('frm_after_create_entry', function ($entry_id, $form_id) {
    $date_field_id = 999;

    global $wpdb;
    $prefix = $wpdb->prefix;

    // DETECT IF ANY STREAK SUBMITTED
    $any_streak_submitted_query = $wpdb->prepare("
        SELECT
            entry.id entry_id
            , entry.user_id
            , answer.id answer_id
            , answer.meta_value answer_value
        FROM {$prefix}frm_items entry
        LEFT JOIN {$prefix}frm_item_metas answer ON answer.item_id = entry.id
        WHERE TRUE
            AND entry.id = %d
            AND answer.field_id = %d
    ", $entry_id, $date_field_id);
    $submitted_streak = $wpdb->get_row($any_streak_submitted_query);
    if (is_null($submitted_streak)) return true;

    // CALCULATE LAST STREAK
    $last_streak = 0;
    $collect_date_query = $wpdb->prepare("
        SELECT
            answer.meta_value streak_date
        FROM {$wpdb->prefix}frm_item_metas answer
        LEFT JOIN {$wpdb->prefix}frm_items entry ON entry.id = answer.item_id
        WHERE TRUE
            AND answer.field_id = %d
            AND entry.user_id = %d
        ORDER BY DATE(answer.meta_value) DESC
    ", $date_field_id, $submitted_streak->user_id);
    $dates = $wpdb->get_results($collect_date_query);

    $dates = array_map(function ($date) {
        return $date->streak_date;
    }, $dates);

    $day = new DateTime();
    $day->modify('-1 day');
    while (in_array($day->format('Y-m-d'), $dates)) {
        $last_streak++;
        $day->modify('-1 day');
    }

    // UPDATE LAST STREAK
    update_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_LAST_STREAK_999", $last_streak);

    // UPDATE BEST STREAK
    $best_streak = get_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_BEST_STREAK_999");
    $best_streak = isset($best_streak[0]) ? $best_streak[0] : 0;
    if ((int) $last_streak >= (int) $best_streak) update_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_BEST_STREAK_999", $last_streak);

    // UPDATE ACHIEVED-10
    if (10 === (int)$last_streak) update_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_STREAK_10_999", 'Achieved');

    // UPDATE ACHIEVED-100
    if (100 === (int)$last_streak) update_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_STREAK_100_999", 'Achieved');

    // UPDATE FIRST DAY OF LAST STREAK
    update_user_meta((int)$submitted_streak->user_id, "ENTRY_USER_META_FIRST_DAY_LAST_STREAK_999", $day->format('Y-m-d'));
}, 30, 2);
