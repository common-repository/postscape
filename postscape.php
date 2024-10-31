<?php
/**
 *
 * @package   Postscape
 * @author    Elysian Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0+
 * @copyright Copyright (C) 2017 Elysian Inc.
 *
 * Plugin Name:     Postscape
 * Description:     This plugin connects to Copyscape to check if published posts are original or not. 
 * Author:          Elysian Inc.
 * Author URI:      http://www.elysian.team
 * Version:         1.0
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:     postscape
 */

require_once(plugin_dir_path( __FILE__ ) . 'postscape-admin.php');

add_filter('http_request_timeout', 'pstsc_timeout_extend');
add_filter('the_content', 'pstsc_button_after_content');
add_action('wp_ajax_pstsc_copyscape_query', 'pstsc_copyscape_query');

/**
 * Extend timeout for requests. This is important for Copyscape API to retrieve
 * response.
 *
 * @return {int} new timeout after extension.
 */
function pstsc_timeout_extend($time)
{
  return 50;
}

/**
 * Add a button to query Copyscape at the botton of post content.
 *
 * @return {string} new post content after adding the button.
 */
function pstsc_button_after_content($content) {
  wp_enqueue_style('popup', plugin_dir_url(__FILE__) . 'css/popup.css');

  // Show only for admins and editors.
  if ((is_page() || is_single()) && current_user_can('edit_others_posts')) {
    $copyscape_button =
      '<div class="pstscpopup">'.
        '<button id="pstsc-submit">Copyscape it!</button>'.
        '<div class="pstscpopuptext" id="pstsc-popup">'.
          '<span class="close" id="pstsc-close-popup">x</span>'.
          '<span id="pstsc-popup-text"></span>'.
        '</div>'.
      '</div>';
    $fullcontent = $content . $copyscape_button;
    ?>

    <!-- Javascript to handle copyscape button click  -->
    <script language="javascript" type="text/javascript">
      jQuery(document).ready(function() {
        jQuery('#pstsc-submit').click(function() {
          jQuery("#pstsc-popup").addClass('show');
          var data = {
            action: 'pstsc_copyscape_query',
            security: '<?php echo wp_create_nonce('pstsc_copyscape_query'); ?>',
            text: '<?php echo sanitize_text_field($content); ?>',
          };
          jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
            var xml = jQuery.parseXML(response);
            var viewUrl = jQuery(xml).find("allviewurl").text();
            var resultCount = jQuery(xml).find("count").text();
            var percentMatched = jQuery(xml).find("allpercentmatched").text();
            var wordsMatched = jQuery(xml).find("allwordsmatched").text();
            var error = jQuery(xml).find("error").text();

            // Show error message if found, otherwise show results.
            if (error) {
              jQuery('#pstsc-popup-text').html(
                'Error: ' + error + '<br>');
            } else {
              jQuery('#pstsc-popup-text').html(
                'Found ' + resultCount + ' results<br>');
              jQuery('#pstsc-popup-text').append(
                'Words matched:   ' + wordsMatched + '<br>');
              jQuery('#pstsc-popup-text').append(
                'Matching percent:   ' + percentMatched + '%<br>');
              jQuery('#pstsc-popup-text').append(
                '<a href="' + viewUrl + '" target="_blank">Click to view all results</a><br>');
            }
          });

          jQuery('#pstsc-popup-text').html('Processing ..');
        });

        // Hide popup when clicking close button
        jQuery('#pstsc-close-popup').click(function() {
          jQuery('#pstsc-popup').removeClass('show');
        });
      });
    </script>
    <?php
  } else {
    $fullcontent = $content;
  }

  return $fullcontent;
}

/**
 * Query Copyscape API to get post info.
 *
 * @return {xml} Response from Copyscape.
 */
function pstsc_copyscape_query() {
  check_ajax_referer('pstsc_copyscape_query', 'security');
  $response = wp_remote_post(
    "http://www.copyscape.com/api/",
    array(
      'body' => array(
        'u' => get_option('pstsc_username'),
        'k' => get_option('pstsc_apikey'),
        'o' => 'csearch',
        'c' => '5',
        'e' => 'UTF-8',
        't' => sanitize_text_field($_POST['text']),
      )
    )
  );

  echo $response['body'];
  die();
}
