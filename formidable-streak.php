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

define('FORMIDABLE_STREAK_OPTION_GROUP', 'FORMIDABLE_STREAK_OPTION_GROUP');
define('FORMIDABLE_STREAK_FIELD_ID', 'FORMIDABLE_STREAK_FIELD_ID');
define('FORMIDABLE_STREAK_USER_META_LAST_STREAK', 'user_streak_last');
define('FORMIDABLE_STREAK_USER_META_BEST_STREAK', 'user_streak_longest');
define('FORMIDABLE_STREAK_USER_META_STREAK_10', 'achieved_streak_of_10');
define('FORMIDABLE_STREAK_USER_META_STREAK_100', 'achieved_streak_of_100');

add_shortcode('formidable-streak-last', function () {
    $user_meta = get_user_meta(get_current_user_id(), FORMIDABLE_STREAK_USER_META_LAST_STREAK);
    return isset($user_meta[0]) ? $user_meta[0] : 0;
});

add_shortcode('formidable-streak-best', function () {
    $user_meta = get_user_meta(get_current_user_id(), FORMIDABLE_STREAK_USER_META_BEST_STREAK);
    return isset($user_meta[0]) ? $user_meta[0] : 0;
});

add_shortcode('formidable-streak-achieved-10', function () {
    $user_meta = get_user_meta(get_current_user_id(), FORMIDABLE_STREAK_USER_META_STREAK_10);
    return isset($user_meta[0]) ? $user_meta[0] : 'Not achieved yet';
});

add_shortcode('formidable-streak-achieved-100', function () {
    $user_meta = get_user_meta(get_current_user_id(), FORMIDABLE_STREAK_USER_META_STREAK_100);
    return isset($user_meta[0]) ? $user_meta[0] : 'Not achieved yet';
});

add_action('admin_menu', function () {
    add_action('admin_init', function () {
        register_setting(FORMIDABLE_STREAK_OPTION_GROUP, FORMIDABLE_STREAK_FIELD_ID);
    });

    add_menu_page('Formidable Streak', 'Formidable Streak', 'administrator', __FILE__, function () {
?>
        <div class="wrap">
            <h1>Formidable Streak</h1>
            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div class="">
                        <div class="meta-box-sortables">
                            <div id="dashboard_quick_press" class="postbox ">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <span>Date Field Configuration</span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <form method="post" action="options.php">
                                        <?php settings_fields(FORMIDABLE_STREAK_OPTION_GROUP); ?>
                                        <?php do_settings_sections(FORMIDABLE_STREAK_OPTION_GROUP); ?>
                                        <div class="input-text-wrap" id="title-wrap">
                                            <label for="title">Date Field ID</label>
                                            <input type="text" name="<?= FORMIDABLE_STREAK_FIELD_ID ?>" value="<?php echo esc_attr(get_option(FORMIDABLE_STREAK_FIELD_ID)); ?>" />
                                        </div>
                                        <p>
                                            <?php submit_button(); ?>
                                            <br class="clear">
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="meta-box-sortables">
                            <div id="" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <span>Available Shortcodes</span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <ol>
                                        <li>[formidable-streak-last][/formidable-streak-last]</li>
                                        <li>[formidable-streak-best][/formidable-streak-best</li>
                                        <li>[formidable-streak-achieved-10][/formidable-streak-achieved-10]</li>
                                        <li>[formidable-streak-achieved-100][/formidable-streak-achieved-100]</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }, '');
});

add_action('frm_after_create_entry', 'formidable_streak_calculate', 30, 2);

function formidable_streak_calculate($entry_id, $form_id)
{
    $date_field_id = get_option(FORMIDABLE_STREAK_FIELD_ID);
    if ('' == $date_field_id) return true;

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
    if (is_null($submitted_streak->answer_id)) return true;

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
    update_user_meta((int) $submitted_streak->user_id, FORMIDABLE_STREAK_USER_META_LAST_STREAK, $last_streak);

    // UPDATE BEST STREAK
    $best_streak = get_user_meta((int)$submitted_streak->user_id, FORMIDABLE_STREAK_USER_META_BEST_STREAK);
    $best_streak = isset($best_streak[0]) ? $best_streak[0] : 0;
    if ((int) $last_streak >= (int) $best_streak) update_user_meta((int)$submitted_streak->user_id, FORMIDABLE_STREAK_USER_META_BEST_STREAK, $last_streak);

    // UPDATE ACHIEVED-10
    if (10 === (int)$last_streak) update_user_meta((int)$submitted_streak->user_id, FORMIDABLE_STREAK_USER_META_STREAK_10, 'Achieved');

    // UPDATE ACHIEVED-100
    if (100 === (int)$last_streak) update_user_meta((int)$submitted_streak->user_id, FORMIDABLE_STREAK_USER_META_STREAK_100, 'Achieved');
}
