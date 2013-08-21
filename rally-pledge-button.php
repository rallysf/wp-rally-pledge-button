<?php
/*
 * Plugin Name: Rally Pledge Button
 * Plugin URI: http://wordpress.org/extend/plugins/rally-pledge-button
 * Description: Raise money for causes you care about on rally.org
 * Author: Rally.org
 * Version: 1.0.0
 * License: GPL2+
 * Text Domain: rally-pledge-button
 * Domain Path: /languages/
 */

/*
 * Inspired by wp-bitbucket and simple-short-code-button 
 *  http://wordpress.org/plugins/wp-bitbucket/
 *  http://wordpress.org/plugins/custom-tinymce-shortcode-button/
 */

class RallyPledgeButton {
  /** Version ***************************************************************/
  /**
   * @public string plugin version
   */
  public $version = '1.0.0';
  /**
   * @public string plugin DB version
   */
  public $db_version = '100';

  /** Paths *****************************************************************/
  public $file = '';
  public $basename = '';
  public $plugin_dir = '';
  private static $instance;

  public static function instance() {
    if ( ! isset( self::$instance ) ) {
      self::$instance = new RallyPledgeButton;
      self::$instance->setup_globals();
      self::$instance->setup_actions();
      self::$instance->setup_filters();

    }
    return self::$instance;
  }


  /* Rally specific variables */
  var $page;
  var $errors;

  /**
   * A dummy constructor to prevent from being loaded more than once.
   *
   */
  private function __construct() { /* Do nothing here */ }

  function setup_globals() {
    /** Paths *************************************************************/
    $this->file       = __FILE__;
    $this->basename   = plugin_basename( $this->file );
    $this->plugin_dir = plugin_dir_path( $this->file );
    $this->plugin_url = plugin_dir_url ( $this->file );
    $this->errors = new WP_Error();
  }

  function setup_actions(){

    //localization (nothing to localize yet, so disable it)
    add_action('init', array($this, 'load_plugin_textdomain'));

    //upgrade
    add_action( 'plugins_loaded', array($this, 'upgrade'));

    //shortcode
    add_shortcode( 'rally-pledge', array( $this, 'process_shortcode' ) );
  }

  function setup_filters() {

    if(preg_match("/(post-new|post)\.php/", basename(getenv('SCRIPT_FILENAME')))){
      add_filter('admin_head', array($this, "setup_button_image"));
    }

    // necessary for tiny-mce extensions
    add_filter("mce_external_plugins", array($this, "rpb_register"));
    add_filter("mce_buttons", array($this, "rpb_add_button"));
  }

  function rpb_register($plugin_array) {
    $plugin_array["rally_pledge_button"] = trim(get_bloginfo('url'), "/")."/wp-content/plugins/rally-pledge-button/rpb.js";
    return $plugin_array;
  }

  function rpb_add_button($button) {
    array_push($button, "", "rally_pledge_button");
    return $button;
  }

  function setup_button_image() {

    $image_path = get_bloginfo('url').'/wp-content/plugins/rally-pledge-button/image.png';
    echo '<script type="text/javascript">'."\n";
    echo "var sc_img = '$image_path'\n";
    echo '</script>';

  }

  public function load_plugin_textdomain(){
    load_plugin_textdomain($this->basename, FALSE, $this->plugin_dir.'/languages/');
  }

  function upgrade(){
    global $wpdb;

    $version_db_key = $this->basename.'-db-version';

    $current_version = get_option($version_db_key);


    if ($current_version==$this->db_version) return false;

    update_option($version_db_key, $this->db_version );
  }

  function process_shortcode( $atts, $content="" ) {

    $default = array(
      "page" => "https://rally.org/buzkashiboys"
    );

    $args = shortcode_atts($default, $atts);

    //all args are required
    foreach ($args as $arg=>$value){
      if (!$value) return false;
    }

    $this->page = $args["page"];

    //check page is found
    $embed_code = self::get_page($this->page);

    return $embed_code;
  }

  /**
   * Check if the input URL returns something or is a redirection.
   * @param type $input_url
   * @return null|boolean
   */
  function get_page($url){

    $pledge_embed_code_url = "https://www.rally.org/widgets/pledge_embed_code?url=" . urlencode($url);

    $ch = curl_init();

    $options = array(
      CURLOPT_URL            => $pledge_embed_code_url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_ENCODING       => "",
      CURLOPT_AUTOREFERER    => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT        => 120,
      CURLOPT_MAXREDIRS      => 10
    );

    curl_setopt_array( $ch, $options );
    $content = curl_exec($ch); 
    $info = curl_getinfo($ch);

    curl_close($ch);

    $valid_http_codes = array(200);
    if(!in_array($info['http_code'], $valid_http_codes)) return false;

    return apply_filters('rally_pledge_button_get_page', $content);
  }

}

/**
 * The main function responsible for returning the one Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 */
function rally_pledge_button() {
  return RallyPledgebutton::instance();
}
rally_pledge_button();
?>
