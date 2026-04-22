<?php
/**
 * Plugin Name: Pinnacle Chat Loader
 * Description: Injects the Pinnacle floating chat widget (Fer bot) on every front-end page.
 * Version: 1.0.0
 * Author: ALEX
 *
 * Place under wp-content/mu-plugins/ so it auto-activates and cannot be deactivated
 * from the Plugins UI. Deploys via GitHub Actions (see hostinger/mu-plugins/ path).
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;
    // Don't load on the form itself — user is already engaged with structured flow
    if (is_page('get-my-offer')) return;

    $ver = '1.0.' . @filemtime(__DIR__ . '/../agents/pinnacle_chat/pinnacle_chat.js');

    wp_enqueue_style(
        'pinnacle-chat',
        '/agents/pinnacle_chat/pinnacle_chat.css',
        [],
        $ver
    );
    wp_enqueue_script(
        'pinnacle-chat',
        '/agents/pinnacle_chat/pinnacle_chat.js',
        [],
        $ver,
        true /* in_footer */
    );
}, 20);
