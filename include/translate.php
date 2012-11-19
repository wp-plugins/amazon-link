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
         $this->translate_url = "https://api.datamarket.azure.com/Data.ashx/Bing/MicrosoftTranslator/v1/Translate";
      }

      /*
       * Must be called by the client in its init function.
       */
      function init($parent) {

//         $script = plugins_url("amazon-link-search.js", __FILE__);
//         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
         add_action('wp_ajax_amazon-link-translate', array($this, 'do_translate'));      // Handle ajax translate requests

           $this->alink    = $parent;
           add_filter('translate', array($this, 'translate'), 10, 3);
      }
/*****************************************************************************************/
      /// AJAX Call Handlers
/*****************************************************************************************/

      function do_translate() {
         $opts = $_POST;
         $translation = $this->translate($opts['Text'], $opts['From'], $opts['To']);
         print json_encode($translation);
         exit();
      }

/*****************************************************************************************/
      /// API Functions
/*****************************************************************************************/

      function set_id ($id) {
         $this->id = $id;
      }

      function translate ($text, $from, $to) {
         $parameters['From'] = "'".$from."'";
         $parameters['To'] = "'".$to."'";
         $parameters['Text'] = "'".$text."'";
         $parameters['$format']= 'Raw';
         $url = $this->generate_url($this->translate_url, $parameters);
         $headers = array( 'Authorization' => 'Basic ' . base64_encode($this->id. ':' . $this->id));
         $result = wp_remote_get( $url, array( 'headers'=> $headers));

         if ($result instanceof WP_Error )
            return new WP_Error('access_error',__('Could not access Bing API: '.$url,'amazon-link'));

         // Save file to media library
         $content = preg_replace('!<.*>(.*)<.*>!', '\1',$result['body']);
//         echo "<PRE>"; print_r($content); echo" </PRE>";
         return $content;
      }

      function debug_resp ( $response, $args, $url )
      {
         echo "<PRE>DEBUG:$url :::"; print_r($args); echo "</PRE>";
         return $reponse;
      }

      // Text=%27hello%27&To=%27fr%27&From=%27en%27&$top=100
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