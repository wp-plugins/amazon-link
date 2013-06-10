function al_gen_multi (id, term, def, chan) {
   var content = "";

   for (var cc in AmazonLinkMulti.country_data) {
      var type = term[cc].substr(0,1);
      var arg  = term[cc].substr(2);
      if (cc != def) {
         if ( type == 'A' ) {
            var url = 'http://www.amazon.' + AmazonLinkMulti.country_data[cc].tld + '/gp/product/' + arg+ '?ie=UTF8&linkCode=as2&camp=1634&creative=6738&tag=' + AmazonLinkMulti.channels[chan]['tag_'+cc] + '&creativeASIN='+ arg;
         } else if( type == 'S') {
            var url = 'http://www.amazon.' + AmazonLinkMulti.country_data[cc].tld + '/mn/search/?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' + AmazonLinkMulti.channels[chan]['tag_'+cc] + '&field-keywords=' + arg;
         } else if( type == 'U') {
            var url = arg;
         } else if ( type == 'R'){
            var url = 'http://www.amazon.' + AmazonLinkMulti.country_data[cc].tld + '/review/' + arg+ '?ie=UTF8&linkCode=ur2&camp=1634&creative=6738&tag=' + AmazonLinkMulti.channels[chan]['tag_'+cc];
         } else {
            continue;
         }
         content = content +'<a '+AmazonLinkMulti.target+' href="' + url + '"><img src="' + AmazonLinkMulti.country_data[cc].flag + '"></a>';
      }
   }
   al_link_in (id, content);
}