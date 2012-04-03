<?php

   $Settings = $this->getSettings();

   // If using local tags then just process the ones on this page otherwise search categories.
   if (strcasecmp($categories, "local") != 0) {
      // First process all post content for the selected categories
      $content = '';
      $lastposts = get_posts("numberposts=$last&cat=$categories");
      foreach ($lastposts as $id => $post) {
         $content .= $post->post_content;
      }
      $saved_tags = array_unique($this->tags);

      $this->tags = array();
      $this->content_filter($content, FALSE);
      $this->Settings = $Settings;                   // Reset settings as content filter will overwrite them
   }


   if ((count($this->tags) != 0) && is_array($this->tags))
   {
      $this->tags = array_unique($this->tags);
      $output = '<div class="amazon_container">';
      if (strcasecmp($Settings['wishlist_type'],'similar') == 0) {
 
         $request = array("Operation" => "CartCreate",
                          "MergeCart" => "True",
                          "ResponseGroup" => "CartSimilarities",
                          "IdType"=>"ASIN",
                          "MerchantId"=>"Amazon");
         // Get the Cart Similarities for the items found
         $counter=1;
         foreach ($this->tags as $asin)
         {
             if (strlen($asin) > 8) {
                $request["Item." . $counter . ".ASIN"] = $asin;
                $request["Item." . $counter . ".Quantity"] = 1;
                $counter++;
             }
         }

         $pxml = $this->doQuery($request);
         if (is_array($pxml['Cart']['SimilarProducts']['SimilarProduct']))
         {
            $Items=$pxml['Cart']['SimilarProducts']['SimilarProduct'];
         } else {
            $output .= '<p>'.__('Amazon query failed to return any results - Have you configured the AWS settings?', 'amazon-link').'</p>';
            $output .= '<!-- '. print_r($request, true) . '-->';
            $Items=array();
         }
         
         foreach ($Items as $Item => $Details)
            $ASINs[] = $Details['ASIN'];

      } else if (strcasecmp($Settings['wishlist_type'],'random') == 0) {
         shuffle($this->tags);
         $ASINs = $this->tags;
      } else if (strcasecmp($Settings['wishlist_type'],'multi') == 0) {

         $ASINs = $this->tags;

      }
      
      if ( is_array($ASINs) && !empty($ASINs)) {
         $ASINs = array_slice($ASINs,0,$Settings['wishlist_items']);
         $Settings['live'] = 1;
         if (!isset($Settings['template'])) $Settings['template'] = $Settings['wishlist_template'];
         $output .= $this->make_links($ASINs, $Settings['text'], $Settings);
         $output .= "</div>";
      }

   } else {
      $output .= "<!--". sprintf(__('No [amazon] tags found in the last %1$s posts in categories %2$s', 'amazon-link'), $last, $categories). "--!>";
   }
   if (isset($saved_tags)) {
      $this->tags = $saved_tags;
   }

   return $output;

?>