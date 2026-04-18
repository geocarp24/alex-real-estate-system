<?php
/**
 * Plugin Name: Pinnacle Tracking
 * Description: Injects Meta Pixel and Microsoft Clarity into wp_head. Reads IDs from constants.
 * Version: 1.0.0
 * Author: ALEX (Pinnacle Holdings)
 *
 * Set credentials via wp-config.php (preferred) or environment:
 *   define('PINNACLE_META_PIXEL_ID', '1234567890');
 *   define('PINNACLE_CLARITY_ID',    'abcdefghij');
 *
 * If a constant is empty, that snippet is skipped silently.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {

    $pixel = defined('PINNACLE_META_PIXEL_ID') ? trim((string) PINNACLE_META_PIXEL_ID) : '';
    if ($pixel !== '') {
        ?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js($pixel); ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel); ?>&ev=PageView&noscript=1"/></noscript>
<!-- /Meta Pixel -->
        <?php
    }

    $clarity = defined('PINNACLE_CLARITY_ID') ? trim((string) PINNACLE_CLARITY_ID) : '';
    if ($clarity !== '') {
        ?>
<!-- Microsoft Clarity -->
<script>
(function(c,l,a,r,i,t,y){
c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
})(window,document,"clarity","script","<?php echo esc_js($clarity); ?>");
</script>
<!-- /Microsoft Clarity -->
        <?php
    }
}, 1);

add_action('wpcf7_mail_sent', function ($contact_form) {
    $pixel = defined('PINNACLE_META_PIXEL_ID') ? trim((string) PINNACLE_META_PIXEL_ID) : '';
    if ($pixel === '') return;
    ?>
<script>if (typeof fbq !== 'undefined') { fbq('track', 'Lead'); }</script>
    <?php
}, 10, 1);
