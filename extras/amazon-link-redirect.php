<?php

/*
Plugin Name: Amazon Link Extra - Redirect
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link/
Description: Adds the ability to redirect to any Amazon Link product using a URL of the format www.mydomain.com/go/0123456789/?args
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


function alx_redirect($settings, $al) {
   $uri = $_SERVER['REQUEST_URI'];
   $match = preg_match( '!^/go[/?](([0-9a-z]*)/)?!', $uri, $args);
   if ( $match ) {
      $arg_position = strpos($uri,'?');
      if ($arg_position > 0) $parameters = substr($uri,$arg_position+1);

      $al->parseArgs('asin='.$args[2].'&'. $parameters);

      $settings   = $al->getSettings();
      $local_info = $al->get_local_info();
      $asin       = $settings['asin'][0];

      if (isset($asin[$local_info['cc']])) {
         // User Specified ASIN always use
         $url_term = 'A-' . $asin[$local_info['cc']];
      } else if ( ($settings['search_link']) ) {//&& (strstr($settings['search_text'], '%') == FALSE)) {
         $settings['template_content'] = $settings['search_text'];
         $search_text = $al->search->parse_template($settings);
         $url_term = 'S-'.$search_text;
      } else {
         $url_term = 'A:' . $asin[$settings['default_cc']];
      }

      $url  = $al->getURL($url_term, $local_info['tld'], $local_info['tag']);

      wp_redirect($url, '302');
      die();
   }
}

add_action('amazon_link_init', 'alx_redirect',12,2);

?>