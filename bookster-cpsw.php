<?php
/*
 * Plugin Name: Bookster Search Form
 * Description: Add a Bookster Property Search Form to your WordPress website
 * Version: v14
 * Author: Bookster
 * Author URI: https://www.booksterhq.com/
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class bkcpsw_Bookster_CPSW
{
  /**
	 * Static property to hold our singleton instance
	 *
	 */
	static $instance = false;

  const option_name = 'bookster_cpsw_sub';
  const option_sub_name = 'bookster_cpsw_sub_id';
  const version = '1.0';

  private function __construct() {
    # on activation
    register_activation_hook( __FILE__, array($this,'activate'));
      
    # uninstall
    register_uninstall_hook(__FILE__, array($this,'uninstall'));

    add_action( 'plugins_loaded', array($this,'bookster_cpsw_pluginLoaded'), 10);

    if(is_admin()) {
      add_action( 'admin_init', array( $this, 'adminInit' ), 10 );
      add_action('admin_menu', array($this, 'adminMenu'), 9, 0);
      add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));
    } else {
      add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    }
  }

  /**
   * Init admin menus, interfaces, templates, and hooks
   */
  public function adminInit()
  {
    register_setting('bookster_cpsw', self::option_name);

    add_settings_section(
      'bookster_cpsw_sub_section',
      'Bookster Subscription',
      array($this,'settingsSection'),
      'bookster-cpsw'
    );

    add_settings_field(
      self::option_sub_name,
      'Subscription ID',
      array($this, 'settingsField'),
      'bookster-cpsw',
      'bookster_cpsw_sub_section',
      array(
        'label_for' => self::option_sub_name,
      )
    );
  }

  /**
   * Register shortcodes
   */
  public function bookster_cpsw_pluginLoaded()
  {
    add_shortcode('bookster_search_form', array($this,'runShortcode'));
  }

  /**
   * Run plugin activation tasks
   */
  public function activate()
  {
    update_option(self::option_name, array(self::option_sub_name => ''));
  }

  /**
   * Run plugin uninstall tasks
   */
  public function uninstall()
  {
    delete_option(self::option_name);
  }

  /**
   * Callback function for the widget shortcode.
   */
  public function runShortcode($atts, $content = null, $code)
  {
    //test sub id = 21265

    if ( is_feed() ) {
      return '[bookster_search_form]';
    }
  
    $output = '';

    if ( 'bookster_search_form' === $code ) {
      $atts = shortcode_atts(
        array(
          'sub_id' => '',
        ),
        $atts, 'bookster_search_form'
      );
  
      $id = trim( $atts['sub_id'] );
  
      if($id != '') {
        $url = 'https://booking.booksterhq.com/system/booking/date/lookup/1985/'.$id;
        $response = wp_remote_get($url);
        $responseBody = wp_remote_retrieve_body( $response );
        $apiData = json_decode($responseBody,true);

        // Max Party Size
        $maxPartySize=20;
        if (!empty($apiData['party']['max']))
          $maxPartySize = $apiData['party']['max'];

        if (!empty($responseBody))
        {
          wp_register_script( 'dummy-inliner', '' );
          wp_enqueue_script( 'dummy-inliner' );
          wp_add_inline_script( 'dummy-inliner', 'const apiData = '.$responseBody.';' );
        }

        //
        // Earliest Arrival Date
        //
        $minArrivalDate = new DateTime('now');
        $minArrivalDate->modify('+1 day');
        
        if (!empty($apiData['dates']))
        {
          $minArrivalDate = new DateTime(array_key_first($apiData['dates']));
        }

        $strMinArrivalDate = $minArrivalDate->format('Y-m-d');

        //
        // Departure Date
        //
        $minDepartureDate = $minArrivalDate;
        $minDepartureDate->modify('+1 days');

        $output .= '<div class="bookster-cpsw-form-container">';
        $output .= '<form id="bookster-cpsw-form" action="">';

        // Arrival Date Picker
        $output .= '<div class="bookster-cpsw-form-group">';
        $output .= '<label for="bookster-cpsw-check-in">Check-in</label>';
        $output .= '<duet-date-picker min="'.$strMinArrivalDate.'" value="'.$strMinArrivalDate.'" identifier="bookster-cpsw-check-in" name="bookster-cpsw-check-in" class="js-bookster-cpsw-date js-bookster-cpsw-check-in"></duet-date-picker>';
        $output .= '</div>';
        
        // Departure Date Picker
        $output .= '<div class="bookster-cpsw-form-group">';
        $output .= '<label for="bookster-cpsw-check-out">Check-out</label>';
        $output .= '<duet-date-picker min="'.$minDepartureDate->format('Y-m-d').'" value="'.$minDepartureDate->format('Y-m-d').'" identifier="bookster-cpsw-check-out" name="bookster-cpsw-check-out" class="js-bookster-cpsw-date js-bookster-cpsw-check-out"></duet-date-picker>';      
        $output .= '</div>';
        $output .= '<div class="bookster-cpsw-form-group">';
        $output .= '<label for="bookster-cpsw-party">Party size</label>';
        $output .= '<select id="bookster-cpsw-party" name="bookster-cpsw-party" class="js-bookster-cpsw-party">';
        $output .= '<option value="--">--</option>';
        for($i=1;$i<=$maxPartySize;$i++) {
          $selected = ($i == 2) ? ' selected ' : '';
          $output .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }
        $output .= '</select>';
        $output .= '</div>';
        $output .= '<input type="hidden" value="'.$id.'" name="bookster-cpsw-subId" class="js-bookster-cpsw-subId" />';
        $output .= '<button type="submit" class="bookster-cpsw-submit js-bookster-cpsw-submit">Search</button>';
        $output .= '</form></div>';
      }
    }
  
    return $output;
  }

  /**
   * Enqueue frontend sttyles and scripts
   */
  public function enqueue()
  {
    wp_enqueue_style('bookster-cpsw-duet-css', plugin_dir_url( __FILE__ ) . 'includes/css/duet.css', array(), self::version, false);
    wp_enqueue_script('bookster-cpsw-duet-js', plugin_dir_url( __FILE__ ) . 'includes/js/bookster-cpsw-duet.js', array(), self::version, true);
    wp_enqueue_style('bookster-cpsw-form-css', plugin_dir_url( __FILE__ ) . 'includes/css/bookster-cpsw.css', array(), self::version, 'all');
    wp_enqueue_script('bookster-cpsw-form-js', plugin_dir_url( __FILE__ ) . 'includes/js/bookster-cpsw.js', array(), self::version, true);
  }

  /**
   * Setup admin menu items
   */
  public function adminMenu()
  {
    add_menu_page('Bookster Search', 'Bookster Search', 'manage_options', 'bookster-cpsw', array($this, 'adminPage'), 'dashicons-search');
  }

  /**
   * Enqueue admin scripts and styles
   */
  public function adminEnqueueScripts($hook)
  {
    if ( false === strpos( $hook, 'bookster-cpsw' )) {
      return;
    }

    wp_enqueue_style('bookster-cpsw-admin-css', plugin_dir_url( __FILE__ ) . 'admin/css/bookster-cpsw-admin.css', array(), self::version, 'all');

    wp_enqueue_script('bookster-cpsw-admin-js', plugin_dir_url( __FILE__ ) . 'admin/js/bookster-cpsw-admin.js', array(), self::version, true);
  }

  /** 
   * Callback function for settings section
   */
  public function settingsSection()
  {
    //do nothing
  }

  /**
   * Callback for settings field
   */
  public function settingsField($args)
  {
    $option = get_option(self::option_name);
    ?>

    <input id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr(self::option_name) ?>[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo esc_attr($option[$args['label_for']]) ?>" />

    <?php 
  }

  /**
   * Admin page for the plugin
   */
  public function adminPage()
  {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $option = get_option(self::option_name);

    // if ( isset( $_GET['settings-updated'] ) ) 
    // {
    //   // add settings saved message with the class of "updated"
    //   add_settings_error( 'bookster_cpsw_messages', 'bookster_cpsw_message', 'Subscription ID Saved', 'updated' );
    // }
  
    // show error/update messages
    settings_errors( 'bookster_cpsw_messages' );
   ?>
    <div class="wrap" id="bookster-cpsw-settings">
      <h1 class="wp-heading-inline">Bookster Search Form</h1>

      <hr class="wp-header-end">
      <div class="notice notice-info">
        <h3>Bookster</h3>
        <p>A <a href="https://www.booksterhq.com/">Bookster</a> Subscription is <strong>required</strong>.</p>
        <p>Find your Bookster Subscription ID by logging into Bookster and clicking on Settings. Your web browser's URL will look like <code>https://app.booksterhq.com/subscriptions/<strong>123456789</strong>/edit</code>.</p>
        <?php if($option[self::option_sub_name] == ''): ?><p>Add your Subscription ID and a shortcode will appear below.</p><?php endif; ?>
      </div>
      <p>Add the Bookster Search form to your WordPress posts and pages using the Shortcode. <a href="https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/">Learn how to use WordPress shortcodes</a>.</p> 
      <?php if($option[self::option_sub_name] != ''): 
        $shortcode = '[bookster_search_form sub_id="'.$option[self::option_sub_name].'"]';
      ?>

      <div class="inside shortcode-container">
        <p class="description">
          <label for="bookster-cpsw-shortcode">Copy this Shortcode and paste it into your post, page, or text widget content:</label>
          <span class="shortcode wp-ui-highlight">
            <input type="text" id="bookster-cpsw-shortcode" readonly="readonly" class="large-text code" value="<?php echo esc_attr($shortcode) ?>" />
          </span>
        </p>
      </div>
      <?php endif; ?>
      <br /><br />
      <form action="options.php" method="post">
        <?php
        settings_fields( 'bookster_cpsw' );
        do_settings_sections( 'bookster-cpsw' );
        submit_button( 'Save' );
        ?>
      </form>
    <?php
  }

  /**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return Bookster_CPSW
	 */

   public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}
}

// Instantiate our class
$Bookster_CPSW = bkcpsw_Bookster_CPSW::getInstance();