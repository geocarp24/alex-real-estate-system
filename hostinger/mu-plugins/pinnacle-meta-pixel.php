<?php
/**
 * Plugin Name: Pinnacle Meta Pixel
 * Description: Injects the Meta (Facebook) Pixel base code on every front-end page,
 *              plus standard custom events: Lead (form submit), Contact (phone click),
 *              Schedule (calendar/booking click). Pixel ID: 2588649491529264.
 * Version: 1.0.0
 * Author: ALEX
 *
 * Server-side Conversions API (CAPI) is wired separately via the form bridge —
 * see agents/pinnacle_form/ for the lead-event POST to graph.facebook.com.
 */

if (!defined('ABSPATH')) exit;

define('PINNACLE_PIXEL_ID', '2588649491529264');

add_action('wp_head', function () {
    if (is_admin()) return;
    $pixel_id = PINNACLE_PIXEL_ID;
    ?>
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js($pixel_id); ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1"/></noscript>
<!-- End Meta Pixel Code -->
    <?php
}, 1);

// Custom event hooks injected on the front-end.
// Lead    — fired on Pinnacle form submit (get-my-offer page) via JS event
// Contact — fired on tel: click anywhere on site
// Schedule — fired on calendar/booking link click
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
<script>
(function () {
  if (typeof fbq !== 'function') return;

  // Phone click → Contact event.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="tel:"]');
    if (a) fbq('track', 'Contact', { phone: a.getAttribute('href').replace('tel:', '') });
  }, { passive: true });

  // Booking/calendar click → Schedule event.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href*="calendly.com"], a[href*="cal.com"], a[href*="/book"]');
    if (a) fbq('track', 'Schedule');
  }, { passive: true });

  // Form bridge: Pinnacle webform fires `pinnacle:lead-submitted` on success.
  document.addEventListener('pinnacle:lead-submitted', function (e) {
    var d = (e && e.detail) || {};
    fbq('track', 'Lead', {
      content_name: d.intent || 'sell-my-house',
      value: d.estimated_value || undefined,
      currency: 'USD',
    });
  });
})();
</script>
    <?php
}, 99);
