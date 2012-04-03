<?php
/*****************************************************************************************/

/*
 * Amazon Link Search Class
 *
 * Provides a facility to do simple Amazon Searches via the ajax interface and return results in
 * an array.
 *
 * To use the default script and styles you must add the following on init (before the header).
 *    - wp_enqueue_script('amazon-link-search')
 *    - wp_enqueue_styles('amazon-link-styles')
 *
 * The page must consist of a form with input elements all with the id='amazon-link-search', and
 * with the following names:
 *    - s_title
 *    - s_index
 *    - s_author
 *    - s_page
 *    - s_template
 *
 * To initiate a search there must be an element in the form which triggers the javascript:
 * 'return wpAmazonLinkSearch.searchAmazon(this.form);'
 * 
 * The results are inserted into the html element on the page with the id='amazon-link-result-list'.
 * Which should be contained within an element of id='amazon-link-results', there should also be a hidden
 * element with the id='amazon-link-error' to report any errors that occur. As well as an element with the
 * id='amazon-link-status' to indicate a search in progress.
 *
 * The values of the form input items are used to control the search, 'title', 'author' are used as search terms,
 * 'index' should be a valid amazon search index (e.g. Books). 'page' should be used to set which page of the results
 * is to be displayed.
 * 'template' can be used to get the search engine to populate a predefined html template with values - this should be htmlencoded.
 * the following terms are replaced with values relevant to the search results:
 *    - %ASIN%         - Item's unique ASIN
 *    - %TITLE%        - Item't Title
 *    - %TEXT1%        - User Defined Text string
 *    - %TEXT2%        - User Defined Text string
 *    - %TEXT3%        - User Defined Text string
 *    - %TEXT4%        - User Defined Text string
 *    - %ARTIST%       - Item's Author, Artist or Creator
 *    - %MANUFACTURER% - Item's Manufacturer
 *    - %THUMB%        - URL to Thumbnail Image
 *    - %IMAGE%        - URL to Full size Image
 *    - %IMAGE_CLASS%  - Class of Image as defined in settings
 *    - %URL%          - The URL returned from the Item Search (not localised!)
 *    - %RANK%         - Amazon Rank
 *    - %RATING%       - Numeric User Rating - (No longer Available)
 *    - %PRICE%        - Price of Item
 *    - %TAG%          - Default Amazon Associate Tag (not localised!)
 *    - %DOWNLOADED%   - (1 if Images are in the local Wordpress media library)
 *    - %LINK_OPEN%    - Create a Amazon link with user defined content, of the form %LINK_OPEN%My Content%LINK_CLOSE%
 *    - %LINK_CLOSE%   - Must follow a LINK_OPEN (translates to '</a>').
 */

if (!class_exists('AmazonLinkSearch')) {
   class AmazonLinkSearch {

      var $data = array();

      function AmazonLinkSearch() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
      }

      /*
       * Must be called by the client in its init function.
       */
      function init($parent) {

         $script = plugins_url("amazon-link-search.js", __FILE__);
         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
         add_action('wp_ajax_amazon-link-search', array($this, 'performSearch'));      // Handle ajax search requests
         add_action('wp_ajax_amazon-link-get-image', array($this, 'grabImage'));       // Handle ajax image download
         add_action('wp_ajax_amazon-link-remove-image', array($this, 'removeImage'));  // Handle ajax image removal

         $this->alink    = $parent;
         $this->keywords = array(
                                  'link_open'    => array( 'Description' => __('Create a Amazon link with user defined content, of the form %LINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'link' => '1'),
                                  'link_close'   => array( 'Description' => __('Must follow a LINK_OPEN (translates to "</a>").', 'amazon-link')),
                                  'asin'         => array( 'Description' => __('Item\'s unique ASIN', 'amazon-link'), 'live' => '1'),
                                  'asins'        => array( 'Description' => __('Comma seperated list of ASINs', 'amazon-link')),
                                  'product'      => array( 'Description' => __('Item\'s Product Group', 'amazon-link'), 'live' => '1'),
                                  'title'        => array( 'Description' => __('Item\'s Title', 'amazon-link'), 'live' => '1'),
                                  'text'         => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'user' => '1'),
                                  'text1'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'user' => '1'),
                                  'text2'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'user' => '1'),
                                  'text3'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'user' => '1'),
                                  'text4'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'user' => '1'),
                                  'artist'       => array( 'Description' => __('Item\'s Author, Artist or Creator', 'amazon-link'), 'live' => '1'),
                                  'manufacturer' => array( 'Description' => __('Item\'s Manufacturer', 'amazon-link'), 'live' => '1'),
                                  'thumb'        => array( 'Description' => __('URL to Thumbnail Image', 'amazon-link'), 'live' => '1', 'image' => '1'),
                                  'image'        => array( 'Description' => __('URL to Full size Image', 'amazon-link'), 'live' => '1', 'image' => '1'),
                                  'image_class'  => array( 'Description' => __('Class of Image as defined in settings', 'amazon-link')),
                                  'url'          => array( 'Description' => __('The URL returned from the Item Search (not localised!)', 'amazon-link'), 'live' => '1'),
                                  'rank'         => array( 'Description' => __('Amazon Rank', 'amazon-link'), 'live' => '1'),
                                  'rating'       => array( 'Description' => __('Numeric User Rating - (No longer Available)', 'amazon-link'), 'live' => '1'),
                                  'price'        => array( 'Description' => __('Price of Item', 'amazon-link'), 'live' => '1'),
                                  'tag'          => array( 'Description' => __('Localised Amazon Associate Tag', 'amazon-link')),
                                  'cc'           => array( 'Description' => __('Localised Country Code (us, uk, etc.)', 'amazon-link')),
                                  'flag'         => array( 'Description' => __('Localised Country Flag Image URL', 'amazon-link')),
                                  'mplace'       => array( 'Description' => __('Localised Amazon Marketplace Code (US, GB, etc.)', 'amazon-link')),
                                  'mplace_id'    => array( 'Description' => __('Localised Numeric Amazon Marketplace Code (2=uk, 8=fr, etc.)', 'amazon-link')),
                                  'tld'          => array( 'Description' => __('Localised Top Level Domain (.com, .co.uk, etc.)', 'amazon-link')),
                                  'rcm'          => array( 'Description' => __('Localised RCM site host domain (rcm.amazon.com, rcm-uk.amazon.co.uk, etc.)', 'amazon-link')),
                                  'downloaded'   => array( 'Description' => __('1 if Images are in the local Wordpress media library', 'amazon-link')),
                                  'found'        => array( 'Description' => __('1 if product was found doing a live data request (also 1 if live not enabled).', 'amazon-link'))
                                  );
      }


/*****************************************************************************************/
      /// AJAX Call Handlers
/*****************************************************************************************/

      function performSearch($Opts='') {
         if (!is_array($Opts)) $Opts = $_POST;

         $Settings = array_merge($this->alink->getSettings(), $Opts);
         $Settings['multi_cc'] = '0';
         $Settings['localise'] = 0;

         if ( empty($Opts['s_title']) && empty($Opts['s_author']) ) {
            $Items = $this->alink->itemLookup($Opts['asin'], $Settings);
         } else {
            $Settings['found'] = 1;
            $Items = $this->do_search($Opts);
         }

         $results['success'] = False;
         if (is_array($Items) && (count($Items) >0)) {
            foreach($Items as $Item) {
               $Item['found'] = 1;
               $item = $this->parse_xml($Item, $Settings['default_cc']);
               $item = array_merge($Settings,$item);
               $results['items'][]['template'] = $this->parse_template($item);
            }
            $results['success'] = True;
         }

         print json_encode($results);
         exit();
      }

      function removeImage() {
         $Opts = $_POST;

         /* Do we have this image? */
         $media_ids = $this->find_attachments( $Opts['asin'] );

         if (is_wp_error($media_ids)) {
            $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => __('No matching image found', 'amazon-link'));
         } else {

            $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => __('Images deleted','amazon-link'));

            /* Only remove images attached to this post */
            foreach ($media_ids as $id => $media_id) {
               if ($media_id->post_parent == $Opts['post']) {
                  /* Remove attachment */
                  wp_delete_attachment($media_id->ID);
               } else {
                  $results['in_library'] = true;
                  $results['id'] = $media_id->ID;
               }
            }
         }
         print json_encode($results);
         exit();         
      }

      function grabImage() {
         $Opts = $_POST;

         /* Do not upload if we already have this image */
         $media_ids = $this->find_attachments( $Opts['asin'] );

         if (!is_wp_error($media_ids)) {
            $results = array('in_library' => true, 'asin' => $Opts['asin'], 'id' => $media_ids[0]->ID);
         } else {

            /* Attempt to download the image */
            $result = $this->grab_image($Opts['asin'], $Opts['post']);
            if (is_wp_error($result))
            {
               $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => $result->get_error_code());
            } else {
               $results = array('in_library' => true, 'asin' => $Opts['asin'], 'id' => $result);
            }
         }
         print json_encode($results);
         exit();         
      }


/*****************************************************************************************/
      /// Helper Functions
/*****************************************************************************************/


      function get_aws_info() {

         $search_index_by_locale = array( 
            'ca' => array('All', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Kitchen', 'Music', 'Software', 'SoftwareVideoGames',
'VHS', 'Video', 'VideoGames'),
            'us' => array('All', 'Apparel', 'Appliances', 'ArtsAndCrafts', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DigitalMusic',
'Grocery', 'MP3Downloads', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'Industrial', 'Jewelry', 'KindleStore',
'Kitchen', 'Magazines', 'Merchants', 'Miscellaneous', 'MobileApps', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'PCHardware', 'PetSupplies', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Tools', 'Toys',
'UnboxVideo', 'VHS', 'Video', 'VideoGames', 'Watches', 'Wireless', 'WirelessAccessories'),
            'cn' => array('All', 'Apparel', 'Appliances', 'Automotive', 'Baby', 'Beauty', 'Books', 'Electronics', 'Grocery', 'HealthPersonalCare', 'Home',
'HomeImprovement', 'Jewelry', 'Misc', 'Music', 'OfficeProducts', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Toys', 'Video',
'VideoGames', 'Watches'),
            'de' => array('All', 'Apparel', 'Automotive', 'Baby', 'Blended', 'Beauty', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Grocery',
'HealthPersonalCare', 'HomeGarden', 'Jewelry', 'KindleStore', 'Kitchen', 'Lighting', 'Magazines', 'MP3Downloads',
'Music', 'MusicalInstruments', 'MusicTracks', 'OfficeProducts', 'OutdoorLiving', 'Outlet', 'PCHardware', 'Photo', 'Software',
'SoftwareVideoGames', 'SportingGoods', 'Tools', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'es' => array('All', 'Books', 'DVD', 'Electronics', 'ForeignBooks', 'Kitchen', 'Music', 'Software', 'Toys', 'VideoGames', 'Watches'),
            'fr' => array('All', 'Apparel', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'HealthPersonalCare',
'Jewelry', 'Kitchen', 'Lighting', 'MP3Downloads', 'Music', 'MusicalInstruments', 'MusicTracks', 'OfficeProducts', 'Outlet',
'Shoes', 'Software', 'SoftwareVideoGames', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'it' => array('All', 'Books', 'DVD', 'Electronics', 'ForeignBooksSearchIndex:Garden', 'Kitchen', 'Music', 'Shoes', 'Software', 'Toys',
'VideoGames', 'Watches'),
            'jp' => array('All', 'Apparel', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Grocery',
'HealthPersonalCare', 'Hobbies', 'HomeImprovement', 'Jewelry', 'Kitchen', 'MP3Downloads', 'Music', 'MusicalInstruments',
'MusicTracks', 'OfficeProducts', 'Shoes', 'Software', 'SportingGoods', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'uk' => array('All', 'Apparel', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'Grocery', 'HealthPersonalCare',
'HomeGarden', 'Jewelry', 'Kitchen', 'Lighting', 'MP3Downloads', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'Outlet', 'Shoes', 'Software', 'SoftwareVideoGames', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'us' => array('All', 'Apparel', 'Appliances', 'ArtsAndCrafts', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DigitalMusic',
'Grocery', 'MP3Downloads', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'Industrial', 'Jewelry', 'KindleStore',
'Kitchen', 'Magazines', 'Merchants', 'Miscellaneous', 'MobileApps', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'PCHardware', 'PetSupplies', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Tools', 'Toys',
'UnboxVideo', 'VHS', 'Video', 'VideoGames', 'Watches', 'Wireless', 'WirelessAccessories'));

         return array('SearchIndexByLocale' => $search_index_by_locale);
      }

      function do_search($Opts) {

         $Settings = array_merge($this->alink->getSettings(), $Opts);
         $Settings['multi_cc'] = '0';
         $Settings['found'] = 1;
         $Settings['localise'] = 0;

         // Not working: Baby, MusicalInstruments
         $Creator = array( 'Author' => array( 'Books', 'ForeignBooks', 'MobileApps', 'MP3Downloads'),
                           'Actor' => array( 'DigitalMusic' ),
                           'Artist' => array('Music'),
                           'Director' => array('DVD', 'UnboxVideo', 'VHS', 'Video'),
                           'Publisher' => array('Magazines'),
                           'Brand' => array('Apparel', 'ArtsAndCrafts', 'Baby', 'Beauty', 'Grocery', 'Lighting', 'OfficeProducts', 'Miscellaneous', 'PetSupplies', 'Shoes', 'MusicalInstruments', 'VideoGames'),
                           'Manufacturer' => array('Appliances', 'Automotive', 'Electronics', 'Garden', 'HealthPersonalCare', 'Hobbies', 'Home', 'HomeGarden', 'HomeImprovement', 'Industrial', 'Kitchen',  'OutdoorLiving', 'Photo', 'Software', 'SoftwareVideoGames'),
                           'Composer' => array('Classical'));

         $Keywords = array('Blended', 'All', 'MusicTracks', 'Outlet');
         $Sort = array('salesrank' => array('Books', 'Classical', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'HomeImprovement', 'Kitchen', 'MarketPlace', 'Music', 'OutdoorLiving', 'PCHardware', 'Software', 'SoftwareVideoGames', 'Toys', 'VHS', 'Video', 'VideoGames'),
                       'relevancerank' => array('Apparel', 'Automotive', 'Baby', 'Beauty', 'Grocery', 'Jewelry', 'KindleStore', 'MP3Downloads', 'MusicalInstruments', 'OfficeProducts', 'Shoes', 'Watches'),
                       'xsrelevancerank' => array('Shoes'));

         // Create query to retrieve the first 10 matching items
         $request = array("Operation" => "ItemSearch",
                          "ResponseGroup" => "Offers,ItemAttributes,Small,Reviews,Images,SalesRank",
                          "SearchIndex"=>$Opts['s_index'],
                          "ItemPage"=>$Opts['s_page']);

         foreach ($Sort as $Term => $Indices) {
            if (in_array($Opts['s_index'], $Indices)) {
               $request['Sort'] = $Term;
               continue;
            }
         }

         foreach ($Creator as $Term => $Indices) {
            if (in_array($Opts['s_index'], $Indices)) {
               $request[$Term] = $Opts['s_author'];
               continue;
            }
         }

         if (in_array($Opts['s_index'], $Keywords)) {
            $request['Keywords']  = $Opts['s_title'];
         } else {
            $request['Title'] = $Opts['s_title'];
         }

         $pxml = $this->alink->doQuery($request, $Settings);

         if (($pxml === False) || !isset($pxml['Items']['Item'])) {
            $Items = array();
         } else {
            $Items=$pxml['Items']['Item'];
         }

/* Test Code to check availibility at all sites... 
 //        if( !class_exists( 'WP_Http' ) )
   //         include_once( ABSPATH . WPINC. '/class-http.php' );

         foreach($Items as $item => $item_info) {
         $map = '<div class="al_flags">';
            $country_data = $this->alink->get_country_data();
            foreach ($country_data as $cc => $data) {
               $this->alink->Settings['localise'] = 0;
               $this->alink->Settings['default_cc'] = $cc;
               $url = $this->alink->getURL($item_info['ASIN'], $data['tld'], $data['default_tag']);
               //$request = new WP_Http;
               //$result = $request->request( $url, array('timeout' => 3, 'method' => 'GET'));
               $result = $this->get_headers($url);
               $map .= '<PRE>'.$result[0].'</PRE>';
               if (!is_wp_error($result) && ($result['response']['code'] == 200)) {
                  $map .= '<img height=8px src="'. $this->alink->URLRoot. '/'. $data['flag'].'">';
               }
               break;
            }

            $map .= '</div>';
            $Items[$item]['Settings'] = $Settings;
            $Items[$item]['Settings']['text1'] = $map;
         }
 */
         return $Items;
      }

      function get_headers($url,$format=0,$httpn=0) { 
         $fp = fsockopen($url, 80, $errno, $errstr, 30); 
         if ($fp) { 
            $out = "GET / HTTP/1.1\r\n"; 
            $out .= "Host: $url\r\n"; 
            $out .= "Connection: Close\r\n\r\n"; 
            fwrite($fp, $out); 
            while (!feof($fp)) { 
               $var.=fgets($fp, 1280); 
            } 
            $var=explode("<",$var); 
            $var=$var[0]; 
            $var=explode("\n",$var); 
            fclose($fp); 
            return $var;
         }
         return array();
      }

      function preg_replacement_quote($str) {
         return preg_replace('/(\$|\\\\)(?=\d)/', '\\\\\1', $str);
      }

      function get_links ($asin, $settings, $local_info, $data) {
         if ($settings['search_link']) {
            $search = $settings['search_text'];
            foreach ($this->keywords as $keyword => $key_data) {
               $search = preg_replace('/%(' . $keyword . ')%/i' , '%$1%S#', $search);
            }
         } else {
            $search ='';
         }

         for ($count = 0; $count <= 5; $count++) {
            $data['link_open'][$local_info['cc']][$count] = substr($this->alink->make_link($asin,'',$settings, $local_info, $search),0,-4);
         }
         $data['link_close'][$local_info['cc']] = '</a>';
         return $data;
      }

      function get_images ($asin, $settings, $data) {
         /*
          * Image and Thumb URL's can have 3 sources:
          *  - passed as arguments in Settings (thumb, image if longer than 1 character)
          *  - stored in the local media library (local_thumb, local_image)
          *  - retrieved from amazon in the results
          *
          * If passed as Setting always use, if local image available use in preference to amazon ones.
          */
         $country = $settings['default_cc'];
         $media_ids = $this->find_attachments( $asin );
         if (!is_wp_error($media_ids)) {
            $data['media_id'][$country] = $media_ids[0]->ID;
            $data['downloaded'][$country] = '1';
            $data['local_thumb'][$country] = wp_get_attachment_thumb_url($data['media_id'][$country]);
            $data['local_image'][$country] = wp_get_attachment_url($data['media_id'][$country]);
         } else {
            $data['media_id'][$country] = 0;
            $data['downloaded'][$country] = '0';
         }

         if (isset($settings['thumb']) && (strlen($settings['thumb']) > 1))
            $data['thumb'][$country] = $settings['thumb'];
         elseif (isset($data['local_thumb'][$country]))
            $data['thumb'][$country] = $data['local_thumb'][$country];

         if (isset($settings['image']) && (strlen($settings['image']) > 1))
            $data['image'][$country] = $settings['image'];
         elseif (isset($data['local_image'][$country]))
            $data['image'][$country] = $data['local_image'][$country];

         return $data;
      }

      function parse_xml ($result, $country, $data = array()) {

         if (!isset($data['asin'][$country])) $data['asin'][$country]   = (isset($result['ASIN']) ? $result['ASIN'] : 0);
         if (!isset($data['asins'][$country])) $data['asins'][$country]  = (isset($result['ASINS']) ? $result['ASINS'] :  0);
         if (!isset($data['title'][$country])) $data['title'][$country]  = (isset($result['ItemAttributes']['Title']) ?  $result['ItemAttributes']['Title'] : '');
         if (!isset($data['artist'][$country])) {
            $data['artist'][$country] = (isset($result['ItemAttributes']['Artist']) ? $result['ItemAttributes']['Artist'] :
                                  (isset($result['ItemAttributes']['Author']) ? $result['ItemAttributes']['Author'] :
                                   (isset($result['ItemAttributes']['Director'])  ? $result['ItemAttributes']['Director'] :
                                   (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : 
                                    (isset($result['ItemAttributes']['Brand']) ? $result['ItemAttributes']['Brand'] : '-') ))) );
            $data['artist'][$country] = $this->remove_parents($data['artist'][$country]);
         }

         if (!isset($data['manufacturer'][$country])) $data['manufacturer'][$country]  = (isset($result['ItemAttributes']['Manufacturer']) ?  $result['ItemAttributes']['Manufacturer'] : (isset($result['ItemAttributes']['Brand']) ?  $result['ItemAttributes']['Brand'] : '-'));
         if (!isset($data['url'][$country])) $data['url'][$country]     = (isset($result['DetailPageURL']) ? $result['DetailPageURL'] : '');
         if (!isset($data['rank'][$country])) $data['rank'][$country]    = (isset($result['SalesRank']) ? $result['SalesRank'] : '');
         if (!isset($data['rating'][$country])) $data['rating'][$country]  = (isset($result['CustomerReviews']['AverageRating']) ? $result['CustomerReviews']['AverageRating'] : '-');
         if (!isset($data['price'][$country])) $data['price'][$country]   = (isset($result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice']) ? $result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice'] : 
                                   (isset($result['OfferSummary']['LowestNewPrice']['FormattedPrice']) ? $result['OfferSummary']['LowestNewPrice']['FormattedPrice'] :
                                    (isset($result['OfferSummary']['LowestUsedPrice']['FormattedPrice']) ? $result['OfferSummary']['LowestUsedPrice']['FormattedPrice'] :
                                     (isset($result['ItemAttributes']['ListPrice']['FormattedPrice']) ? $result['ItemAttributes']['ListPrice']['FormattedPrice'] : '-'))));
         if (!isset($data['type'][$country])) $data['type'][$country]    = 'Amazon';
         if (!isset($data['product'][$country])) $data['product'][$country] = (isset($result['ItemAttributes']['ProductGroup']) ? $result['ItemAttributes']['ProductGroup'] : '-');
         if (!isset($data['found'][$country])) $data['found'][$country]   = (isset($result['found']) ? $result['found'] : 0);

         if (!isset($data['thumb'][$country])) {
            if (isset($result['MediumImage']))
               $data['thumb'][$country] = $result['MediumImage']['URL'];
            else
               $data['thumb'][$country] = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";
         }

         if (!isset($data['image'][$country])) {
            if (isset($result['LargeImage']))
                $data['image'][$country] = $result['LargeImage']['URL'];
            else
                $data['image'][$country] = $data['thumb'][$country];
         }
 
         return $data;
      }

      /*
       * item = asin => XXXXX,
                title => array ('uk' => Title, 'us' => Title2),
                image => url)

       * cc = uk:

       * out  = asin[uk] => XXXX,
                title    => array('uk' => Title, 'us'...
                image[uk] => url)
       */

      function regionalise ($data, $index, $output = array()) {
         foreach($data as $key => $info) {
            if (is_array($info) && ($key != 'link_open')) {
               if (!isset($output[$key])) $output[$key] = $info;
            } else {
               if (!isset($output[$key][$index])) $output[$key][$index] = $info;
            }
         }
         return $output;
      }


      function globalise ($data, $output = array(), $default_cc) {
         $country_data = $this->alink->get_country_data();
         foreach($data as $key => $info) {
            if (is_array($info) && ($key != 'link_open')) {
               $output[$key] = $info;
            } else if ($key == 'asin') {
               $output[$key][$default_cc] = $info;
            } else {
               foreach($country_data as $index => $country_info) {
                  $output[$key][$index] = $info;
               }
            }
         }
         return $output;
      }

      function parse_template ($item, $template=NULL, $settings=NULL) {
         $country_data = $this->alink->get_country_data();
         $local_info   = $this->alink->get_local_info($item);
         $local_country = $local_info['cc'];
         $default_country = $item['default_cc'];

         // 'channel' used may be different for each shortcode or post so need to refresh every template
         $data = $this->regionalise($local_info, $local_country);

         if ($item['global_over']) {
            $data = $this->globalise($item, $data, $default_country);
         } else {
            $data = $this->regionalise($item, $local_country, $data);
         }
//         echo "<PRE>DATA: "; print_r($data); echo "</pRE>";
         $input = htmlspecialchars_decode (stripslashes($item['template_content']));

         $local_settings = $item;
         foreach ($this->keywords as $keyword => $key_data) {
            $index=0;
            $output = '';
            while (($key_start = stripos($input, '%'.$keyword.'%' , $index)) !== FALSE) {
               $key_end = $key_start + 2+strlen ($keyword);

               $country = $local_country;
               $localised = true;
               $escaped = false;
               $local_settings['multi_cc'] = $item['multi_cc'];
               $local_settings['localise'] = $item['localise'];
               $local_settings['default_cc'] = $item['default_cc'];

               // Check for Modifiers
               $modifiers = substr($input, $key_end,4);
               $mod_end = strpos($modifiers, '#');
               if ($mod_end !== FALSE) {
                  $modifier_cc = NULL;
                  $modifier_s = NULL;
                  if ($mod_end >= 2) {
                     $modifier_cc = strtolower(substr($modifiers, $mod_end-2, 2));
                     if (!array_key_exists($modifier_cc, $country_data)) {
                        $modifier_cc = False;
                     }
                  }
                  if (($mod_end == 1) || ($mod_end == 3)) {
                     $modifier_s = strtolower(substr($modifiers, 0,1));
                     if ($modifier_s != 's') {
                        $modifier_s = False;
                     }
                  }

                  if (($modifier_cc !== False) && ($modifier_s !== False)) {
                     $key_end +=$mod_end+1;
                     if ($modifier_cc != NULL) {
                        $country= $modifier_cc;
                        $local_settings['multi_cc'] = 0;
                        $local_settings['localise'] = 0;
                        $local_settings['default_cc'] = $country;
                        $localised = false;
                     }
                     if ($modifier_s != NULL) {
                        $escaped = 1;
                     }
                  }
               }

               if (!isset($this->$data[$keyword][$country])) {
                  $local_info = $this->alink->get_local_info($local_settings);
                  $asin = (isset($data['asin'][$country]) ? $data['asin'][$country] : $data['asin'][$default_country]);
                  if ($key_data['link']) {
                     $data = $this->get_links($data['asin'], $local_settings, $local_info, $data);
                  }
                  if ($key_data['image']) {
                     /* First try and get uploaded image info */
                     $data = $this->get_images($asin, $local_settings, $data);
                  }
                  if ($key_data['live'] && !isset($data[$keyword][$country]) ) {
                     if ($local_settings['live']) {
//echo "<PRE>DATA: "; print_r($local_settings); echo "</pRE>";
                        $item_data = array_shift($this->alink->itemLookup($asin, $local_settings));
                        if ($localised && !$item_data['found'] && $item['localise'] && ($country != $item['default_cc'])) {
                           $local_settings['default_cc'] = $item['default_cc'];
                           $local_settings['localise']   = 0;
                           $item_data = array_shift($this->alink->itemLookup($asin, $local_settings));
                        }
                        $data = $this->parse_xml($item_data, $country, $data);
                     } else {
                        $data[$keyword][$country] = 'Undefined';
                     }
                  } else {
                     $data         = $this->regionalise($local_info, $country, $data);
                     if (!isset($data[$keyword][$country])) $data[$keyword][$country] = 'NL';
                  }
               }
               if (is_array($data[$keyword][$country])) {
                  $phrase = array_shift($data[$keyword][$country]);
               } else {
                  $phrase = $data[$keyword][$country];
               }

               if ($escaped) $phrase = addslashes($phrase); //urlencode
               $output .= substr($input, $index, ($key_start-$index)) . $phrase;
               $index  = $key_end;
            }
            $input = $output . substr($input, $index);
            if ($key_data['live']) {
               $escaped++;
            } else {
               $escaped =2;
            }
//         echo "<PRE>KEYWORD: $keyword - $input </PRE>";
         }
         $this->alink->Settings['default_cc'] = $item['default_cc'];
         $this->alink->Settings['multi_cc'] = $item['multi_cc'];
         $this->alink->Settings['localise'] = $item['localise'];
         return $input;
      }
/**/
      function find_attachments ($asin, $number = '-1') {

         // Do we already have a local image ? 
         $args = array( 'post_type' => 'attachment', 'numberposts' => $number, 'post_status' => 'all', 'no_filters' => true,
                        'meta_query' => array(array('key' => 'amazon-link-ASIN', 'value' => $asin)));
         $query = new WP_Query( $args );
         $media_ids = $query->posts;
         if ($media_ids) {
            return $media_ids;
         } else {
            return new WP_Error(__('No images found','amazon-link'));
         }
      }

      function grab_image ($ASIN, $post_id = 0) {

         $ASIN = strtoupper($ASIN);

         $result = array_shift($this->alink->itemLookup($ASIN));

         $r_title  = $result['ItemAttributes']['Title'];

         $r_artist = (isset($result['ItemAttributes']['Artist']) ? $result['ItemAttributes']['Artist'] :
                      (isset($result['ItemAttributes']['Author']) ? $result['ItemAttributes']['Author'] :
                       (isset($result['ItemAttributes']['Director'])  ? $result['ItemAttributes']['Director'] :
                        (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : 
                         (isset($result['ItemAttributes']['Brand']) ? $result['ItemAttributes']['Brand'] : '-') ))) );

         $r_artist  = $this->remove_parents($r_artist);

         if (isset($result['LargeImage']))
           $r_s_url  = $result['LargeImage']['URL'];
         elseif (isset($result['MediumImage']))
           $r_s_url  = $result['MediumImage']['URL'];
         else
           $r_s_url  = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";

         if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) )
            return new WP_Error($uploads['error']);

         $filename = $ASIN. '.JPG';
         $filename = '/' . wp_unique_filename( $uploads['path'], basename($filename));
         $filename_full = $uploads['path'] . $filename;

         if( !class_exists( 'WP_Http' ) )
            include_once( ABSPATH . WPINC. '/class-http.php' );

         $request = new WP_Http;
         $result = $request->request( $r_s_url );
         if ($result instanceof WP_Error )
            return new WP_Error(__('Could not retrieve remote image file','amazon-link'));

         // Save file to media library
         $content = $result['body'];
         $size = file_put_contents ($filename_full, $content);

         if (is_readable($filename_full)) {
            // Grabbed Image successfully now add it to the media library
            $wp_filetype = wp_check_filetype(basename($filename_full), null );
            $attachment = array(
               'guid' => $filename,
               'post_mime_type' => $wp_filetype['type'],
               'post_title' => $r_artist . ' - ' . $r_title,   // Title
               'post_excerpt' => $r_title,                     // Caption
               'post_content' => '',                           // Description
               'post_status' => 'inherit');
            $attach_id = wp_insert_attachment( $attachment, $filename_full, $post_id);
            // you must first include the image.php file
            // for the function wp_generate_attachment_metadata() to work
            update_post_meta($attach_id , 'amazon-link-ASIN', $ASIN);
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename_full );
            //echo "<PRE>"; print_r($attach_data); echo "</PRE>";
            wp_update_attachment_metadata( $attach_id,  $attach_data );
         } else {
            return new WP_Error(__('Could not read downloaded image','amazon-link'));
         }
         return $attach_id;
      }

      function remove_parents ($array) {
         if (is_array($array)) {
            return $this->remove_parents($array[0]);
         } else {
            return $array;
         }
      }

   }
}
?>
