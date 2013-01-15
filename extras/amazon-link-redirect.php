<?php

/*
Plugin Name: Amazon Link Extra - Redirect
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link/
Description: Adds the ability to redirect to any Amazon Link product using a URL of the format www.mydomain.com/go/&lt;ASIN>/&lt;LINK TYPE S,R or A>/&lt;Domain ca,cn,de, etc.>/?args. Note if using these type of links it is recommended that you clearly indicate on your site that the link is to Amazon otherwise you might be in breach of the terms and conditions of your associates account.
Version: 1.2.1
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
   $match = preg_match( '!^/'.$settings['redirect_word'].'(?:/(?P<asin>[A-Z0-9]{10})|/(?P<ref>[^/]{2,}))?(?:/(?P<type>A|S|R))?(?:/(?P<default_cc>ca|cn|de|fr|it|es|jp|uk|us))?!', $uri, $args);
   if ( $match ) {
      $arg_position = strpos($uri,'?');
      if ($arg_position > 0) $parameters = substr($uri,$arg_position+1);
      $home_cc = $settings['default_cc'];

      // Get all named args
      $opts = array();
      foreach ($args as $arg => $data)
         if (!is_int($arg) && !empty($data)) $opts[$arg] = $data;

      // Extract the Hard coded Type if set
      $type = !empty($opts['type']) ? $opts['type'] : 'A';
      $type = $type_map[$type];
      unset($opts['type']);

      // If hard coded to a specific locale then disable localisation
      if (isset($opts['default_cc'])) {
         $opts['localise']=0;
      }

      // Convert to a shortcode args string
      $settings = $sep = '';
      foreach ($opts as $opt => $data) {
         $settings .= $sep. $opt .'='. $data;
         $sep = '&';
      }
      
      $settings   = $al->parseArgs($settings.'&'. $parameters);

      $settings['asin'] = $settings['asin'][0];
      $settings['template_content'] = $settings['search_text'];
      $settings['home_cc'] = $home_cc;

      $url = $al->get_url('',$type, $settings['asin'], rawurlencode($al->search->parse_template($settings)), $al->get_local_info($settings), $settings);
//echo "<PRE>URL:"; print_r($url); echo "</PRE>";
      if ($url) {
         wp_redirect($url, '302');
         die();
      }
   }

   if ($settings['redirect_url']) {
      add_filter('amazon_link_url', 'alx_redirect_url', 10, 7);
   }
}

/*
 * Create a redirection style URL - OPTIONAL!
 */
function alx_redirect_url($url, $type, $asin, $search, $local_info, $settings, $al) {

   $options = $al->getOptions();

   /* Work out which ASIN to use */
   if (!empty($settings['ref'])) {
      $asin = $settings['ref'].'/';
   } else if (!empty($asin[$local_info['cc']])) {
      // User Specified ASIN always use
      $asin = $asin[$local_info['cc']].'/';
   } else if ($settings['search_link'] && ($type == 'product') && !empty($asin[$settings['home_cc']])) {

      // User wants search links for non-local domains
      $type = 'search';
      $asin = $asin[$settings['home_cc']].'/';
   } else if (empty($asin[$settings['home_cc']]) && !empty($settings['url'])){
      return $settings['url'][$local_info['cc']];
   } else {
     
      // Try using the default cc ASIN
      $asin = $asin[$settings['home_cc']].'/';
   }

   // If search links are enabled then pass the search text as an argument
   if (($type == 'search') || ($settings['search_link'] && ($options['search_text'] != $search))) {
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
          '/%ASIN%%REF%?%UNUSED_ARGS%">%TEXT%</a>';
}

/*
 * Change the Amazon Link Regex to find links of the form <a class="amazon-link" href="/<redirect_word>/<asin>|<ref>?args">Text</a>
 * 
 */
function alx_redirect_regex ($regex, $al) {
   $settings = $al->getSettings();
   return '!<a\sclass="(?U:.*)amazon-link(?U:.*)"'. // Must start with class element ignoring any other classes
          ' (?:(?U:.*)title="(?P<title>[^"]*)")?'.    // Optional Title
          ' (?:(?U:.*)href=".*/'. $settings['redirect_word'].
          '  (?:/(?P<asin>[A-Z0-9]{10})|/(?P<ref>[^/?]{2,}))'.
          '  (?:\?(?P<args>[^"]*) )"'.
          ' )?'. // optional href
          ' (?:[>]*)' .                             // ignore any further data inside the element
          ' > '.                                    // End of link tag
          ' (?P<text>.*)'.                           // optional text
          ' </a>!x';                                // close link tag
}

/*
 * Add Filter and Template to Import/Export Table
 * 
 */
function alx_redirect_impexp_expression ($expressions, $al) {

   $expressions['redirect'] = array ( 'Regex'       => alx_redirect_regex('',$al),
                                      'Name'        => __('Redirect Link', 'amazon-link'),
                                      'Description' => 'A Link Element using the redirection plugin of the form <a class="amazon-link" title="%TITLE%" href="/al/%ASIN%?%ARGS%>%TEXT%</a>.',
                                      'Template'    => alx_redirect_shortcode_template('', $al));
   return $expressions;
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
                                            'Description' => __('The links to Amazon displayed on your site are of the form &lta href="/&ltREDIRECT WORD>/ASIN/...".', 'amazon-link'),
                                            'Type' => 'checkbox',
                                            'Default' => '1',
                                            'Class' => 'al_border');
   return $options_list;
}

/*
 * Install the Redirect option, filter and action
 *
 * Modifies the following Functions:
 *  - On Init checks to see if the URI is a redirect link - if it is then redirect to Amazon (redirect)
 *    - [Optionally: When dynamically creating links on the Page generate them in the form <a href="/al/ASIN/..."> (redirect_url) ]
 *
 *  - Add two extra options to control the redirect links (redirect_options)
 *  - When processing the Content search for <a class="amazon-link" href="/al/ASIN/..."> to replace with amazon-link templates (redirect_regex)
 *  - On creating links using the Post/Page Edit helper, create links of the from <a class="amazon-link" href="/al/ASIN/..."> (shortcode_template)
 */
add_action('amazon_link_init', 'alx_redirect',12,2);
add_filter('amazon_link_option_list', 'alx_redirect_options');
add_filter('amazon_link_regex', 'alx_redirect_regex',10,2);
add_filter('amazon_link_impexp_expressions', 'alx_redirect_impexp_expression',10,2);
add_filter('amazon_link_shortcode_template', 'alx_redirect_shortcode_template',10,2);
?>