<?php

   $Settings = $this->getSettings();
   $local_info = $this->get_local_info($Settings);

   // If using local tags then just process the ones on this page otherwise search categories.
   if (strcasecmp($categories, "local") != 0) {
      // First process all post content for the selected categories
      $content = '';
      $lastposts = get_posts("numberposts=$last&cat=$categories");
      foreach ($lastposts as $id => $post) {
         $content .= $post->post_content;
      }
      $saved_tags = $this->tags;

      $this->tags = array();
      $this->content_filter($content, FALSE);
      $this->Settings = $Settings;                   // Reset settings as content filter will overwrite them
   }


   if ((count($this->tags) != 0) && is_array($this->tags))
   {
      $output = '<div class="amazon_container">';
      if (strcasecmp($Settings['wishlist_type'],'similar') == 0) {
 
         $request = array("Operation" => "CartCreate",
                          "MergeCart" => "True",
                          "ResponseGroup" => "CartSimilarities",
                          "IdType"=>"ASIN",
                          "MerchantId"=>"Amazon");
         // Get the Cart Similarities for the items found
         $counter=1;
         $unique_asins = array();
         foreach ($this->tags as $asins)
         {
             $asin = isset($asins[$local_info['cc']]) ? $asins[$local_info['cc']] : (isset($asins[$Settings['default_cc']]) ? $asins[$Settings['default_cc']] : '');

             if ((strlen($asin) > 8) && !in_array($asin,$unique_asins)) {
                $request["Item." . $counter . ".ASIN"] = $asin;
                $request["Item." . $counter . ".Quantity"] = 1;
                $counter++;
                $unique_asins[] = $asin;
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

      } else {
         if (strcasecmp($Settings['wishlist_type'],'random') == 0) {
            shuffle($this->tags);
            $ASINs = $this->tags;
         } else if (strcasecmp($Settings['wishlist_type'],'multi') == 0) {
            $ASINs = $this->tags;
         }
         $unique_asins = array();
         for ($index=0; $index < count($ASINs); $index++) {
            $asin = isset($ASINs[$index][$local_info['cc']]) ? $ASINs[$index][$local_info['cc']] : (isset($ASINs[$index][$Settings['default_cc']]) ? $ASINs[$index][$Settings['default_cc']] : '');
            if (in_array($asin, $unique_asins)) {
               unset($ASINs[$index]);
            } else {
               $unique_asins[] = $asin;
            }
         }
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