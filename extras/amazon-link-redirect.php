<?php

/*
Plugin Name: Amazon Link Extra - Redirect
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link/
Description: !!!BETA!!! This plugin adds the ability to redirect to any Amazon Link product using a URL of the format www.mydomain.com/go/&lt;ASIN>/&lt;LINK TYPE S,R or A>/&lt;Domain ca,cn,de, etc.>/?args. Note if using these type of links it is recommended that you clearly indicate on your site that the link is to Amazon otherwise you might be in breach of the terms and conditions of your associates account.
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
   $match = preg_match( '!^/'.$settings['redirect_word'].'[/?](?:(?<term>[^/]*)[/?])?(?:(?<type>A|S|R)[/?])?(?:(?<default_cc>ca|cn|de|fr|es|jp|uk|us)[/?])?!', $uri, $args);
   if ( $match ) {
      $arg_position = strpos($uri,'?');
      if ($arg_position > 0) $parameters = substr($uri,$arg_position+1);
      $home_cc = $settings['default_cc'];

      $type = !empty($args['type']) ? $args['type'] : 'A';
      $type = $type_map[$type];

      if (!empty($args['default_cc'])) {
         $settings='default_cc='. $args['default_cc'].'&localise=0&';
      } else {
         $settings ='';
      }

      $term = $args['term'];
      $asin = preg_grep('/^[0-9a-z]{10}$/i',array($term));

      if ( (strlen($term) > 10) || empty($asin) ) {
         // Do Database lookup here for predefined terms
         if (0) {
            $parameters .= $result['args'];
         } else {
            $settings .= 'search_text='.$term;
            $type = 'search';
         }
      } else {
         $settings .= 'asin='.$term;
      }

      $al->parseArgs($settings.'&'. $parameters);
      $settings   = $al->getSettings();
      $settings['asin'] = $settings['asin'][0];
      $settings['template_content'] = $settings['search_text'];
      $settings['home_cc'] = $home_cc;

      $url = $al->get_url('',$type, $settings['asin'], rawurlencode($al->search->parse_template($settings)), $al->get_local_info($settings), $settings);
//echo "<PRE>"; print_r($url); echo "</PRE>";
      wp_redirect($url, '302');
      die();
   }

   if ($settings['redirect_url']) {
      add_filter('amazon_link_url', 'alx_redirect_url', 10, 6);
   }
}

/*
 * Create a redirection style URL - OPTIONAL!
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

   // If search links are enabled then pass the search text as an argument
   if (($type == 'search') || $settings['search_link']) {
      $search = '?search_text='. $search;
   } else {
      $search = '';
   }

   // If not localised then force redirect function to send to specific locale.
   if (!$settings['localise']) {
      $cc = $local_info['cc'] . '/';
      // For country specific links that aren't search links don't need this
      if ($type != 'search') $search = '';
   }

   $type_map = array( /*default: 'product' => 'A/',*/ 'search' => 'S/', 'review' => 'R/');
   $text= get_option('home'). '/'. $settings['redirect_word']. '/'. $asin . $type_map[$type]. $cc . $search;
   return $text;
}

/*
 * Change the Amazon Link Shortcode Template to create links of the form:
 * <a class="amazon-link" title="Title" href="/<redirect_word>/<asin>?shortcode">Text</a>
 */
function alx_redirect_shortcode_template ($template, $al) {
   $settings = $al->getSettings();
   return '<a class="amazon-link" title="%TITLE%" href="/'. 
          $settings['redirect_word'] .
          '/%ASIN%?%ARGS%">%TEXT%</a>';
}

/*
 * Change the Amazon Link Regex to find links of the form <a class="amazon-link" href="/<redirect_word>/<asin>?shortcode">Text</a>
 */
function alx_redirect_regex ($regex, $al) {
   $settings = $al->getSettings();
   return '!<a\sclass="(?U:.*)amazon-link(?U:.*)"'. // Must start with class element ignoring any other classes
          ' (?:(?U:.*)href=".*/'. $settings['redirect_word'] .'/(?<asin>[^/?]*)(\?(?<args>(?U:[^\[\]]*(?:\[[a-z]*\]){0,1})))?")?'. // optional href
          ' (?:[>]*)' .                             // ignore any further data inside the element
          ' > '.                                    // End of link tag
          ' (?<text>.*)'.                          // optional text
          ' </a>!x';                                // close link tag
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
   $options_list['redirect_url'] = array ( 'Name' => __('Redirection Links', 'amazon-link'),
                                            'Description' => __('The links to Amazon displayed on your site are of the form &lta href="/&ltREDIRECT WORD>/ASIN/...". Note this is part of the plugin that Amazon might frown upon', 'amazon-link'),
                                            'Type' => 'checkbox',
                                            'Default' => '1',
                                            'Class' => 'al_border');
   return $options_list;
}

/*
 * Install the Redirect option, filter and action
 *
 * Modifies the following Functions:
 *  - On creating links using the Post/Page Edit helper, create links of the from <a class="amazon-link" href="/al/ASIN/..."> (shortcode_template)
 *  - When processing the Content search for <a class="amazon-link" href="/al/ASIN/..."> to replace with amazon-link templates (redirect_regex)
 *  - When creating links on the Page generate them in the form <a href="/al/ASIN/..."> (redirect_url)
 *  - On Init checks to see if the URI is a redirect link - if it is then redirect to Amazon (redirect)
 */
add_filter('amazon_link_option_list', 'alx_redirect_options');
add_action('amazon_link_init', 'alx_redirect',12,2);
add_filter('amazon_link_regex', 'alx_redirect_regex',10,2);
add_filter('amazon_link_shortcode_template', 'alx_redirect_shortcode_template',10,2);
?>