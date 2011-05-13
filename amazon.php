<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: Insert a link to Amazon using the passed ASIN number, with the required affiliate info.
Version: 1.8.2
Text Domain: amazon-link
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
License: GPL2
*/

/*
Copyright 2005-2006 Paul Stuttard (email : wordpress_amazonlink@ redtom.co.uk)

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
Usage:
	Add one of the following lines into an entry:

	[amazon asin=<ASIN Number>&text=<link text>]           --> inserts link to amazon item
	[amazon cat=<Category List>&last=<Number of Posts>]    --> inserts table of random items

Layout:

   amazon_container      - Encloses whole wishlist.
   amazon_prod           - Encloses each list item.
   amazon_img_container  - Encloses the item thumbnail (link+img)
   amazon_pic            - Class of the item thumbnail IMG element
   amazon_text_container - Encloses the item description (Title paragraphs+link + Details paragraphs)
   amazon_details        - Encloses the item details part of the description
   amazon_price          - Spans the item's formatted price.

*/

require_once('aws_signed_request.php');

require_once('include/displayForm.php');

if (!class_exists('AmazonWishlist_ip2nation'))
   include_once ( 'include/ip2nation.php');

if (!class_exists('AmazonLinkSearch'))
   include_once ( 'include/amazonSearch.php');

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      var $optionList   = null;
      var $country_data = null;

      // String to insert into Posts to indicate where to insert the amazon items
      var $TagHead    = '[amazon';
      var $TagTail    = ']';

      var $optionVer  = 1;
      var $optionName = 'AmazonLinkOptions';
      var $Opts       = null;

      var $multi_id   = 0;

      function AmazonWishlist_For_WordPress() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->form = new AmazonWishlist_Options;
         $this->ip2n = new AmazonWishlist_ip2nation;
         $this->search = new AmazonLinkSearch;

         register_activation_hook(__FILE__, array($this, 'activate'));               // To perform options upgrade
         add_action('init', array($this, 'init'));                                   // Load i18n and initialise translatable vars
         add_filter('plugin_row_meta', array($this, 'registerPluginLinks'),10,2);    // Add extra links to plugins page
         add_filter('the_posts', array($this, 'stylesNeeded'));                      // Check if styles/scripts are needed
         add_filter('the_content', array($this, 'contentFilter'),15);                // Process the content
         add_action('admin_menu', array($this, 'optionsMenu'));                      // Add options page hooks
 
      }

      function registerPluginLinks($links, $file) {
         if ($file == $this->base_name) {
            $links[] = '<a href="options-general.php?page=' . $this->base_name .'">' . __('Settings','amazon-link') . '</a>';
         }
         return $links;
      }

      function optionsMenu() {

         // Add plugin options page
         $my_page = add_options_page(__('Manage Amazon Wishlist', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options', __FILE__, array($this, 'showOptions'));

         // Add support for Post metabox, requires our styles and post edit AJAX scripts.
         add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), 'post', 'normal');
         add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), 'page', 'normal');
         add_action( "admin_print_scripts-post.php", array($this,'edit_scripts') );
         add_action( "admin_print_scripts-post-new.php", array($this,'edit_scripts') );
         add_action( "admin_print_styles-post-new.php", array($this,'amazon_admin_styles') );
         add_action( "admin_print_styles-post.php", array($this,'amazon_admin_styles') );
         add_action( "admin_print_styles-" . $my_page, array($this,'amazon_admin_styles') );
      }

      /// We only need the styles and scripts when a Wishlist or the Multinational popup is displayed...

      function amazon_styles() {
         wp_enqueue_style('amazon-link-style');
      }

      function amazon_admin_styles() {
         wp_enqueue_style('amazon-link-style');
         wp_enqueue_style('amazon-link-form');
      }

      function amazon_scripts() {
         wp_enqueue_script('amazon-link-script');
      }

      function edit_scripts() {
         $script = plugins_url("postedit.js", __FILE__);
         wp_enqueue_script('wpAmazonLinkAdmin', $script, array('jquery', 'amazon-link-search'), '1.0.0');
      }

      function generate_multi_script() {
         $Settings= $this->getOptions();
         $TARGET = $Settings['new_window'] ? 'target="_blank"' : '';
         ?>

<script type='text/javascript'> 
function al_gen_multi (id, asin, def) {
   var country_data = new Array();
<?php
         foreach ($this->country_data as $cc => $data) {
            echo "   country_data['". $cc ."'] = { 'flag' : '" . $this->URLRoot . "/" . $data['flag'] . "', 'tld' : '" . $data['tld'] . "',  'tag' : '" . $Settings['tag_' . $cc] ."'};\n";
         }
?>
   var content = "";
   for (var cc in country_data) {
      if (cc != def) {
         var url = 'http://www.amazon.' + country_data[cc].tld + '/gp/product/' + asin + '?ie=UTF8&tag=' + country_data[cc].tag + '&linkCode=as2&camp=1634&creative=6738&creativeASIN='+ asin;
         content = content +'<a <?php echo $TARGET; ?> href="' + url + '"><img src="' + country_data[cc].flag + '"></a>';
      }
   }
   al_link_in (id, content);
}
</script> 


<?php
      }

      function init() {

         /* load localisation  */
         load_plugin_textdomain('amazon-link', $this->plugin_dir . '/i18n', $this->plugin_dir . '/i18n');

         /* Move Option List & Country Data construction here so we can localise the strings */
         // Country specific aspects:
         // full name of country,
         // country flag image
         // tld of main amazon site
         // link to affiliate program site
         // Default tag if none set up
         $this->country_data = array('uk' => array('name' => __('United Kingdom', 'amazon-link'), 'flag' => 'images/flag_uk.gif', 'tld' => 'co.uk', 'site' => 'https://affiliate-program.amazon.co.uk', 'default_tag' => 'livpauls-21'),
                                     'us' => array('name' => __('United States', 'amazon-link'), 'flag' => 'images/flag_us.gif', 'tld' => 'com', 'site' => 'https://affiliate-program.amazon.com', 'default_tag' => 'lipawe-20'),
                                     'de' => array('name' => __('Germany', 'amazon-link'), 'flag' => 'images/flag_de.gif', 'tld' => 'de', 'site' => 'https://partnernet.amazon.de', 'default_tag' => 'lipas03-21'),
                                     'fr' => array('name' => __('France', 'amazon-link'), 'flag' => 'images/flag_fr.gif', 'tld' => 'fr', 'site' => 'https://partenaires.amazon.fr', 'default_tag' => 'lipas03-21'),
                                     'jp' => array('name' => __('Japan', 'amazon-link'), 'flag' => 'images/flag_jp.gif', 'tld' => 'jp', 'site' => 'https://affiliate.amazon.co.jp', 'default_tag' => 'Livpaul21-22'),
                                     'it' => array('name' => __('Italy', 'amazon-link'), 'flag' => 'images/flag_it.gif', 'tld' => 'it', 'site' => 'https://programma-affiliazione.amazon.it', 'default_tag' => 'livpaul-21'),
                                     'cn' => array('name' => __('China', 'amazon-link'), 'flag' => 'images/flag_cn.gif', 'tld' => 'cn', 'site' => 'https://associates.amazon.cn', 'default_tag' => 'livpaul-21'),
                                     'ca' => array('name' => __('Canada', 'amazon-link'), 'flag' => 'images/flag_ca.gif', 'tld' => 'ca', 'site' => 'https://associates.amazon.ca', 'default_tag' => 'lipas-20'));


         // Default wishlist template
         $wishlist_template = htmlspecialchars ('
<div class="amazon_prod">
 <div class="amazon_img_container">
  %THUMB_LINK%
 </div>
 <div class="amazon_text_container">
  <p>%LINK%</p>
  <div class="amazon_details">
    <p>'. __('by %ARTIST% [%MANUFACTURER%]', 'amazon-link') .'<br />
    '. __('Rank/Rating: %RANK%/%RATING%', 'amazon-link').'<br />
    <b>' .__('Price', 'amazon-link').': <span class="amazon_price">%PRICE%</span></b>
   </p>
  </div>
 </div>
</div>');

         $this->optionList = array(
         'title' => array ( 'Type' => 'title', 'Value' => __('Amazon Link Plugin Options')),

/* Hidden Options - not saved in Settings */

         'nonce' => array ( 'Type' => 'nonce', 'Name' => 'update-AmazonLink-options' ),
         'cat' => array ( 'Type' => 'hidden' ),
         'last' => array ( 'Type' => 'hidden' ),
         'asin' => array( 'Default' => '0', 'Type' => 'hidden'),
         'thumb' => array (  'Default' => '0', 'Type' => 'hidden' ),
         'image' => array (  'Default' => '0', 'Type' => 'hidden' ),

/* Options that change how the items are displayed */
         'hd1s' => array ( 'Type' => 'section', 'Value' => __('Display Options')),

         'text' => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'www.amazon.co.uk', 'Type' => 'text', 'Size' => '40'),
         'image_class' => array ( 'Name' => __('Image Class', 'amazon-link'), 'Description' => __('Style Sheet Class of image thumbnails', 'amazon-link'), 'Default' => 'wishlist_image', 'Type' => 'text', 'Size' => '40' ),
         'wishlist_template' => array (  'Default' => $wishlist_template, 'Type' => 'hidden' ),
         'wishlist_items' => array (  'Name' => __('Wishlist Length', 'amazon-link'), 'Description' => __('Maximum number of items to display in a wishlist [1-5] (Amazon only returns a maximum of 5)', 'amazon-link'), 'Default' => 5, 'Type' => 'text' ),
         'hd1e' => array ( 'Type' => 'end'),

/* Options that change the behaviour of the links */

         'multi_cc' => array('Name' => __('Multinational Link', 'amazon-link'), 'Description' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox'),
         'localise' => array('Name' => __('Localise Amazon Link', 'amazon-link'), 'Description' => __('Make the link point to the users local Amazon website, (you must have ip2nation installed for this to work).', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox'),
         'remote_images' => array ( 'Name' => __('Remote Images', 'amazon-link'), 'Description' => __('Use images hosted on the Amazon site when creating shortcodes', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox' ),
         'new_window' => array('Name' => __('New Window Link', 'amazon-link'), 'Description' => __('When link is clicked on open it in a new browser window', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox'),

/* Options related to the Amazon backend */

         'hd2s' => array ( 'Type' => 'section', 'Value' => __('Amazon Associate Information')),
         'default_cc' => array( 'Name' => __('Default Country', 'amazon-link'), 'Description' => __('Which country\'s Amazon site to use by default', 'amazon-link'), 'Default' => 'uk', 'Type' => 'radio'),         'pub_key' => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Public key provided by your AWS Account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40'),
         'priv_key' => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Private key provided by your AWS Account.', 'amazon-link'), 'Default' => "", 'Type' => 'text', 'Size' => '40'),
         'hd2e' => array ( 'Type' => 'end'),

         'button' => array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'AmazonLinkAction'))));

         // Populate Country related options
         foreach ($this->country_data as $cc => $data) {
            $this->optionList['default_cc']['Options'][$cc]['Name'] = '<img style="height:14px;" src="'. $this->URLRoot. '/'. $data['flag'] . '">';
            $this->optionList['default_cc']['Options'][$cc]['Input'] = 'tag_' . $cc;
            $this->optionList['tag_' . $cc]['Type'] = 'option';
            $this->optionList['tag_' . $cc]['Name'] = $data['name'] . __('Affiliate Tag', 'amazon-link');
            $this->optionList['tag_' . $cc]['Default'] = $data['default_tag'];
            $this->optionList['tag_' . $cc]['Description'] = sprintf(__('Enter your affiliate tag for %1$s.', 'amazon-link'), '<a href="'. $data['site']. '">'.$data['name'].'</a>' );
         }

         $script = plugins_url("amazon.js", __FILE__);
         // Allow the user to override our default styles. 
         if (file_exists(dirname (__FILE__).'/user_styles.css')) {
            $stylesheet = plugins_url("user_styles.css", __FILE__); 
         } else {
            $stylesheet = plugins_url("Amazon.css", __FILE__);
         }

         wp_register_style('amazon-link-style', $stylesheet);
         wp_register_script('amazon-link-script', $script);
      }

      function activate() {

         // Options structure changed so need to add the 'version' option and upgrade as appropriate...
         $Opts = $this->getOptions();

         if (!isset($Opts['version'])) {

            $cc_map = array('co.uk' => 'uk', 'com' => 'us', 'fr' => fr, 'de' => 'de', 'ca' => 'ca', 'jp' => 'jp');

            // Move from version 1.2 to 1.3 of the plugin (Option Version Null => 1)
            if (isset($Opts['tld'])) {
               $cc = isset($cc_map[$Opts['tld']]) ? $cc_map[$Opts['tld']] : 'uk';
               $Opts['default_cc'] = $cc;
               if (isset($Opts['tag'])) $Opts['tag_' . $cc] = $Opts['tag'];
            }
            unset($Opts['tld']);
            unset($Opts['tag']);
            $Opts['version'] = 1;
            $this->saveOptions($Opts);
         }
      }

      function stylesNeeded($posts){
         if (empty($posts)) return $posts;
       
         $this->stylesNeeded = False;
         foreach ($posts as $post) {
            $post->post_content = $this->contentFilter($post->post_content, False, False);
            if ($this->stylesNeeded) {
               $this->amazon_styles();
               $this->amazon_scripts();
               add_action('wp_print_scripts', array($this, 'generate_multi_script'));
               break;
            }
         }
         return $posts;
      }

/*****************************************************************************************/
      /// Options
/*****************************************************************************************/

      function getOptions() {
         if (null === $this->Opts) {
            $this->Opts = get_option($this->optionName, array());
            // Ensure hidden items are not stored in the database
            foreach ( $this->optionList as $optName => $optDetails ) {
               if ($optDetails['Type'] == 'hidden') unset($this->Opts[$optName]);
            }
         }
         return $this->Opts;
      }

      function saveOptions($Opts) {
         if (!is_array($Opts)) {
            return;
         }
         update_option($this->optionName, $Opts);
         $this->Opts = $Opts;
      }

      function deleteOptions() {
         delete_option($this->optionName);
      }

      /*
       * Parse the arguments passed in.
       */
      function parseArgs($arguments) {

         $args = array();
         parse_str(html_entity_decode($arguments), $args);

         $Opts = $this->getOptions();
         unset($this->Settings);
         /*
          * Check for each setting, local overides saved option, otherwise fallback to default.
          */
         foreach ($this->optionList as $key => $details) {
            if (isset($args[$key])) {
               $this->Settings[$key] = trim(stripslashes($args[$key]),"\x22\x27");              // Local setting
            } else if (isset($Opts[$key])) {
               $this->Settings[$key] = $Opts[$key];   // Global setting
            } else if (isset ($details['Default'])) {
               $this->Settings[$key] = $details['Default'];      // Use default
            }
         }
      }

/*****************************************************************************************/
      /// Localise Link Facility
/*****************************************************************************************/

      function get_country() {

        // Pretty arbitrary mapping of domains to Amazon sites, default to 'com' - the 'international' site.
        $country_map = array('uk' => array('uk', 'ie', 'gi', 'gl', 'nl', 'vg', 'cy', 'gb'),
                             'fr' => array('fr', 'be', 'bj', 'bf', 'bi', 'cm', 'cf', 'td', 'km', 'cg', 'dj', 'ga', 'gp',
                                           'gf', 'gr', 'pf', 'tf', 'ht', 'ci', 'lu', 'mg', 'ml', 'mq', 'yt', 'mc', 'nc',
                                           'ne', 're', 'sn', 'sc', 'tg', 'vu', 'wf', 'es'),
                             'de' => array('de', 'at', 'ch', 'no', 'dn', 'li', 'sk'),
                             'it' => array('it'),
                             'cn' => array('cn'),
                             'ca' => array('ca', 'pm'),
                             'jp' => array('jp')
                            );
                          
        $country = False;

        if (!isset($this->country)) {

           if ($this->Settings['localise']) {
              $cc = $this->ip2n->get_cc();

              $country = 'us';
              foreach ($country_map as $key => $countries) {
                 if (in_array($cc, $countries)) {
                    $country = $key;
                    continue;
                 }
              }
           }

           if ($country)
              $this->country = $country;
           else
              $this->country = $this->Settings['default_cc'];
         }	

         return $this->country;
      }

/*****************************************************************************************/
      /// Searches through the_content for our 'Tag' and replaces it with the lists or links
      /*
       * Performs 3 functions:
       *   1. Searches through the posts, and if multinational link required or a wishlist then bring in the styles.
       *   2. Process the content and replace the shortcode with amazon links and wishlists
       *   3. Search through the content and record any Amazon ASIN numbers ready to generate a wishlist.
       */
/*****************************************************************************************/
      function contentFilter($content, $doRecs=TRUE, $doLinks=TRUE) {

         $newContent='';
         $index=0;
         $found = 0;

         while ($found !== FALSE) {
            $found = strpos($content, $this->TagHead, $index);
            if ($found === FALSE) {
               // Add the remaining content to the output
               $newContent = $newContent . substr($content, $index);
               break;
            } else {
               // Need to parse any arguments
               $tagEnd = strpos($content, $this->TagTail, $found);
               $arguments = substr($content, $found + strlen($this->TagHead), ($tagEnd-$found-strlen($this->TagHead)));

               $this->parseArgs($arguments);

               if (isset($this->Settings['cat']) && isset($this->Settings['last'])) {
                  if ($doRecs) {
                     $output = $this->showRecommendations($this->Settings['cat'],$this->Settings['last']);
                  } elseif (!$this->stylesNeeded) {
                     $output = '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>' .
                               substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                     $this->stylesNeeded = True;
                  } else {
                     $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  }
               } else if ($doLinks) {
                  // Generate Amazon Link
                  $this->tags[] = $this->Settings['asin'];
                  $output = $this->make_links($this->Settings['asin'], $this->Settings['text']);
               } else {
                  $this->tags[] = $this->Settings['asin'];
                  if ($this->Settings['multi_cc'] && !$this->stylesNeeded) {
                     $this->stylesNeeded = True;
                     $output = '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>' .
                               substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  } else {
                     $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  }
               }

               $newContent = $newContent . substr($content, $index, ($found-$index));
               $newContent = $newContent . $output;
               $index = $tagEnd + strlen($this->TagTail);
            }
         }
         return $newContent;
      }

/*****************************************************************************************/
      /// Display Content
/*****************************************************************************************/

      function showRecommendations ($categories='1', $last='30') {
         return include('include/showRecommendations.php');
      }

      function showOptions() {
         include('include/showOptions.php');
      }

      function insertForm($post) {
         include('include/insertForm.php');
      }

      function getURL($asin)
      {
         $top_cc  = $this->get_country();
         $tld = $this->country_data[$top_cc]['tld'];
         $tag = $this->Settings['tag_' . $top_cc];
         $text='http://www.amazon.' . $tld . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $tag .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
         return $text;
      }

      function getURLs($asin)
      {
         $top_cc  = $this->get_country();
         
         $tld = $this->country_data[$top_cc]['tld'];
         $tag = $this->Settings['tag_' . $top_cc];
         $text[$top_cc]='http://www.amazon.' . $tld . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $tag .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
         foreach ($this->country_data as $cc => $data) {
            if ($cc != $top_cc) {
               $tld = $data['tld'];
               $tag = $this->Settings['tag_'.$cc];
               $text[$cc]='http://www.amazon.' . $tld . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $tag .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
            }
         }
         return $text;
      }

      function doQuery($request)
      {
         $cc = $this->country_data[$this->Settings['default_cc']]['tld'];
         if ($cc == 'it')
            $cc = "fr";
         if ($cc == 'cn')
            $cc = 'com';

         return aws_signed_request($cc, $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
      }

      function make_links($asin, $link_text)
      {
         global $post;
         $object = stripslashes($link_text);

         // Do we need to display or link to an image ?
         if ($this->Settings['image'] || $this->Settings['thumb']) {

            $media_ids = $this->search->find_attachments($asin);
            if (!is_wp_error($media_ids)) {
               $media_id = $media_ids[0]->ID;
            }

            if ($this->Settings['thumb']) {
               if (isset($media_id)) {
                  $thumb = wp_get_attachment_thumb_url($media_id);
               } elseif (strlen($this->Settings['thumb']) > 4) {
                  $thumb = $this->Settings['thumb'];
               }
            }
            if ($this->Settings['image']) {
               if (isset($media_id)) {
                  $image = wp_get_attachment_url($media_id);
               } elseif (strlen($this->Settings['image']) > 4) {
                  $image = $this->Settings['image'];
               }
            }
         }

         // If both thumb and image are specified then just insert the image
         if (isset($thumb) && isset($image)) {
            $object = '<a href = "'. $image .'"><img class="'. $this->Settings['image_class'] .'" src="'. $thumb. '" alt="'. $link_text .'"></a>';
            return $object;
         }

         if (isset($image))
            $object = '<img class="'. $this->Settings['image_class'] .'" src="'. $image . '" alt="'. $link_text .'">';
         if (isset($thumb))
            $object = '<img class="'. $this->Settings['image_class'] .'" src="'. $thumb . '" alt="'. $link_text .'">';

/*
 * Template for Multi Link
 */
 $multi_template = htmlspecialchars ('
<a onMouseOut="al_link_out()" href="%URL%" onMouseOver="al_gen_multi(%MULTI_ID%, \'%ASIN%\', \'%DEFAULT_CC%\');">
 %OBJECT%
</a>');
         $TARGET = $this->Settings['new_window'] ? 'target="_blank"' : '';
         $URL    = $this->getURL($asin);

         if ($this->Settings['multi_cc']) {
            $def = $this->get_country();
            $text='<a '. $TARGET .' onMouseOut="al_link_out()" href="' . $URL .'" onMouseOver="al_gen_multi('. $this->multi_id . ', \'' . $asin . '\', \''. $def .'\');">';
            $text .= $object. '</a>';
            $this->multi_id++;
         } else {
            $text='<a '. $TARGET .' href="' . $URL .'">' . $object . '</a>';
         }
         return $text;
      }

/////////////////////////////////////////////////////////////////////


   } // End Class

   $awlfw = new AmazonWishlist_For_WordPress();

} // End if exists

function amazon_get_link($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->getURL($awlfw->Settings['asin']);        // Return a URL
}

function amazon_get_links($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->getURLs($awlfw->Settings['asin']);        // Return an array of URLs
}

function amazon_make_links($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->make_links($awlfw->Settings['asin'], $awlfw->Settings['text']);        // Return html
}

function amazon_query($request)
{
  global $awlfw;
  $awlfw->parseArgs("");              // Get the default settings
  return $awlfw->doQuery($request);   // Return response

}

function amazon_recommends($categories='1', $last='30')
{
  global $awlfw;
  $awlfw->parseArgs("");              // Get the default settings
  return $awlfw->showRecommendations ($categories, $last);
}

?>