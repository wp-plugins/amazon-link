<?php
/*****************************************************************************************/

/*
 * Simple BING Translate Class
 *
 */

if (!class_exists('bing_translate')) {
   class bing_translate {

      function bing_translate() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->translate_url = "http://api.microsofttranslator.com/v2/Http.svc/Translate";
      }

      /*
       * Must be called by the client in its init function.
       */
      function init($parent) {

//         $script = plugins_url("amazon-link-search.js", __FILE__);
//         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
//         add_action('wp_ajax_amazon-link-search', array($this, 'performSearch'));      // Handle ajax search requests

           $this->alink    = $parent;
      }
/*****************************************************************************************/
      /// AJAX Call Handlers
/*****************************************************************************************/

      function do_translate() {
         $Opts = $_POST;

         print json_encode($this->parse_results($Items, $Settings));
         exit();
      }
/*****************************************************************************************/
      /// API Functions
/*****************************************************************************************/

      function set_id ($id) {
         $this->id = $id;
      }

      function translate ($text, $from, $to) {
         $parameters['appId'] = $this->id;
         $parameters['from'] = $from;
         $parameters['to'] = $to;
         $parameters['text'] = $text;
         $url = $this->generate_url($this->translate_url, $parameters);

         if( !class_exists( 'WP_Http' ) )
            include_once( ABSPATH . WPINC. '/class-http.php' );
         $request = new WP_Http;
         $result = $request->request( $url );
         if ($result instanceof WP_Error )
            return new WP_Error('access_error',__('Could not access Bing API: '.$url,'amazon-link'));

         // Save file to media library
         $content = $result['body'];
         return $content;
      }



      function generate_url($root, $parameters) {
         $args = array();
         foreach ( $parameters as $arg => $data) {
            $args[] = $arg .'='.urlencode($data);
         }
         $url = $root .'?'. implode('&', $args);
         return $url;
      }

   }
}
?>