<?php

/*
Plugin Name: Amazon Link Extra - Redirect
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link/
Description: Adds the ability to redirect to any Amazon Link product using a URL of the format www.mydomain.com/go/&lt;ASIN>/&lt;LINK TYPE S,R or A>/&lt;Domain ca,cn,de, etc.>/?args
Version: 1.0
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
*/

/*
Copyright 2012-2013 Paul Stuttard

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


/*
 * The Redirect action function that is called when the Amazon Link plugin is Initialised
 */
function alx_redirect($settings, $al) {

   $type_map = array ( 'A' => 'product', 'S' => 'search', 'R' => 'review');

   $uri = $_SERVER['REQUEST_URI'];
   $match = preg_match( '!^/'.$settings['redirect_word'].'[/?](([0-9a-z]*)[/?])?((A|S|R)[/?])?((ca|cn|de|fr|es|jp|uk|us)[/?])?!', $uri, $args);
   if ( $match ) {
      $arg_position = strpos($uri,'?');
      if ($arg_position > 0) $parameters = substr($uri,$arg_position+1);
      $home_cc = $settings['default_cc'];

      $type = !empty($args[4]) ? $args[4] : 'A';
      $type = $type_map[$type];

      if (!empty($args[6])) {
         $settings='default_cc='. $args[6].'&localise=0&';
      } else {
         $settings ='';
      }

      $al->parseArgs($settings.'asin='.$args[2].'&'. $parameters);
      $settings   = $al->getSettings();
      $settings['asin'] = $settings['asin'][0];
      $settings['template_content'] = $settings['search_text'];
      $settings['home_cc'] = $home_cc;

      $url = $al->get_url('',$type, $settings['asin'], $al->search->parse_template($settings), $al->get_local_info($settings), $settings);
//echo "<PRE>"; print_r($url); echo "</PRE>";
      wp_redirect($url, '302');
      die();
   }
}

/*
 * Create a redirection style URL
 */
function alx_redirect_url($url, $type, $asin, $search, $local_info, $settings) {

   /* Work out which ASIN to use */
   if (isset($asin[$local_info['cc']])) {

      // User Specified ASIN always use
      $asin = $asin[$local_info['cc']].'/';
   } else if ($settings['search_link'] && ($type == 'product')) {

      // User wants search links for non-local domains
      $type = 'search';
      $asin = '';
   } else {
     
      // Try using the default cc ASIN
      $asin = $asin[$settings['home_cc']].'/';
   }

   // If not localised then force redirect function to send to specific locale.
   if (!$settings['localise']) {
      $cc = $local_info['cc'] . '/';
   }

   // If search links are enabled then pass the search text as an argument
   if ($settings['search_link'] || ($type == 'search')) {
      $search = '?search_text='. $search;
   } else {
      $search = '';
   }

   $type_map = array( /*'product' => 'A/',*/ 'search' => 'S/', 'review' => 'R/');
   $text= get_option('home'). '/'. $settings['redirect_word']. '/'. $asin . $type_map[$type]. $cc . $search;
   return $text;
}

/*
 * Add the Redirect option to the Amazon Link Settings Page
 */
function alx_redirect_options ($options_list) {
   $options_list['redirect_word'] = array ( 'Name' => __('Redirect Word', 'amazon-link'),
                                            'Description' => __('The word that the redirect plugin looks for to indicate that it should try and link to Amazon (www.yourdomain.com/<REDIRECT WORD>/ASIN/?options)', 'amazon-link'),
                                            'Type' => 'text',
                                            'Default' => 'al',
                                            'Class' => 'al_border');
   return $options_list;
}

/*
 * Install the Redirect option, filter and action
 */
add_filter('amazon_link_option_list', 'alx_redirect_options');
add_action('amazon_link_init', 'alx_redirect',12,2);
add_filter('amazon_link_url', 'alx_redirect_url', 10, 6);

?>