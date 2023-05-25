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
