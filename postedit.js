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
        var collection = jQuery(event).find("input[name='multi_cc'], input[name='localise']");
        var defaults   = jQuery(event).find("input[name='defaults']:checked").length;
        if (defaults) {
           jQuery(collection).parent().parent().hide(); /*css("border: 2px solid"); */
        } else {
           jQuery(collection).parent().parent().show(); /*css("border: 2px solid"); */
        }
    },

    generateShortCode : function() {
        var content = this['options']['content'];
        delete this['options']['content'];

        if (this['options']['defaults'] == "1") {
           delete this['options']['multi_cc'];
           delete this['options']['localise'];
        }
        delete this['options']['defaults'];

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
