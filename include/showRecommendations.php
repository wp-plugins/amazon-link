<?php
   // First process all post content for the selected categories
   $content = '';
   $lastposts = get_posts("numberposts=$last&cat=$categories");
   foreach ($lastposts as $id => $post) {
      $content .= $post->post_content;
   }

   unset($this->tags);
   $this->tags = array();

   $this->contentFilter($content, FALSE, FALSE);

   $request = array("Operation" => "CartCreate",
                    "MergeCart" => "True",
                    "ResponseGroup" => "CartSimilarities",
                    "IdType"=>"ASIN",
                    "MerchantId"=>"Amazon",
                    "AssociateTag"=>$this->Settings['tag']);

   $output = '<div class="amazon_container">';
   if (count($this->tags) != 0)
   {
      $counter=1;
      foreach ($this->tags as $asin)
      {
          $request["Item." . $counter . ".ASIN"] = $asin;
          $request["Item." . $counter . ".Quantity"] = 1;
          $counter++;
      }

      $pxml = aws_signed_request($this->Settings['tld'], $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
      if ($pxml === False) {
         echo "<PRE>Did not work.</PRE>";
      } else {
         $SimilarProducts=$pxml['Cart']['SimilarProducts']['SimilarProduct'];
      }


      for ($counter = 0; $counter < 4; $counter++) {
         $ASIN = $SimilarProducts[$counter]['ASIN'];
         $request = array("Operation"=>"ItemLookup","ItemId"=>$ASIN,"ResponseGroup"=>"Small,Images,Offers,Reviews,SalesRank","IdType"=>"ASIN","MerchantId"=>"Amazon","AssociateTag"=>$this->Settings['tag']);

         $pxml = aws_signed_request($this->Settings['tld'], $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
         $result = $pxml['Items']['Item'];
         $r_title  = $result['ItemAttributes']['Title'];
         $r_artist = isset($result['ItemAttributes']['Artist'])  ? $result['ItemAttributes']['Artist'] :
                     (isset($result['ItemAttributes']['Author'])  ? $result['ItemAttributes']['Author'] :
                      (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : '-'));

         if (isset($result['MediumImage']))
           $r_s_url  = $result['MediumImage']['URL'];
         else
           $r_s_url  = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";

         $r_url    = $result['DetailPageURL'];
         $r_rank   = $result['SalesRank'];
         $r_rating = $result['CustomerReviews']['AverageRating'];
         $r_price  = $result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice'];

         $output .= "<div style='width:100%;height:130px; margin: 3px; border-bottom: 1px dashed;' class='amazon_prod'>\n";
         $output .= "<div style='height:7em;float:right;border:1px dotted; padding:5px;margin-right:10px; width:7em'><A style='text-align:center;' href='$r_url'><IMG style='margin-left:auto; margin-right:auto; display: block; height:7em' class='amazon_pic' src='$r_s_url'></a></div>\n";
         $output .= "<div style='width:65%; float:left'><p style='margin:0; line-height: 1em;'><a href='$r_url'>$r_title</a></p>";
         $output .= "<p style='margin:0; line-height: 1em;'>by $r_artist </p>";
         $output .= "<p style='margin:0; margin-top:4.5em; line-height: 1em;'>Rank/Rating: $r_rank/$r_rating</p>";
         $output .= "<p style='margin:0; line-height: 1em;'><b>Price <span style='color:red;'>$r_price</span></b></p></div></div>\n";
      } 
   } else {
      $output .= "<p>No [amazon] tags found in the last '$last' posts in categories '$categories'</p>";
   }
   $output .= "</div>";
   return $output;

?>