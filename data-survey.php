<?php

/**
 * Data Survey
 *
 * @package     DataSurvey
 * @author      Henri Susanto
 * @copyright   2022 Henri Susanto
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Data Survey
 * Plugin URI:  https://github.com/susantohenri/data-survey
 * Description: Plugin for managing question rotation of data survey
 * Version:     1.0.0
 * Author:      Henri Susanto
 * Author URI:  https://github.com/susantohenri/
 * Text Domain: DataSurvey
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

define('DATA_SURVEY_CSV_FILE_SAMPLE', plugin_dir_url(__FILE__) . 'data-survey-sample.csv');
define('DATA_SURVEY_CSV_FILE_ACTIVE', plugin_dir_url(__FILE__) . 'data-survey-active.csv');
define('DATA_SURVEY_CSV_FILE', plugin_dir_path(__FILE__) . 'data-survey-active.csv');
define('DATA_SURVEY_CSV_FILE_SUBMIT', 'data-survey-submit');
define('DATA_SURVEY_LATEST_CSV_OPTION', 'data-survey-last-uploaded-csv');

add_action('admin_menu', 'data_survey_admin_menu');
add_action('rest_api_init', 'data_survey_rest_api_init');
add_filter('frm_get_default_value', 'data_survey_rotate_fields', 10, 3);

function data_survey_admin_menu()
{
    add_menu_page('Data Survey', 'Data Survey', 'administrator', __FILE__, function () {
        if ($_FILES) {
            if ($_FILES[DATA_SURVEY_CSV_FILE_SUBMIT]['tmp_name']) {
                move_uploaded_file($_FILES[DATA_SURVEY_CSV_FILE_SUBMIT]['tmp_name'], DATA_SURVEY_CSV_FILE);
                update_option(DATA_SURVEY_LATEST_CSV_OPTION, $_FILES[DATA_SURVEY_CSV_FILE_SUBMIT]['name']);
            }
        }
?>
        <div class="wrap">
            <h1>Data Survey</h1>
            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div class="">
                        <div class="meta-box-sortables">
                            <div id="dashboard_quick_press" class="postbox ">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <span>Data Survey CSV</span>
                                        <div>
                                            <?php if (file_exists(DATA_SURVEY_CSV_FILE)) : ?>
                                                <a class="button button-primary" href="<?= site_url() . '/wp-json/data-survey/v1/download-latest' ?>" style="text-decoration:none;">Export Current CSV</a>
                                            <?php endif ?>
                                            <a class="button button-primary" href="<?= site_url() . '/wp-json/data-survey/v1/download-sample' ?>" style="text-decoration:none;">Download CSV Sample File</a>
                                        </div>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <form name="post" action="" method="post" class="initial-form" enctype="multipart/form-data">
                                        <div class="input-text-wrap" id="title-wrap">
                                            <label> Last Uploaded CSV File Name: </label>
                                            <b><?= get_option(DATA_SURVEY_LATEST_CSV_OPTION) ?></b>
                                        </div>
                                        <div class="input-text-wrap" id="title-wrap">
                                            <label for="title"> Choose New CSV File </label>
                                            <input type="file" name="<?= DATA_SURVEY_CSV_FILE_SUBMIT ?>">
                                        </div>
                                        <p>
                                            <input type="submit" name="save" class="button button-primary" value="Upload Selected CSV">
                                            <br class="clear">
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }, '');
}

function data_survey_rest_api_init()
{
    register_rest_route('data-survey/v1', '/download-sample', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $filename = basename(DATA_SURVEY_CSV_FILE_SAMPLE);
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Type: text/csv');
            readfile(DATA_SURVEY_CSV_FILE_SAMPLE);
        }
    ));
    register_rest_route('data-survey/v1', '/download-latest', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $filename = basename(DATA_SURVEY_CSV_FILE_ACTIVE);
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Type: text/csv');
            readfile(DATA_SURVEY_CSV_FILE_ACTIVE);
        }
    ));
}

function data_survey_rotate_fields($new_value, $field, $is_default)
{
    if (3890 != $field->id) return $new_value;
    if (!$is_default) return $new_value;
    if (!file_exists(DATA_SURVEY_CSV_FILE)) return $new_value;

    $rows = [];
    if (($open = fopen(DATA_SURVEY_CSV_FILE, 'r')) !== FALSE) {
        while (($data = fgetcsv($open, 100000, ",")) !== FALSE) $rows[] = $data;
        fclose($open);
    }

    global $wpdb;
    $current_user_type = $wpdb->get_results($wpdb->prepare("
        SELECT {$wpdb->prefix}frm_item_metas.meta_value
        FROM {$wpdb->prefix}frm_items
        LEFT JOIN {$wpdb->prefix}frm_item_metas ON {$wpdb->prefix}frm_item_metas.item_id = {$wpdb->prefix}frm_items.id
        WHERE {$wpdb->prefix}frm_items.user_id = %d
        AND {$wpdb->prefix}frm_items.form_id = 38
        AND {$wpdb->prefix}frm_item_metas.field_id = 771
    ", get_current_user_id()));
    $current_user_type = array_map(function ($role) {
        return $role->meta_value;
    }, $current_user_type);

    // exclude rows not match current user type
    $rows = array_values(array_filter($rows, function ($row) use ($current_user_type) {
        $csv_user_type = explode(',', $row[1]);
        $csv_user_type = array_map(function ($user_type) {
            return trim($user_type);
        }, $csv_user_type);
        $intersection = array_intersect($csv_user_type, $current_user_type);
        return !empty($intersection);
    }));

    wp_register_script('data-survey', plugin_dir_url(__FILE__) . 'data-survey.js', array('jquery'));
    wp_enqueue_script('data-survey');

    // 1st priority: static fields
    $statics = array_values(array_filter($rows, function ($cols) {
        return 'Static' === $cols[3];
    }));
    foreach ($statics as $cols) {
        $field = $cols[0];
        $days = $cols[4];
        $answer_exists = $wpdb->get_results($wpdb->prepare("
            SELECT id
            FROM {$wpdb->prefix}frm_item_metas
            WHERE field_id = 3890
            AND meta_value = %d
            AND DATEDIFF(NOW(), created_at) < %d
        ", $field, $days));
        if (empty($answer_exists)) return json_encode($cols);
    }

    // 2nd priority: sequence fields
    $sequences = array_values(array_filter($rows, function ($cols) {
        return 'Sequence' === $cols[3];
    }));
    foreach ($sequences as $cols) {
        $field = $cols[0];
        $times = $cols[4];
        $recent_answers = $wpdb->get_results($wpdb->prepare("
            SELECT meta_value
            FROM {$wpdb->prefix}frm_item_metas
            WHERE field_id = 3890
            ORDER BY id DESC
            LIMIT %d
        ", $times));
        $matching_answers = array_values(array_filter($recent_answers, function ($answer) use ($field) {
            return $answer->meta_value == $field;
        }));
        if (empty($matching_answers)) return json_encode($cols);
    }

    // 3rd priority: regular fields
    $regulars = array_values(array_filter($rows, function ($cols) {
        return 'Regular' === $cols[3];
    }));
    foreach ($regulars as $cols) {
        $field = $cols[0];
        $percent = explode('%', $cols[4])[0];
        $all_answers = $wpdb->get_results($wpdb->prepare("
            SELECT meta_value
            FROM {$wpdb->prefix}frm_item_metas
            WHERE field_id = %d
        ", 3890));
        $current_field_answers = array_filter($all_answers, function ($answer) use ($field) {
            return $answer->meta_value == $field;
        });
        if (count($current_field_answers) / count($all_answers) < (int) $percent / 100) return json_encode($cols);
    }

    // last: default
    $defaults = array_values(array_filter($rows, function ($cols) {
        return 'Default' === $cols[3];
    }));
    return json_encode($defaults[0]);
}
