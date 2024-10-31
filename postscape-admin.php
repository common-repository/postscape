<?php
/**
 * Settings page located under the Settings Menu.
 *
 * @package     Postscape
 * @subpackage  Postscape Admin
 * @author      Elysian Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0+
 * @copyright   Copyright (C) 2017, Elysian Inc.
 */


/*
 * --------------------------------
 * Admin Settings Section
 * --------------------------------
 */

add_action('admin_menu', 'pstsc_add_pages');

/**
 * Add "Copyscape Plugin" page under Settings.
 */
function pstsc_add_pages() {
  add_options_page(__('Postscape','menu-pstsc'), __('Postscape','menu-pstsc'), 'manage_options', 'options-postscape', 'pstsc_settings_page');
}

/**
 * Display page content for the Copyscape Plugin settings page.
 */
function pstsc_settings_page() {

  if (!current_user_can('manage_options'))
  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  // Update plugin fields if "Save Changes" is clicked.
  if(isset($_POST['pstsc_submit']) && $_POST['pstsc_submit'] == 'Y') {
    check_admin_referer('pstsc_update_options');
    $username = sanitize_text_field($_POST['pstsc-username']);
    $apikey = sanitize_text_field($_POST['pstsc-apikey']);
    $threshold = sanitize_text_field($_POST['pstsc-threshold']);

    update_option('pstsc_username', $username);
    update_option('pstsc_apikey', $apikey);
    update_option('pstsc_threshold', $threshold);
    ?>
    <div class="updated"><p><?php _e('Settings saved.', 'menu-pstsc'); ?></p></div>
    <?php
  }

  $copyscape_username = get_option('pstsc_username');
  $copyscape_apikey = get_option('pstsc_apikey');
  $copyscape_threshold = get_option('pstsc_threshold');
  if (empty($copyscape_threshold)) {
    $copyscape_threshold = '80%';
  }

  $balance_response = wp_remote_get(
    'http://www.copyscape.com/api/?u='.$copyscape_username.'&k='.$copyscape_apikey.'&o=balance');
  $balance_xml = simplexml_load_string($balance_response['body']);
  $copyscape_remcredit = $balance_xml->value;
  $copyscape_remsearch = $balance_xml->total;
  $copyscape_remsearchtoday = $balance_xml->today;

  echo '<div class="wrap">';
  echo "<h2>" . __( 'Postscape Settings', 'menu-pstsc') . "</h2>";
  ?>

  <form name="form1" method="post" action="">
    <input type="hidden" name="pstsc_submit" value="Y">
    <table class="form-table">
      <tr>
      <th scope="row"><label for="pstsc-username"><?php _e("Copyscape Username:", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-username" type="text" id="pstsc-username" value="<?php esc_attr_e($copyscape_username); ?>" class="regular-text" placeholder="Copyscape Username"/></td>
      </tr>
      <tr>
      <tr>
      <th scope="row"><label for="pstsc-apikey"><?php _e("Copyscape Api-Key:", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-apikey" type="text" id="pstsc-apikey" value="<?php esc_attr_e($copyscape_apikey); ?>" class="regular-text" placeholder="Copyscape API-Key"/></td>
      </tr>
      <tr>
      <th scope="row"><label for="pstsc-threshold"><?php _e("Originality Threshold:", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-threshold" type="text" id="pstsc-threshold" aria-describedby="pstsc-threshold-description" value="<?php esc_attr_e($copyscape_threshold); ?>" class="regular-text" placeholder="Maximum allowed similarity .. ex: 80%"/>
      <p class="description" id="pstsc-threshold-description">Maximum allowance for similarity percantage obtained Copyscape. Any post above this percantage will not be published.</p></td>
      </tr>
      <tr>
      <th scope="row"><label for="pstsc-remcredit"><?php _e("Remaining Credit:", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-remcredit" type="text" id="pstsc-remcredit" aria-describedby="pstsc-remcredit-description" value="$<?php esc_attr_e($copyscape_remcredit); ?>" class="regular-text" readonly/>
      <p class="description" id="pstsc-remcredit-description">Monetary value of your remaining credit in dollars.</p></td>
      </tr>
      <tr>
      <th scope="row"><label for="pstsc-remsearch"><?php _e("Total queries remaining", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-remsearch" type="text" id="pstsc-remsearch" aria-describedby="pstsc-remsearch-description" value="<?php esc_attr_e($copyscape_remsearch); ?> queries" class="regular-text" readonly/>
      <p class="description" id="pstsc-remsearch-description">Total number of search credits remaining.</p></td>
      </tr>
      <tr>
      <th scope="row"><label for="pstsc-remsearchtoday"><?php _e("Queries remaining for today:", 'menu-pstsc' ); ?></label></th>
      <td><input name="pstsc-remsearchtoday" type="text" id="pstsc-remsearchtoday" aria-describedby="pstsc-remsearchtoday-description" value="<?php esc_attr_e($copyscape_remsearchtoday); ?> queries" class="regular-text" readonly/>
      <p class="description" id="pstsc-remsearchtoday-description">Number of Internet searches remaining today.</p></td>
      </tr>
    </table>

    <p class="submit">
      <?php wp_nonce_field('pstsc_update_options'); ?>
      <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
    </p>

  </form></div>
<?php
}


/*
 * --------------------------------
 * Admin Add/Edit Post Section
 * --------------------------------
 */

add_action('admin_enqueue_scripts-post.php', 'pstsc_load_jquery_js');
add_action('admin_enqueue_scripts-post-new.php', 'pstsc_load_jquery_js');
add_action('post_submitbox_misc_actions', 'pstsc_button_post_page');
add_action('admin_head-post.php','pstsc_copyscape_before_publish');
add_action('admin_head-post-new.php','pstsc_copyscape_before_publish');
add_action('wp_ajax_pstsc_pre_submit_validation', 'pstsc_pre_submit_validation');

/**
 * Load jQuery script.
 */
function pstsc_load_jquery_js(){
  global $post;
  if ($post->post_type == 'post') {
    wp_enqueue_script('jquery');
  }
}

/**
 * Add "Copyscape post" button above "Publish" in admin post page.
 */
function pstsc_button_post_page() {
  $html  = '<div id="major-publishing-actions" style="overflow:hidden">';
  $html .= '<div id="publishing-action">';
  $html .= '<span id="pstsc-spinner" class="spinner"></span>';
  $html .= '<input type="submit" tabindex="5" value="Copyscape post!" class="preview button-primary" id="pstsc-copyscape-query" name="pstsc-copyscape-query">';
  $html .= '</div>';
  $html .= '</div>';

  ?>
  <div id="pstsc-notice" class="warning notice" style="display: none;"></div>
  <script language="javascript" type="text/javascript">
    jQuery(document).ready(function() {
      jQuery('#pstsc-copyscape-query').click(function() {
        var content = '';
        if (jQuery('#wp-' + wpActiveEditor + '-wrap').hasClass('tmce-active') 
            && tinyMCE.get(wpActiveEditor)) {
          content = tinyMCE.get(wpActiveEditor).getContent();
        } else {
          content = jQuery('#' + wpActiveEditor).val();
        }
        
        var data = {
          action: 'pstsc_copyscape_query',
          security: '<?php echo wp_create_nonce('pstsc_copyscape_query'); ?>',
          text: content
        };
        jQuery.post(ajaxurl, data, function(response) {
          var xml = jQuery.parseXML(response);
          var viewUrl = jQuery(xml).find("allviewurl").text();
          var resultCount = jQuery(xml).find("count").text();
          var percentMatched = jQuery(xml).find("allpercentmatched").text();
          var error = jQuery(xml).find("error").text();
          
          jQuery('#pstsc-notice').show();
          if (error) {
            jQuery('#pstsc-notice').html('Error: ' + error);
          } else {
            jQuery('#pstsc-notice').html('<p>' + percentMatched + '% of your content is not original. Non-original content in your post should not exceed <?php esc_attr_e(get_option('pstsc_threshold')); ?> to be approved. <a href="' + viewUrl + '" target="_blank">View details</a>');
          }

          jQuery('#pstsc-spinner').removeClass('is-active');
          jQuery('html,body').scrollTop(0);
        });
        jQuery('#pstsc-spinner').addClass('is-active');
        return false;
      });
    });
  </script>
  <?php

  echo $html;
}

/**
 * Handle publish post button click. If the published post has similarity
 * percentage above certain threshold, the post is not published and an error
 * message appears.
 */
function pstsc_copyscape_before_publish(){
  global $post;
  if (is_admin() && $post->post_type == 'post'){
    ?>
    <div id="pstsc-notice" class="error notice" style="display: none;"></div>
    <!-- Javascript to handle publish button click  -->
    <script language="javascript" type="text/javascript">
      jQuery(document).ready(function() {
        jQuery('#publish').click(function() {
          if(jQuery(this).data("valid")) {
            return true;
          }
          // Validate post data before submission.
          var form_data = jQuery('#post').serializeArray();
          var data = {
            action: 'pstsc_pre_submit_validation',
            security: '<?php echo wp_create_nonce('pre_publish_validation'); ?>',
            form_data: jQuery.param(form_data),
          };
          jQuery.post(ajaxurl, data, function(response) {
            var xml = jQuery.parseXML(response);
            var viewUrl = jQuery(xml).find("allviewurl").text();
            var resultCount = jQuery(xml).find("count").text();
            var percentMatched = jQuery(xml).find("allpercentmatched").text();

            var canOverride =
                ('<?php echo current_user_can('edit_others_posts') ?>' == '1');

            // Publish post if matching percantage does not exceed our limit OR
            // current user is admin/editor.
            if (canOverride || !percentMatched || (parseInt(percentMatched) <= parseInt('<?php esc_attr_e(get_option('pstsc_threshold')); ?>'))) {
              jQuery("#post").data("valid", true).submit();
            } else {  // Otherwise, stop publishing and show error message.
              jQuery('#pstsc-notice').show();
              jQuery('#pstsc-notice').html('<p>' + percentMatched + '% of your content is not original. Non-original content in your post should not exceed <?php esc_attr_e(get_option('pstsc_threshold')); ?> to be approved. <a href="' + viewUrl + '" target="_blank">View details</a>');
              jQuery("#post").data("valid", false);
            }

            jQuery(submitpost).find('#major-publishing-actions .spinner')
              .not('#pstsc-spinner').removeClass('is-active');
            jQuery('html,body').scrollTop(0);
            jQuery('#publish').removeClass('button-primary-disabled');
            jQuery('#save-post').removeClass('button-disabled');
          });
          jQuery(submitpost).find('#major-publishing-actions .spinner')
              .not('#pstsc-spinner').addClass('is-active');
          return false;
        });
      });
    </script>
    <?php
  }
}

/**
 * Query Copyscape API to validate post content before submission.
 *
 * @return {xml} Response from Copyscape, or an empty xml if we should override.
 */
function pstsc_pre_submit_validation() {
  check_ajax_referer('pre_publish_validation', 'security');
  parse_str($_POST['form_data'], $vars);

  // Only check if a new post is (published, updated, scheduled, sent for review).
  if ($vars['post_status'] == 'publish' || 
    (isset($vars['original_publish'] ) && 
      in_array($vars['original_publish'], array('Publish', 'Schedule', 'Update', 'Submit for Review')))) {

    $response = wp_remote_post(
      "http://www.copyscape.com/api/",
      array(
        'body' => array(
          'u' => get_option('pstsc_username'),
          'k' => get_option('pstsc_apikey'),
          'o' => 'csearch',
          'c' => '5',
          'e' => 'UTF-8',
          't' => sanitize_text_field($vars['content']),
        )
      )
    );

    echo $response['body'];
    die();
  }

  echo '<postscape></postscape>';
  die();
}
