<?php

/*
Plugin Name: Amazon Link Extra - Image Sizes
Plugin URI: http://www.houseindorset.co.uk/
Description: Update the Amazon Link plugin to return Specific size images from Amazon not just the 'Small' for Thumbnail and 'Large' from Image, this is not the 'recommended' method for retrieving images from the AWS so no guarantees!.
Version: 1.1
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
*/

/*
Copyright 2011-2012 Paul Stuttard

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
 * Filter to process the image url
 */
function alx_images_process_image_url ($url, $keyword_info, $al) {

   $settings = $al->getSettings();

   if ($keyword_info['Keyword'] == 'thumb')
   {
       $size = $settings['thumb_size'];
   } else {
       $size = $settings['image_size'];
   }

   // URL of the form: 'http://ecx.images-amazon.com/images/I/518FFDVWNQL._SL160_.jpg
   $url = preg_replace('!(http://(?:[^/]*/)+(?:[^.]*))!', '\1._SL' .$size. '_.jpg', $url);
   return $url;
}


/*
 * Filter to change the thumb & image keyword and attach a filter to it to munge the URL
 */
function alx_images_keywords ($keywords) {

   $keywords['thumb']['Callback'] = 'alx_images_process_image_url';
   $keywords['thumb']['Position'] = array(array('LargeImage','URL'), array('MediumImage','URL'), array('SmallImage','URL'));

   $keywords['image']['Callback'] = 'alx_images_process_image_url';
   $keywords['image']['Position'] = array(array('LargeImage','URL'), array('MediumImage','URL'), array('SmallImage','URL'));

   return $keywords;
}

/*
 * Add the Image Size options to the Amazon Link Settings Page
 */
function alx_images_option_list ($options_list) {
   $options_list['image_size'] = array ( 'Name' => __('Preferred Image Size', 'amazon-link'),
                                       'Description' => __('Retrieve the URL to an Image with the width or height (the longest) of this size rather than the default \'Large\' Image.', 'amazon-link'),
                                       'Type' => 'text', 
                                       'Default' => '400', 
                                       'Class' => 'al_border');
   $options_list['thumb_size'] = array ( 'Name' => __('Preferred Thumb Size', 'amazon-link'),
                                       'Description' => __('Retrieve the URL to a Thumbnail with the width or height (the longest) of this size rather than the default \'Small\' Image.', 'amazon-link'),
                                       'Type' => 'text', 
                                       'Default' => '100', 
                                       'Class' => 'al_border');

   return $options_list;
}

/*
 * Install the image size keyword, data filter and options
 */
add_filter('amazon_link_keywords', 'alx_images_keywords',10,1);
add_filter('amazon_link_option_list', 'alx_images_option_list');
?>