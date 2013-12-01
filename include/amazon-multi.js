function al_gen_multi (id, term, def, chan) {
   var content = "";

   for (var cc in AmazonLinkMulti.country_data) {
      var type = term[cc].substr(0,1);
      var arg  = term[cc].substr(2);
      var tld  = AmazonLinkMulti.country_data[cc].tld;
      var tag  = AmazonLinkMulti.channels[chan]['tag_'+cc];
      
      if (cc != def) {
         url = AmazonLinkMulti.link_templates[type];
         url = url.replace(/%CC%#/g, '');
         url = url.replace(/%CC%/g, cc);
         url = url.replace(/%MANUAL_CC%/g, cc);
         url = url.replace(/%ARG%/g, arg);
         url = url.replace(/%TLD%/g, tld);
         url = url.replace(/%TAG%/g, tag);
         content = content +'<a '+AmazonLinkMulti.target+' href="' + url + '"><img src="' + AmazonLinkMulti.country_data[cc].flag + '"></a>';
      }
   }
   al_link_in (id, content);
}
