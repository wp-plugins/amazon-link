/**
 * Handle: wpAmazonLinkAdmin
 * Version: 0.0.1
 * Deps: jquery
 * Enqueue: true
 */


var wpAmazonLinkAdmin = function () {}


wpAmazonLinkAdmin.prototype = {
    options           : {},

    toggleAdvanced : function(event) {
        var collection = jQuery(event).find("input[name='multi_cc'], input[name='localise'], input[name='image'], input[name='thumb'], input[name='remote_images']");
        var defaults   = jQuery(event).find("input[name='defaults']:checked").length;
        if (defaults) {
           jQuery(collection).parent().parent().hide();
        } else {
           jQuery(collection).parent().parent().show();
        }
    },

    generateShortCode : function() {
        var content = this['options']['content'];
        delete this['options']['content'];

        /* If use 'defaults' is set then do not force these options */
        if (this['options']['defaults'] == "1") {
           delete this['options']['multi_cc'];
           delete this['options']['localise'];
           delete this['options']['image'];
           delete this['options']['thumb'];
        }

        delete this['options']['defaults'];

        /* If user has selected the button to add images, then override global setting */
        if (this['options']['image_override'] == "1") {
           this['options']['image'] = "1";
        }
        if (this['options']['thumb_override'] == "1") {
           this['options']['thumb'] = "1";
        }

        /* If not using local images, then use the remote URL's for the images */
        if (this['options']['remote_images'] == "1") {
           if ((this['options']['image'] == "1") && (this['options']['image_url'] != undefined)) {
              this['options']['image'] = this['options']['image_url'];
           }
           if ((this['options']['thumb'] == "1") && (this['options']['thumb_url'] != undefined)){
              this['options']['thumb'] = this['options']['thumb_url'];
           }
        }

        /* Delete temporary options only used by the java exchange */
        delete this['options']['remote_images'];
        delete this['options']['image_override'];
        delete this['options']['thumb_override'];
        delete this['options']['image_url'];
        delete this['options']['thumb_url'];
        
        /* Now generate the short code with what is left */
        var attrs = '';
        var sep = '';
        jQuery.each(this['options'], function(name, value){
            if (value != '') {
                attrs += sep + name + '=' + value;
                sep = '&';
            }
        });
        return '[amazon ' + attrs + ']'
    },

    sendToEditor      : function(f, options) {
        var collection = jQuery(f).find("input[id^=AmazonLinkOpt]");
        var $this = this;
        collection.each(function () {
            if (this.type == 'checkbox') {
               $this['options'][this.name] = this.checked ? "1" : "0";
            } else {
               $this['options'][this.name] = this.value;
            }
        });
        if (options != undefined) {
           jQuery.extend($this['options'], options);
        }
        send_to_editor(this.generateShortCode());
        return false;
    }
}

var wpAmazonLinkAd = new wpAmazonLinkAdmin();

jQuery(document).ready( function() {
    jQuery('.mypostbox h3').prepend('<a class="togbox">+</a> ');
    jQuery('.mypostbox').prepend('<div class="handlediv myhandle"><br></div> ');
    jQuery('.myhandle').click( function() {
        jQuery(jQuery(this).parent().get(0)).toggle();
    });
});
