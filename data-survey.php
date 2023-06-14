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
add_action('frm_entries_footer_scripts', 'data_survey_register_script', 20, 2);
add_filter('frm_filter_view', 'data_survey_view', 10, 1);

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

function data_survey_register_script($fields, $form)
{
    if (153 == $form->id) {
        wp_register_script('data-survey', plugin_dir_url(__FILE__) . 'data-survey.js', array('jquery'));
        wp_enqueue_script('data-survey');
        $cache_breaker = time();
        $user_id = base64_encode(get_current_user_id());
        wp_localize_script(
            'data-survey',
            'data_survey',
            array(
                'url' => site_url("wp-json/data-survey/v1/get-field?key={$user_id}&cache-breaker={$cache_breaker}"),
            )
        );
    }
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
    register_rest_route('data-survey/v1', '/get-field', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'data_survey_rotate_fields'
    ));
}

function data_survey_rotate_fields()
{
    if (!file_exists(DATA_SURVEY_CSV_FILE)) return '';

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
    ", base64_decode($_GET['key'])));
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

    // 1st priority: static fields
    $statics = array_values(array_filter($rows, function ($cols) {
        return 'Static' === $cols[3];
    }));
    if (!empty($statics)) {
        $static_fields = implode(',', array_map(function ($static) {
            return $static[0];
        }, $statics));
        $last_time_answered = $wpdb->get_results($wpdb->prepare("
            SELECT
                {$wpdb->prefix}frm_item_metas.field_id
                , DATEDIFF(CURRENT_DATE, {$wpdb->prefix}frm_item_metas.created_at) last_answered_days
            FROM {$wpdb->prefix}frm_item_metas
            WHERE %d AND {$wpdb->prefix}frm_item_metas.field_id IN ($static_fields)
        ", true));
        if (empty($last_time_answered)) return json_encode($statics[0]);

        $pairable_last_time_answered = [];
        foreach ($last_time_answered as $lta) $pairable_last_time_answered[$lta->field_id] = $lta->last_answered_days;

        $statics_sortable = [];
        foreach ($statics as $static) {
            $field = $static[0];
            $frequency = $static[4];
            if (!isset($pairable_last_time_answered[$field])) return json_encode($static);
            else if ($pairable_last_time_answered[$field] < $frequency) continue;
            else $statics_sortable[] = [
                'row' => json_encode($static),
                'weight' => $pairable_last_time_answered[$field] - $frequency
            ];
        }

        if (!empty($statics_sortable)) {
            usort($statics_sortable, function ($a, $b) {
                return $b['weight'] - $a['weight'];
            });
            return $statics_sortable[0]['row'];
        }
    }

    // 2nd priority: sequence fields
    $sequences = array_values(array_filter($rows, function ($cols) {
        return 'Sequence' === $cols[3];
    }));
    if (!empty($sequences)) {
        $sequences_frequencies = array_map(function ($seqence) {
            return $seqence[4];
        }, $sequences);
        $sequences_greatest_frequency = max($sequences_frequencies) + 1;
        $recent_answers = $wpdb->get_results($wpdb->prepare("
            SELECT meta_value
            FROM {$wpdb->prefix}frm_item_metas
            WHERE field_id = 3890
            ORDER BY id DESC
            LIMIT %d
        ", $sequences_greatest_frequency));
        if (empty($recent_answers)) return json_encode($sequences[0]);

        $pairable_recent_answers = [];
        foreach ($recent_answers as $index => $answer) {
            if (isset($pairable_recent_answers[$answer->meta_value])) continue;
            $pairable_recent_answers[$answer->meta_value] = $index;
        }

        $sequences_sortable = [];
        foreach ($sequences as $sequence) {
            $field = $sequence[0];
            $frequency = $sequence[4];
            if (!isset($pairable_recent_answers[$field])) return json_encode($sequence);
            else if ($pairable_recent_answers[$field] < $frequency) continue;
            else $sequences_sortable[] = [
                'row' => json_encode($sequence),
                'weight' => $pairable_recent_answers[$field] - $frequency
            ];
        }
        if (!empty($sequences_sortable)) {
            usort($sequences_sortable, function ($a, $b) {
                return $b['weight'] - $a['weight'];
            });
            return $sequences_sortable[0]['row'];
        }
    }

    // 3rd priority: regular fields
    $regulars = array_values(array_filter($rows, function ($cols) {
        return 'Regular' === $cols[3];
    }));
    if (!empty($regulars)) {
        $regular_fields = implode(',', array_map(function ($regular) {
            return $regular[0];
        }, $regulars));
        $total_answer_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT({$wpdb->prefix}frm_item_metas.id) answer_count
            FROM {$wpdb->prefix}frm_item_metas
            WHERE {$wpdb->prefix}frm_item_metas.field_id = %d
        ", 3890));
        $regular_answer_count = $wpdb->get_results($wpdb->prepare("
            SELECT {$wpdb->prefix}frm_item_metas.field_id, COUNT({$wpdb->prefix}frm_item_metas.id) answer_count
            FROM {$wpdb->prefix}frm_item_metas
            WHERE %d AND {$wpdb->prefix}frm_item_metas.field_id IN($regular_fields)
            GROUP BY {$wpdb->prefix}frm_item_metas.field_id
        ", true));
        if (empty($regular_answer_count)) return json_encode($regulars[0]);

        $pairable_answer_count = [];
        foreach ($regular_answer_count as $count) $pairable_answer_count[$count->field_id] = $count->answer_count;

        $regulars_sortable = [];
        foreach ($regulars as $regular) {
            $field = $regular[0];
            $frequency = explode('%', $regular[4])[0];
            if (!isset($pairable_answer_count[$field])) return json_encode($regular);
            else if ($pairable_answer_count[$field] / $total_answer_count >= $frequency / 100) continue;
            else $regulars_sortable[] = [
                'row' => json_encode($regular),
                'weight' => $pairable_answer_count[$field] / $total_answer_count - $frequency / 100
            ];
        }
        if (!empty($regulars_sortable)) {
            usort($regulars_sortable, function ($a, $b) {
                return $b['weight'] - $a['weight'];
            });
            return $regulars_sortable[0]['row'];
        }
    }

    // last: default
    $defaults = array_values(array_filter($rows, function ($cols) {
        return 'Default' === $cols[3];
    }));
    return json_encode($defaults[0]);
}

function data_survey_view($view)
{
    $view->post_content = str_replace('[3890]', $_POST['item_meta']['3890'], $view->post_content);
    $view->post_content = str_replace('[3891]', $_POST['item_meta']['3891'], $view->post_content);
    return $view;
}
