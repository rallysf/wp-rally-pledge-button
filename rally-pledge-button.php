<?php
/*
 * Plugin Name: Rally Pledge Button
 * Plugin URI: http://wordpress.org/extend/plugins/rally-pledge-button
 * Description: Raise money for causes you care about on rally.org
 * Author: Rally.org
 * Version: 0.0.1
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
  public $version = '0.2.1';
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
      self::$instance->includes();
      self::$instance->setup_actions();
      self::$instance->setup_filters();

    }
    return self::$instance;
  }


  /* Rally specific variables */
  var $page;
  var $cover_id;

  var $input_doc;

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

  function includes(){

    if (!class_exists('phpQuery')) {
      require($this->plugin_dir . '_inc/lib/phpQuery/phpQuery.php');
    }
  }

  function setup_actions(){

    //localization (nothing to localize yet, so disable it)
    add_action('init', array($this, 'load_plugin_textdomain'));
    //upgrade
    add_action( 'plugins_loaded', array($this, 'upgrade'));

    //register scripts & styles
    add_action('init', array($this, 'register_scripts_styles'));

    //shortcode
    add_shortcode( 'rally-pledge', array( $this, 'process_shortcode' ) );
  }

  function setup_filters() {

    if(preg_match("/(post-new|post)\.php/", basename(getenv('SCRIPT_FILENAME')))){
      add_filter('admin_head', array($this, "setup_button_image"));
    }

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

  function register_scripts_styles(){
    wp_register_style( $this->basename.'-newsfeed', $this->plugin_url . '_inc/css/newsfeed.css',false,$this->version);
  }

  function process_shortcode( $atts, $content="" ) {

    $url = trailingslashit('http://bitbucket.org');

    $default = array(
      "page" => "https://rally.org/buzkashiboys"
    );

    $args = shortcode_atts($default,$atts);

    //all args are required
    foreach ($args as $arg=>$value){
      if (!$value) return false;
    }

    $this->page = $args["page"];

    //check page is found
    $markup = self::get_page($this->page);

    if (!$markup) {
      return "<a href='http://rally.org?redirect_from_invalid_widget=".urlencode($this->page)."'>rally.org</a>";
    }
    $input_doc = phpQuery::newDocumentHTML($markup);

    if($input_doc){
      $this->input_doc = $input_doc;
      wp_enqueue_style( $this->basename.'-newsfeed' );
    }

    return self::get_block();
  }

  /**
   * Check if the input URL returns something or is a redirection.
   * @param type $input_url
   * @return null|boolean
   */
  function get_page($url,$base_url=false){

    $ch = curl_init();

    $options = array(
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_ENCODING       => "",
      CURLOPT_AUTOREFERER    => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT        => 120,
      CURLOPT_MAXREDIRS      => 10,
      CURLOPT_SSL_VERIFYPEER =>false
    );

    curl_setopt_array( $ch, $options );
    $content = curl_exec($ch); 
    $info = curl_getinfo($ch);

    curl_close($ch);

    $valid_http_codes = array(200,301);
    if(!in_array($info['http_code'],$valid_http_codes)) return false;

    if($info['http_code']==301){
      $content = self::get_page($info['redirect_url'],$url);
    }else{
      //
    }

    return apply_filters('wp_bitbucket_get_page',$content,$url,$base_url);
  }

  function get_block(){

    if($this->input_doc){
      phpQuery::selectDocument($this->input_doc);

      $this->cover_id = pq("#feed-information")->attr("data-cover-id");


      $block = "<div data-content-url='https://rally.org/covers/$this->cover_id/widgets/pledge' id='rally-pledge-button'></div>";
      $block .= "<script src='https://rally.org/assets/widgets/pledge.js' type='text/javascript'></script>";
    }

    if(!$block){
      $this->errors->add( 'block_not_found', sprintf(__('We were unable to fetch content from the Bitbucket project %s.  Please visit the %s instead !',$this->basename),'<strong>'.ucfirst($this->project).'</strong>','<a href="'.$this->project_url.'" target="_blank">'.__('original page',$this->basename).'</a>'));
    }

    $errors_block=self::get_errors();

    return apply_filters('wp_bitbucket_get_block',$errors_block.$block,$id);

  }

  function get_errors($code=false){
    if(!$code) {
      $codes = $this->errors->get_error_codes();
    }else{
      $codes = (array)$code;
    }
    if(!$codes) return false;

    foreach((array)$codes as $error_code){
      $messages = $this->errors->get_error_messages($code);
      if(!$messages) return false;

      $block='<div id="wp-bitbucket-notices" class="error">';
      foreach((array)$messages as $message){
        $block.='<p style="background-color:#ffebe8;border-color:#c00;margin: 0 0 16px 8px;padding: 12px;">'.$message.'</p>';
      }

      $block.='</div>';
    }
    return $block;

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
