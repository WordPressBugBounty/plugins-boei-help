<?php
/*
 * Plugin Name: Boei
 * Version: 1.6.0
 * Plugin URI: https://www.boei.help/?utm_source=wordpress&utm_medium=wp_plugins
 * Description: Boei is a widget that allows you to engage with your website visitors in their favourite messaging app (e.g., Telegram, SMS, WeChat). Convert more using Boei by talking to visitors on their favourite channels.
 * Author: Boei
 * Author URI: https://www.boei.help/?utm_source=wordpress&utm_medium=wp_plugins
 * Tested up to: 6.6.2
 * Requires PHP: 7.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Boei
 * @author Boei
 * @license GPL-2.0-or-later
 */

// Test readme: https://wpreadme.com

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional function for users that want to test on localhost with their live domain
 *
 * @link https://app.boei.help/docs/1.0/localhost
 * Not used anymore for loading the script for Telegram, Twitter DMs, etc
 * Now use the advanced installation method.
 */
// function boei_localhost_test(){
//     echo '<script>window.Boei_Test_Hostname = "example.com";</script>';
// }
// add_action('wp_head', 'boei_localhost_test');

/**
 * Loading the Boei script on the frontend.
 * wp_enqueue_script() function doesn’t support async.
 * This will load the script for Telegram, Twitter DMs, SMS, WeChat, etc
 */
function boei_load_script()
{
    if (boei_get_key()) {
        // Advanced installation
        wp_enqueue_script('boei', 'https://app.boei.help/embed/k/' . boei_get_key(), array(), '1.0', true);
    } else {
        // Regular installation
        wp_enqueue_script('boei', 'https://cdn.boei.help/hello.js', array(), '1.0', true);
    }
}

add_action('wp_enqueue_scripts', 'boei_load_script');

/**
 * Adding link to the Boei settings page in wp-plugins admin
 * On the settings page, one can manage the buttons with Telegram, Twitter DMs, SMS, WeChat, etc
 */
function boei_admin_action_links($links)
{
    $links = array_merge(array(
        '<a href="' . esc_url(add_query_arg(
            'page',
            'boei-help-settings',
            get_admin_url() . 'admin.php'
        )) . '">' . __('Setup & Settings', 'textdomain') . '</a>',
        '<a href="' . esc_url(boei_url_homepage()) . '">' . __('Support', 'textdomain') . '</a>',
    ), $links);

    return $links;
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'boei_admin_action_links');

/**
 * Add menu option for Boei and register the settings
 * Clicking on it will lead to the settings for Telegram, Twitter DMs, etc
 */
function boei_register_admin()
{
    // Add menu option
    add_menu_page(__('Boei', 'boei'), __('Boei', 'boei'), 'manage_options', 'boei-help-settings', 'boei_settings', 'dashicons-format-chat');

    // Register the settings
    register_setting('boei_key', 'boei_key_option');
}

add_action('admin_menu', 'boei_register_admin');

/**
 * Return value of the Boei key
 * The advanced installation key for the script for Telegram, Twitter DMs, etc
 */
function boei_get_key()
{
    return get_option('boei_key_option');
}

/**
 * Display settings and installation page
 * This basically redirects to the Boei admin area.
 * On the admin area, one can manage the buttons and helpers for Telegram, Twitter DMs, etc
 */
function boei_settings()
{
    $safeLogoURL = esc_url(boei_url_logo());
    $safeManageURL = esc_url(boei_url_manage());
    $safeHomepageURL = esc_url(boei_url_homepage());
    $safeInstallationURL = esc_url('https://boei.help/docs/installation-wordpress?utm_source=wordpress&utm_medium=wp_plugins');
    $safeRoadmapURL = esc_url('https://feedback.boei.help');
    $Boei = "<a href=\"" . $safeHomepageURL . "\" target=\"_blank\">Boei</a>";

    // Get current user and domain for SSO-like registration
    $current_user = wp_get_current_user();
    $boei_register_email = $current_user->user_email;

    $urlparts = parse_url(home_url());
    $boei_register_domain = $urlparts['host'];

    $safeRegisterURL = esc_url('https://app.boei.help/register?utm_source=wordpress&utm_medium=wp_plugins&email=' . urlencode($boei_register_email) . '&domain=' . urlencode($boei_register_domain));

    echo <<<EOTEXT

    <div class="wrap">
        <div id="icon-my-id" class="icon32">
            <img src="{$safeLogoURL}" style="max-width: 32px; margin-top: 20px;" alt="logo">
        </div>
        <h2>Boei</h2>

        <div style="display: flex; flex-direction: row; flex-wrap: wrap; width: 100%;">
            <div style="padding-right: 60px; display: flex; flex-direction: column; flex-basis: 100%; flex: 2; min-width: 300px; font-size: 14px;">
                <div class="postbox">
                    <div class="postbox-header">
                        <h4 style="padding-left: 12px;">Welcome to Boei 👋</h4>
                    </div>
                    <div class="inside">
                        Don't drop customers trying to reach you. With {$Boei}, you offer their favorite contact channels in a pretty widget.
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h4 style="padding-left: 12px;">Installation</h4>
                    </div>
                    <div class="inside">
                        <p>1. Start here 👇</p>
                        <a href="{$safeRegisterURL}" class="button button-secondary" target="_blank" style="background-color: #713eec; color: #ffffff; border: 0;">Create free widget</a>

                        <p style="margin-top:40px;">2. Enter your widget key to connect your widget with WordPress. <a href="{$safeInstallationURL}">Where can I find this key?</a><br><br><strong>Widget Key</strong></p>

EOTEXT;

    echo '<form action="options.php" method="POST">';
    settings_fields('boei_key');
    do_settings_sections('boei_key');
    echo '<input type="text" name="boei_key_option" value="' . esc_attr(boei_get_key()) . '" style="width: 80%;" placeholder="Example: 21424816-afa8-4f70-85f0-85ddfbcbcec6" />';
    submit_button('Save key');
    echo '</form>';

    echo <<<EOTEXT

                        <p>You make changes in the Boei app. This ensures you have the latest version and features.</p>
                        <a href="{$safeManageURL}" class="button button-secondary" target="_blank">Manage existing widgets</a>
                    </div>
                </div>

                <div class="postbox">
                    <div class="inside">
                        Thanks for choosing Boei! Do you like us? Please support us with a <a href="https://wordpress.org/support/plugin/boei-help/reviews/#new-post" target="_blank">⭐️⭐️⭐️⭐️⭐️ review</a>.
                    </div>
                </div>

            </div>
            <div style="padding-right: 30px; display: flex; flex-direction: column; flex-basis: 100%; flex: 1; min-width: 300px; font-size: 10px;">

                <div class="video-container" style="position:relative; padding-bottom:56.25%; padding-top:30px; height:0; overflow:hidden;">
                    <iframe style="position:absolute; top:0; left:0; width:100%; height:100%;" width="560" height="315" src="https://www.youtube.com/embed/BmEz7_3HFs4" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>

                <br />

                <h3 style="margin-bottom: 0;">Questions or support</h3>
                <p>Go to the <a href="{$safeHomepageURL}" target="_blank">Boei site</a> and click our Boei widget 💪</p>

                <br />

                <h3 style="margin-bottom: 0;">Roadmap & feedback</h3>
                <p>You can follow Boei developments on our <a href="{$safeRoadmapURL}" target="_blank">public roadmap</a>.</p>

            </div>
        </div>
    </div>
EOTEXT;
}

/**
 * Return app management URL
 */
function boei_url_manage()
{
    return 'https://app.boei.help?utm_source=wordpress&utm_medium=wp_plugins';
}

/**
 * Return Boei homepage
 */
function boei_url_homepage()
{
    return 'https://www.boei.help/?utm_source=wordpress&utm_medium=wp_plugins';
}

/**
 * Return Boei logo
 */
function boei_url_logo()
{
    return 'https://www.boei.help/static/logo.svg';
}
