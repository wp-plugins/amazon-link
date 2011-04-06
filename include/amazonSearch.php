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
 *    - title
 *    - index
 *    - author
 *    - page
 *    - template
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
 *    - %ASIN%
 *    - %TITLE%
 *    - %ARTIST%
 *    - %MANUFACTURER%
 *    - %THUMB%
 *    - %IMAGE%
 *    - %URL%
 *    - %RANK%
 *    - %RATING%
 *    - %PRICE%
 *    - %DOWNLOADED% (1 if Images are in the local Wordpress media library)
 *    - %LINK%
 *    - %THUMB_LINK%
 *    - %IMAGE_LINK%
 */

if (!class_exists('AmazonLinkSearch')) {
   class AmazonLinkSearch {

      function AmazonLinkSearch() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         add_action('init', array($this,'init'));
      }

      function init() {
         $script = plugins_url("amazon-link-search.js", __FILE__);
         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
         add_action('wp_ajax_amazon-link-search', array($this, 'performSearch'));      // Handle ajax search requests
         add_action('wp_ajax_amazon-link-get-image', array($this, 'grabImage'));       // Handle ajax image download
         add_action('wp_ajax_amazon-link-remove-image', array($this, 'removeImage'));  // Handle ajax image removal
      }

      function performSearch() {
         $Opts = $_POST;

         if ($Opts['index'] == 'Books') {
            $Term = "Author";
         } else if ($Opts['index'] == 'Music') {
            $Term = "Artist";
         } else if ($Opts['index'] == 'DVD') {
            $Term = "Publisher";
         } else {
            $Term = "Manufacturer";
         }

         // Create query to retrieve the first 10 matching items
         $request = array("Operation" => "ItemSearch",
                          "ResponseGroup" => "Small,Reviews,Images,Offers,SalesRank",
                          $Term=>$Opts['author'],
                          "Title"=>$Opts['title'],
                          "SearchIndex"=>$Opts['index'],
                          "Sort"=>"salesrank",
                          "MerchantId"=>"Amazon",
                          "ItemPage"=>$Opts['page']);

         $pxml = amazon_query($request);

         if (($pxml === False) || !isset($pxml['Items']['Item'])) {
            $results = array('success' => false);
            $Items = array();
         } else {
            $results = array('success' => true);
            $Items=$pxml['Items']['Item'];
         }
         print json_encode($this->parse_results($Items, $Opts));
         exit();
      }

      function parse_results ($Items, $Settings, $Count=100) {
         $Template = $Settings['template'];
         if (count($Items) > 0) {
            for ($counter = 0; ($counter < count($Items)) || ($counter > $Count) ; $counter++) {
               $result = $Items[$counter];
               $data = array();
               $data['asin']   = $result['ASIN'];
               $data['title']  = $result['ItemAttributes']['Title'];
               $data['artist'] = isset($result['ItemAttributes']['Artist']) ? $result['ItemAttributes']['Artist'] :
                           (isset($result['ItemAttributes']['Author']) ? $result['ItemAttributes']['Author'] :
                            (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : '-'));
               $data['artist'] = $this->remove_parents($data['artist']);
               $data['manufacturer'] = isset($result['ItemAttributes']['Manufacturer']) ? $result['ItemAttributes']['Manufacturer'] : '-';

               if (isset($result['MediumImage']))
                 $data['thumb'] = $result['MediumImage']['URL'];
               else
                 $data['thumb'] = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";

               if (isset($result['LargeImage']))
                  $data['image'] = $result['LargeImage']['URL'];
               else
                  $data['image'] = $data['thumb'];

               $data['url']     = $result['DetailPageURL'];
               $data['rank']    = $result['SalesRank'];
               $data['rating']  = isset($result['CustomerReviews']['AverageRating']) ? $result['CustomerReviews']['AverageRating'] : '-';
               $data['price']   = $result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice'];
               $data['id']      = $result['asin'];
               $data['type']    = 'Amazon';
               $data['link']    = amazon_make_links('asin='.$data['asin'].'&text=' . $data['title']);
               $data['image_link']    = amazon_make_links('image_class='. $Settings['image_class'].'&image='. $data['image'] . '&asin='.$data['asin'].'&text=' .$data['title']);
               $data['thumb_link']    = amazon_make_links('image_class='. $Settings['image_class'].'&thumb='. $data['thumb'] . '&asin='.$data['asin'].'&text='. $data['title']);

               $media_ids = $this->find_attachments( $data['asin'] );

               if ($media_ids) {
                  $data['media_id'] = $media_ids[0]->ID;
                  $data['downloaded'] = '1';
               } else {
                  $data['media_id'] = 0;
                  $data['downloaded'] = '0';
               }
               $data['template'] = $this->process_template($data, htmlspecialchars_decode (stripslashes($Template)));
               $results['items'][$data['asin']] = $data;
            }
         }
         return $results;
     }

      function process_template ($data, $template) {
         foreach ($data as $key => $string)
            $template = str_replace('%'. strtoupper($key) . '%', $string, $template);
         return $template;
      }

      function find_attachments ($asin, $number = '-1') {

         // Do we already have a local image ? 
         $args = array( 'post_type' => 'attachment', 'numberposts' => $number, 'post_status' => 'all', 'no_filters' => true,
                        'meta_query' => array(array('key' => 'amazon-link-ASIN', 'value' => $asin)));
         $query = new WP_Query( $args );
         $media_ids = $query->posts;
         if ($media_ids) {
            return $media_ids;
         } else {
            new WP_Error(__('No images found','amazon-link'));
         }
      }

      function removeImage() {
         $Opts = $_POST;

         /* Do we have this image? */
         $media_ids = $this->find_attachments( $Opts['asin'] );

         if (!$media_ids) {
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

         if ($media_ids) {
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

      function grab_image ($ASIN, $post_id = 0) {

         $ASIN = strtoupper($ASIN);

         $request = array("Operation"=>"ItemLookup","ItemId"=>$ASIN,"ResponseGroup"=>"Small,Images","IdType"=>"ASIN","MerchantId"=>"Amazon");

         $pxml = amazon_query($request);
         $result = $pxml['Items']['Item'];
         $r_title  = $result['ItemAttributes']['Title'];
         $r_artist = isset($result['ItemAttributes']['Artist'])  ? $result['ItemAttributes']['Artist'] :
                     (isset($result['ItemAttributes']['Author'])  ? $result['ItemAttributes']['Author'] :
                     (isset($result['ItemAttributes']['Director'])  ? $result['ItemAttributes']['Director'] :
                      (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : '-')));
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