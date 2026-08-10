<?php
/*
 * Plugin Name: Boei – AI Chatbot, Live Chat & 50+ Channels
 * Version: 1.9.3
 * Plugin URI: https://boei.help/ai-chatbot/wordpress/?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=plugin_header
 * Description: Capture every lead. Reply instantly. Close more deals. AI chatbot, 50+ contact channels, single inbox, and lead tracking—all in one plugin.
 * Author: Boei
 * Author URI: https://www.boei.help/?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=author_header
 * Tested up to: 7.0
 * Requires at least: 5.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: boei-help
 * Domain Path: /languages
 *
 * @package Boei
 * @author Boei
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return allowed HTML tag list for inline SVG icons rendered in the admin UI.
 *
 * Used with wp_kses() so the plugin can safely echo static SVG markup while
 * still passing the WP.org Plugin Check output-escaping rules.
 */
function boei_allowed_svg_tags()
{
    return array(
        'svg' => array(
            'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true,
            'xmlns' => true, 'stroke' => true, 'stroke-width' => true,
        ),
        'path' => array(
            'd' => true, 'fill' => true, 'stroke' => true,
            'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-width' => true,
        ),
        'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true),
        'g' => array('fill' => true, 'stroke' => true),
    );
}

/**
 * Verify widget key with Boei API
 *
 * @param string $key The widget key to verify
 * @return bool True if valid, false otherwise
 */
function boei_verify_key($key)
{
    if (empty($key)) {
        return false;
    }

    $response = wp_remote_get(
        'https://app.boei.help/api/domains/' . sanitize_text_field($key) . '/verify',
        array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        )
    );

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['valid']) && $data['valid'] === true;
}

/**
 * AJAX handler for widget key verification
 */
function boei_ajax_verify_key()
{
    check_ajax_referer('boei_verify_key', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'boei-help')));
    }

    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

    if (empty($key)) {
        wp_send_json_error(array('message' => __('Please enter a widget key', 'boei-help')));
    }

    $is_valid = boei_verify_key($key);

    if ($is_valid) {
        wp_send_json_success(array('message' => __('Widget key is valid!', 'boei-help')));
    } else {
        wp_send_json_error(array('message' => __('Invalid widget key. Please check and try again.', 'boei-help')));
    }
}

add_action('wp_ajax_boei_verify_key', 'boei_ajax_verify_key');

/**
 * Validate widget key before saving
 *
 * @param string $value The value to sanitize
 * @return string The sanitized value, or previous value if invalid
 */
function boei_sanitize_key($value)
{
    $value = sanitize_text_field($value);
    $previous_value = get_option('boei_key_option', '');

    // If empty, show error
    if (empty($value)) {
        add_settings_error(
            'boei_key_option',
            'empty_key',
            __('Please enter a widget key', 'boei-help'),
            'error'
        );
        return '';
    }

    // Verify the key with the API
    if (!boei_verify_key($value)) {
        add_settings_error(
            'boei_key_option',
            'invalid_key',
            __('Invalid widget key. Please check and try again.', 'boei-help'),
            'error'
        );
        // Return the previous value so invalid keys don't get saved
        return $previous_value;
    }

    // If this is a first-time connection, set celebration flag
    if (empty($previous_value) && !empty($value)) {
        set_transient('boei_just_connected', true, 30);
    }

    return $value;
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
        )) . '">' . __('Setup & Settings', 'boei-help') . '</a>',
        '<a href="' . esc_url(boei_url_homepage()) . '">' . __('Support', 'boei-help') . '</a>',
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
    // Add top-level menu with chat icon
    add_menu_page(__('Boei', 'boei-help'), __('Boei', 'boei-help'), 'manage_options', 'boei-help-settings', 'boei_settings', 'dashicons-format-chat');

    // Register the settings with sanitize callback
    register_setting(
        'boei_key',
        'boei_key_option',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'boei_sanitize_key',
        )
    );
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
    // URLs are escaped at each echo point below (esc_url()) rather than
    // pre-escaped here, so the WP.org Plugin Check output-escaping rule
    // detects escaping at the actual output boundary.
    $homepageURL = boei_url_homepage();
    $installationURL = 'https://boei.help/docs/installation-wordpress?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=onboarding_docs';
    $roadmapURL = 'https://feedback.boei.help?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=footer_roadmap';

    $current_user = wp_get_current_user();
    $boei_register_email = $current_user->user_email;
    $urlparts = wp_parse_url(home_url());
    $boei_register_domain = !empty($urlparts['host']) ? $urlparts['host'] : '';
    $registerURL = 'https://app.boei.help/register?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=onboarding_register&email=' . urlencode($boei_register_email) . '&domain=' . urlencode($boei_register_domain);

    $has_key = !empty(boei_get_key());

    echo '<div class="wrap">';
    echo '<h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">';
    echo '<svg width="32" height="32" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="50" fill="#713eec"/><path d="M30 35c0-2.8 2.2-5 5-5h30c2.8 0 5 2.2 5 5v20c0 2.8-2.2 5-5 5H45l-10 10v-10h-5c-2.8 0-5-2.2-5-5V35z" fill="#fff"/></svg>';
    echo 'Boei</h1>';

    // Add inline CSS for hover effects and JS for key verification
        echo '<style>.boei-card:hover { border-color: #713eec !important; }</style>';
        ?>
        <script>
        function boeiTestKey() {
            var keyInput = document.querySelector('#boei-key-edit input[name="boei_key_option"], input[name="boei_key_option"]');
            var testBtn = document.getElementById('boei-test-btn');
            var resultSpan = document.getElementById('boei-test-result');
            var key = keyInput ? keyInput.value.trim() : '';

            if (!key) {
                resultSpan.innerHTML = '<span style="color: #d63638;"><?php echo esc_js(__('Please enter a widget key', 'boei-help')); ?></span>';
                return;
            }

            testBtn.disabled = true;
            testBtn.textContent = '<?php echo esc_js(__('Testing...', 'boei-help')); ?>';
            resultSpan.innerHTML = '';

            var formData = new FormData();
            formData.append('action', 'boei_verify_key');
            formData.append('nonce', '<?php echo esc_attr(wp_create_nonce('boei_verify_key')); ?>');
            formData.append('key', key);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                testBtn.disabled = false;
                testBtn.textContent = '<?php echo esc_js(__('Test', 'boei-help')); ?>';
                if (data.success) {
                    resultSpan.innerHTML = '<span style="color: #00a32a;">✓ ' + data.data.message + '</span>';
                } else {
                    resultSpan.innerHTML = '<span style="color: #d63638;">✗ ' + data.data.message + '</span>';
                }
            })
            .catch(function() {
                testBtn.disabled = false;
                testBtn.textContent = '<?php echo esc_js(__('Test', 'boei-help')); ?>';
                resultSpan.innerHTML = '<span style="color: #d63638;"><?php echo esc_js(__('Connection error. Please try again.', 'boei-help')); ?></span>';
            });
        }

        function boeiShowEdit() {
            document.getElementById('boei-key-display').style.display = 'none';
            document.getElementById('boei-key-edit').style.display = 'block';
        }

        function boeiCancelEdit() {
            document.getElementById('boei-key-edit').style.display = 'none';
            document.getElementById('boei-key-display').style.display = 'flex';
            document.getElementById('boei-test-result').innerHTML = '';
        }

        function boeiDeactivate() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to deactivate your widget?', 'boei-help')); ?>')) {
                document.getElementById('boei-deactivate-form').submit();
            }
        }
        </script>
        <?php
        settings_errors('boei_key_option');

        if ($has_key) {
        // ========== CONNECTED STATE ==========
        $just_connected = get_transient('boei_just_connected');
        if ($just_connected) {
            delete_transient('boei_just_connected');
            // Celebration message for first-time connection
            echo '<div style="background: linear-gradient(135deg, #713eec 0%, #9b6df5 100%); border-radius: 8px; padding: 24px; margin-bottom: 20px; color: #fff; text-align: center;">';
            echo '<div style="font-size: 32px; margin-bottom: 8px;">&#127881;</div>';
            echo '<div style="font-size: 20px; font-weight: 600; margin-bottom: 8px;">' . esc_html__("You're all set!", 'boei-help') . '</div>';
            echo '<div style="opacity: 0.9;">' . esc_html__('Your Boei widget is now live on your website. Start capturing leads!', 'boei-help') . '</div>';
            echo '</div>';
        } else {
            // Regular connected message
            echo '<div class="notice notice-success" style="margin-bottom: 20px; padding: 12px 16px;">';
            echo '<strong>' . esc_html__('Connected!', 'boei-help') . '</strong> ' . esc_html__('Your Boei widget is active on this site.', 'boei-help');
            echo '</div>';
        }

        // Dashboard cards
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">';

        $cards = array(
            array('url' => 'https://app.boei.help/inbox', 'icon' => '<svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#713eec" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>', 'title' => __('Inbox', 'boei-help'), 'desc' => __('All messages', 'boei-help')),
            array('url' => 'https://app.boei.help/crm', 'icon' => '<svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#713eec" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'title' => __('CRM', 'boei-help'), 'desc' => __('Manage leads', 'boei-help')),
            array('url' => 'https://app.boei.help/analytics', 'icon' => '<svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#713eec" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>', 'title' => __('Analytics', 'boei-help'), 'desc' => __('Track performance', 'boei-help')),
            array('url' => 'https://app.boei.help/domains', 'icon' => '<svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#713eec" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'title' => __('Widget', 'boei-help'), 'desc' => __('Customize', 'boei-help')),
        );

        foreach ($cards as $card) {
            echo '<a href="' . esc_url($card['url'] . '?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=dashboard_cards') . '" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">';
            echo '<span class="boei-card" style="display: block; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center; transition: border-color 0.2s; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
            echo '<span style="display: block; margin-bottom: 8px;">' . wp_kses($card['icon'], boei_allowed_svg_tags()) . '</span>';
            echo '<span style="display: block; font-weight: 600; color: #1d2327; font-size: 14px;">' . esc_html($card['title']) . '</span>';
            echo '<span style="display: block; font-size: 12px; color: #646970; margin-top: 4px;">' . esc_html($card['desc']) . '</span>';
            echo '</span>';
            echo '</a>';
        }

        echo '</div>';

        // Review request box
        echo '<div style="background: linear-gradient(135deg, #713eec 0%, #9b6df5 100%); border-radius: 4px; padding: 20px; margin-bottom: 24px; color: #fff;">';
        echo '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">';
        echo '<div>';
        echo '<div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">' . esc_html__('Enjoying Boei? Help us grow!', 'boei-help') . '</div>';
        echo '<div style="font-size: 13px; opacity: 0.9;">' . esc_html__('Your review helps other WordPress users discover Boei.', 'boei-help') . '</div>';
        echo '</div>';
        echo '<a href="https://wordpress.org/support/plugin/boei-help/reviews/#new-post" target="_blank" rel="noopener noreferrer" style="background: #fff; color: #713eec; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; white-space: nowrap;">' . esc_html__('Leave a review', 'boei-help') . '</a>';
        echo '</div>';
        echo '</div>';

        // Settings section
        echo '<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 16px;">';
        echo '<h3 style="margin: 0 0 12px 0; font-size: 14px;">' . esc_html__('Widget Key', 'boei-help') . '</h3>';

        // Display mode (default)
        echo '<div id="boei-key-display" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
        echo '<div style="display: flex; align-items: center; gap: 8px; background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 4px; padding: 8px 12px; font-family: monospace; font-size: 13px;">';
        echo '<svg width="16" height="16" viewBox="0 0 20 20" fill="#00a32a"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.707 6.707l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 111.414-1.414L9 10.586l3.293-3.293a1 1 0 111.414 1.414z"/></svg>';
        echo '<span>' . esc_html(boei_get_key()) . '</span>';
        echo '</div>';
        echo '<button type="button" class="button" onclick="boeiShowEdit()">' . esc_html__('Edit', 'boei-help') . '</button>';
        echo '<button type="button" class="button" onclick="boeiDeactivate()" style="color: #d63638;">' . esc_html__('Deactivate', 'boei-help') . '</button>';
        echo '</div>';

        // Edit mode (hidden by default)
        echo '<div id="boei-key-edit" style="display: none;">';
        echo '<form action="options.php" method="POST" style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">';
        settings_fields('boei_key');
        do_settings_sections('boei_key');
        echo '<input type="text" name="boei_key_option" value="' . esc_attr(boei_get_key()) . '" style="flex: 1; min-width: 250px; max-width: 400px;" />';
        echo '<button type="button" id="boei-test-btn" onclick="boeiTestKey()" class="button">' . esc_html__('Test', 'boei-help') . '</button>';
        submit_button(__('Update', 'boei-help'), 'secondary', 'submit', false);
        echo '<button type="button" class="button" onclick="boeiCancelEdit()">' . esc_html__('Cancel', 'boei-help') . '</button>';
        echo '</form>';
        echo '<span id="boei-test-result" style="display: block; margin-top: 8px;"></span>';
        echo '</div>';

        // Deactivate form (hidden)
        echo '<form id="boei-deactivate-form" action="options.php" method="POST" style="display: none;">';
        settings_fields('boei_key');
        echo '<input type="hidden" name="boei_key_option" value="" />';
        echo '</form>';

        echo '</div>';

    } else {
        // ========== ONBOARDING STATE ==========
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; align-items: stretch;">';

        // Left column - Value prop & features
        echo '<div style="background: linear-gradient(135deg, #713eec 0%, #9b6df5 100%); border-radius: 8px; padding: 32px; color: #fff; display: flex; flex-direction: column;">';
        echo '<h2 style="margin: 0 0 16px 0; font-size: 28px; font-weight: 700; color: #fff; line-height: 1.2;">' . esc_html__('Capture Every Lead. Reply Instantly. Close More Deals.', 'boei-help') . '</h2>';
        echo '<p style="margin: 0 0 24px 0; opacity: 0.9; font-size: 15px; line-height: 1.5;">' . esc_html__('Turn your WordPress site into a lead generation machine with AI-powered chat and 50+ contact channels.', 'boei-help') . '</p>';

        // Feature list
        echo '<div style="display: flex; flex-direction: column; gap: 16px;">';

        $features = array(
            array('icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>', 'text' => __('AI chatbot answers questions 24/7', 'boei-help')),
            array('icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>', 'text' => __('50+ contact channels in one widget', 'boei-help')),
            array('icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>', 'text' => __('Single inbox for all messages', 'boei-help')),
            array('icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'text' => __('Built-in CRM to track leads', 'boei-help')),
        );

        foreach ($features as $feature) {
            echo '<div style="display: flex; align-items: center; gap: 12px;">';
            echo '<div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">' . wp_kses($feature['icon'], boei_allowed_svg_tags()) . '</div>';
            echo '<span style="font-size: 14px;">' . esc_html($feature['text']) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        echo '<div style="margin-top: auto; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 13px; opacity: 0.9;">';
        echo esc_html__('Trusted by 10,000+ businesses worldwide', 'boei-help');
        echo '</div>';

        echo '</div>';

        // Right column - Setup steps
        echo '<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 32px; display: flex; flex-direction: column;">';
        echo '<h3 style="margin: 0 0 24px 0; font-size: 18px;">' . esc_html__('Get started in 2 minutes', 'boei-help') . '</h3>';

        // Step 1
        echo '<div style="display: flex; gap: 16px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e0e0e0;">';
        echo '<div style="background: #713eec; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; flex-shrink: 0;">1</div>';
        echo '<div style="flex: 1;">';
        echo '<div style="font-weight: 600; margin-bottom: 4px;">' . esc_html__('Create your free Boei account', 'boei-help') . '</div>';
        echo '<div style="color: #646970; font-size: 13px; margin-bottom: 12px;">' . esc_html__('Set up your widget and AI chatbot in minutes', 'boei-help') . '</div>';
        echo '<a href="' . esc_url($registerURL) . '" class="button button-primary" target="_blank" rel="noopener noreferrer" style="background: #713eec; border-color: #713eec;">' . esc_html__('Get started free', 'boei-help') . '</a>';
        echo '</div>';
        echo '</div>';

        // Step 2
        echo '<div style="display: flex; gap: 16px;">';
        echo '<div style="background: #713eec; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; flex-shrink: 0;">2</div>';
        echo '<div style="flex: 1;">';
        echo '<div style="font-weight: 600; margin-bottom: 4px;">' . esc_html__('Connect your widget', 'boei-help') . '</div>';
        echo '<div style="color: #646970; font-size: 13px; margin-bottom: 12px;">' . wp_kses(
            sprintf(
                /* translators: %s: link to Boei dashboard */
                __('Paste your widget key from the %s', 'boei-help'),
                '<a href="' . esc_url($installationURL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Boei dashboard', 'boei-help') . '</a>'
            ),
            array('a' => array('href' => true, 'target' => true, 'rel' => true))
        ) . '</div>';
        echo '<form action="options.php" method="POST">';
        settings_fields('boei_key');
        do_settings_sections('boei_key');
        echo '<input type="text" name="boei_key_option" value="' . esc_attr(boei_get_key()) . '" style="width: 100%; margin-bottom: 12px;" placeholder="' . esc_attr__('e.g. 21424816-afa8-4f70-85f0-85ddfbcbcec6', 'boei-help') . '" />';
        echo '<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">';
        submit_button(__('Connect widget', 'boei-help'), 'primary', 'submit', false, array('style' => 'background: #713eec; border-color: #713eec;'));
        echo '<button type="button" id="boei-test-btn" onclick="boeiTestKey()" class="button">' . esc_html__('Test', 'boei-help') . '</button>';
        echo '</div>';
        echo '<span id="boei-test-result" style="display: block; margin-top: 8px;"></span>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        echo '</div>';
    }

    // Footer links (shown for both states)
    echo '<div style="display: flex; gap: 20px; flex-wrap: wrap; color: #646970; font-size: 13px; margin-top: 20px;">';
    echo '<a href="' . esc_url($homepageURL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Help & Support', 'boei-help') . '</a>';
    echo '<a href="' . esc_url($roadmapURL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Roadmap', 'boei-help') . '</a>';
    echo '</div>';

    echo '</div>';
}


/**
 * Return app management URL
 */
function boei_url_manage()
{
    return 'https://app.boei.help?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=settings_page';
}

/**
 * Return Boei homepage
 */
function boei_url_homepage()
{
    return 'https://www.boei.help/?utm_source=wp_plugin&utm_medium=plugin_admin&utm_campaign=footer_support';
}

/**
 * Return Boei logo
 */
function boei_url_logo()
{
    return plugins_url('logo.svg', __FILE__);
}

/**
 * Verify widget key on plugin activation
 */
function boei_activation()
{
    $key = get_option('boei_key_option');
    if (!empty($key)) {
        $is_valid = boei_verify_key($key);
        if (!$is_valid) {
            set_transient('boei_activation_notice', 'invalid_key', 30);
        }
    }
}

register_activation_hook(__FILE__, 'boei_activation');

/**
 * Show admin notice if widget key is not configured
 */
function boei_admin_notices()
{
    // Don't show on Boei settings page
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_boei-help-settings') {
        return;
    }

    // Show notice after activation if key is invalid
    if (get_transient('boei_activation_notice') === 'invalid_key') {
        delete_transient('boei_activation_notice');
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Boei:</strong> ' . esc_html__('Your widget key could not be verified. Please check your settings.', 'boei-help') . ' ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=boei-help-settings')) . '">' . esc_html__('Go to settings', 'boei-help') . '</a></p>';
        echo '</div>';
        return;
    }

    // Show setup notice if widget key is not configured
    if (empty(boei_get_key())) {
        echo '<div class="notice notice-info">';
        echo '<p><strong>Boei</strong> ' . esc_html__('Connect your widget to start capturing leads.', 'boei-help') . ' ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=boei-help-settings')) . '">' . esc_html__('Go to settings', 'boei-help') . '</a></p>';
        echo '</div>';
    }
}

add_action('admin_notices', 'boei_admin_notices');

/**
 * Clean up plugin data on uninstall
 */
function boei_uninstall()
{
    delete_option('boei_key_option');
}

register_uninstall_hook(__FILE__, 'boei_uninstall');

