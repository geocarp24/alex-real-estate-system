<?php
/**
 * Plugin Name: Pinnacle Email Capture Popup Loader
 * Description: Injects the email-capture popup on all front-end pages after 5s.
 *              Skipped on /get-my-offer/ (user already engaged with form).
 * Version: 1.0.0
 * Author: ALEX
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;
    if (is_page('get-my-offer')) return;

    $ver = '1.0.' . @filemtime(ABSPATH . 'agents/pinnacle_popup/pinnacle_popup.js');

    wp_enqueue_style(
        'pinnacle-popup',
        '/agents/pinnacle_popup/pinnacle_popup.css',
        [],
        $ver
    );
    wp_enqueue_script(
        'pinnacle-popup',
        '/agents/pinnacle_popup/pinnacle_popup.js',
        [],
        $ver,
        true
    );
}, 25);
