=== Amazon Link ===
Contributors: paulstuttard
Donate link: http://www.houseindorset.co.uk/plugins/
Tags: Amazon, links, wishlist, recommend, shortcode, ip2nation, localise, images, media library
Requires at least: 3.1
Tested up to: 3.1
Stable tag: 1.8.1

Provides a shortcode for inserting links to Amazon products and also generate a short list (e.g. a wishlist) of related items into your site. 

== Description ==

This plugin has two main functions the first is used to quickly add a link to a product on the Amazon website using a referrer URL so that commission can be earned.

This is done by adding one of the following lines into an entry (page or post): `[amazon asin=<ASIN Number>&text=<link text>]`

Where ASIN Number is the unique amazon number used to identify products e.g. "1405235675". The Link Text is simply what you want to be shown for the link, e.g. "Mr. Good".

The second and more complex part is to create a short list of random items, related to other amazon items that are linked to in the last few posts of a particular category.

This is created by either putting the line `amazon_recommends(<Category>,<Number of Posts>)` in your template. Or putting the line `[amazon cat=<Category>&last=<Number of Posts>]` within a post or page.
Where 'Category' is a list of category ids to search within (e.g. as expected by the 'cat' argument of [query_posts](http://codex.wordpress.org/Template_Tags/query_posts#Parameters) function. The 'last' parameter is the number of posts to search through.

For example I use this feature to provide friends and family some ideas for presents, it is based on the Amazon Web Service
API and uses the 'CartSimilarities' feature to generate the list of items.

The links that are generated can optionally be localised to the Amazon store most likely to be used by the visitor to your site, this is achieved by installing the [ip2nation](http://www.ip2nation.com/) database and enabling 'localise links' option in the plugin settings.

Since the reliability of guessing your visitors best Amazon store is questionable, an alternative is to enable the 'Multinational Link' option, this will enable a small popup for each link allowing the site visitor to choose the most appropriate site (based on local or language).


On the Post and Page administrative pages there is also a box to help add the shortcodes, if you already know the ASIN then simply enter it into the ASIN input and click on 'Send To Editor'. If not then there is a facility to search Amazon, by Index, Product Title and or Product Author/Artist. There is also a facility to
add cover images from the Amazon items into the local media library as attachments to the post. These images or the remote ones hosted on Amazon can be used to insert image or thumbnail links into your posts.


== Installation ==

1. Upload 'amazon-link.zip' to the '/wp-content/plugins/' directory and unzip it.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Update the settings (at least change the default Affiliate Tag), see the FAQ.
1. Place `[amazon asin=<ASIN>&text=<LINK TEXT>]` in a post or page to create a link.
1. Place `[amazon cat=<CATEGORY LIST>&last=<NUMBER OF POSTS>]` in a page or post to create a wishlist.
1. Optionally install your own style sheet in '/amazon-link/user_styles.css', see the FAQ.
1. Optionally install the ip2nation database to enable link localisation, see the FAQ.

*Warning!*

Installation of the ip2nation database is not validated, it simply downloads the latest database file from ip2nation and zaps it into your wordpress mysql database.
If the file is corrupted or maliciously changed then this operation could completely destroy your sites mysql database, if in doubt manually download the database from [ip2nation](http://www.ip2nation.com/) and check its content, currently it consists of 2 tables ip2nation and ip2nationCountries.

== Frequently Asked Questions ==

= I've tried the plugin and it doesn't do what I want, help? =

If you think the plugin doesn't work, please try contacting me and I will endeavour to help. You can either start a forum topic on the [Wordpress site](http://wordpress.org/tags/amazon-link?forum_id=10) or 
leave a comment on my site on the plugin page [Amazon Link Page](http://www.houseindorset.co.uk/plugins/amazon-link/).

= Why doesn't the Wishlist function work? =

For this to work you must have set up a working [Amazon Web Services](http://aws.amazon.com/) account and set the AWS Public and
Private key settings to those provided in the AWS->Security Credentials->Access Credentials section.

= The list seems random? =

You must have inserted at least a few links to Amazon products using the [amazon] tag for it to generate a list of related product
suggestions.

= Can I change the styling of the wishlist? =

You can add the file 'user_styles.css' to the plugins directory overriding the default stylesheet, the wishlist has the following style elements:

*   amazon_container      - Encloses whole wishlist.
*   amazon_prod           - Encloses each list item.
*   amazon_img_container  - Encloses the item thumbnail (link+img)
*   amazon_pic            - Class of the item thumbnail IMG element
*   amazon_text_container - Encloses the item description (Title paragraphs+link + Details paragraphs)
*   amazon_details        - Encloses the item details part of the description
*   amazon_price          - Spans the item's formatted price.

= Are the links localised to match my visitor's country of origin? =

The plugin has the option to install the ip2nation database which provides a lookup of visitors IP address to country code.
If you install the database and enable the 'localise links' option then it will point the amazon link to the 'best match' amazon site based on the visitors IP address location.

Obviously the database is not perfect and some people browse through proxies or through their company's firewall so it may get the wrong country of origin. Additionally there
are only 6 major Amazon sites (UK, France, Germany, US, Japan Italy, China and Canada). So the plugin has to guess where a country's residents are most likely to shop online.

== Changelog ==

= 1.8.2 =
Add template facility, with pre-designed templates for most Amazon widgets
Add ability to create multiple links from one shortcode
Add shortcode processing in widgets
Add an option to make the links open in a new window when clicked on by a reader.
Add an option to set the length of the wishlist displayed

= 1.8.1 =
Default to using .com for aws requests when user has configured Italian or Chinese as the default domain. Note requires Wordpress version 3.1 for this release.

= 1.8 =
Add support for images into the shortcode, as well as the ability to add images to the Wordpress media library.

= 1.7 =
Rework ip2nation download function

= 1.6 =
Add support for China and Canada Associates sites, fix ip2nation status check bug.

= 1.5 =
Change the multinational link to use less in-line javascript.

= 1.4 =
Added a simple widget to the post & page new/edit screen to assist in adding shortcodes to posts, providing a facility to search Amazon.

= 1.3 =
Add link localisation through IP address lookup, and support for all 6 amazon affiliate sites not just one via a image popup.

= 1.2 =
Improve options page processing.

= 1.1 =
Move options page into 'Options' section.
Corrected stylesheet content, updated styles & provide facility to override the stylesheet.
Add internationalisation hooks into plugin.

= 1.0 =
First Release

== Upgrade Notice ==

= 1.8.2 =
Upgrade if you prefer the links to open in a new window when selected.

= 1.8.1 =
Upgrade if you want partial support for China and Italy for the product search widget.

= 1.8 =
Upgrade to this release to add support for image links, and downloading of cover images to the Wordpress media library.

= 1.7 =
Upgrade if you are having problems installing the ip2nation database

= 1.6 =
Upgrade to support the 2 new amazon associates site, and address ip2nation bug.

= 1.5 =
Upgrade if you are having problems with the javascript in the multinational link.

= 1.4 =
Upgrade to enable the metabox to allow easy insertion of the Amazon Links into Posts and Pages

= 1.3 =
Upgrade if you want multinational link support or link localisation.

= 1.2 =
Minor internal structural changes, add options link from plugin page.

= 1.1 =
Upgrade if you wish to have internationalisation (i18n) support.

== Screenshots ==

1. This is the example wishlist taken from [www.HouseInDorset.co.uk](http://www.houseindorset.co.uk/plugins/amazon-link)

2. This shows the multinational Amazon link popup.

3. This shows the Amazon Link Metabox that can be used to insert shortcode links into Posts or Pages.

== Translations ==

The plugin comes with translation support but as yet no translations are included, please refer to the WordPress Codex for more information about activating the translation.
If you want to help to translate the plugin to your language, please have a look at the i18n/amazon-link.pot file which contains all definitions and may be used to create a language specific .po file, if you do then
contact me and I will add it to the plugin ready for the next update.

== Technical ==
The plugin relies upon the php script aws_signed_request kindly crafted by [Ulrich Mierendorff](http://mierendo.com/software/aws_signed_query/) to perform the requests to the Amazon service.

The plugin has two utility classes that might be of use to other plugin designers. The first is one for generating the options page (as well as the 'Add Amazon Link' meta box). The second is an AJAX facility for performing Amazon product searches and returning an array of product details, including a facility to fill in a HTML template with various attributes of the product using the patterns %TITLE%, %PRICE%, %AUTHOR%, etc. See the plugin source files for more details on how to utilise them.

== Template Design ==

There is a settings page dedicated to the creation of new templates. Use this to create, delete and copy templates. The template content is based on standard html with additional keywords that are surrounded by '%' characters.
See the Template Help on the same page for a description of each of the keywords that can be used.

Most of the keywords are self explanatory: '%TITLE%' will expand to be the product's title, '%PRICE%' the formatted product's price, etc.

However links can be using the keyword pair '%LINK_START%' and '%LINK_END%' with the subject of the link being placed between them, for example '%LINK_START%Amazon Product%LINK_END%'. The link produced will comply with whatever settings you have used, i.e. localised to the users country, produce multinational popup, with the appropriate Amazon associate ID inserted.

There are a number of keywords that are also localised these include: '%LINK_START%' - as described above, '%TLD%' the Top Level Domain to be used '.co.uk', '.it', '.com', etc.; '%MPLACE%' - the Amazon Market place to use 'GB', 'IT', 'US', etc.; '%CC%' - the localised country code 'uk', 'it', 'us'; '%TAG%' - The amazon associate tag to use.

By specifying the 'live' - Live Data setting either in the settings page or within the amazon shortcode the data used to fill the template can be generated when the link is displayed. Or if you prefer to use static data or override some of the template content the template keywords can be specified in the shortcode.

There are a number of keywords that are only used for static data, these are '%TEXT%', '%TEXT1%', '%TEXT2%, '%TEXT3%', '%TEXT4%'.

The keyword '%ASINS%' can be used to indicate that this template will accept a string of comma separated ASINs to generate its output, for example the Amazon Carousel widget. Normally putting a list of ASINs in the shortcode will cause multiple links to be generated.

Browse the default included templates to see some examples of how the keywords can be used.

Note: the Amazon widgets are currently not supported in some locales (e.g. Canada).

== Post / Page Edit Helper ==

The plugin adds a box to the post edit and page edit section of your Wordpress site, that can be used to generate shortcodes easily and quickly. Use this to find the product you wish to link to on your site, then select the appropriate template and other settings and press the 'Insert' button. This will insert the shortcode into your post, with all the settings prefilled. 
If you are using 'live' data then it will only include keywords for user required data 'text', 'text1', etc. If you are using static data then it will also prefill the keywords with the product information retrieved from the Amazon site.

== Shortcode ==

The Amazon links are inserted onto your site by using shortcodes of the form `[amazon Link Options]`, when they are displayed on your Wordpress posts and pages, the plugin will automatically expand them into the appropriate link. The shortcodes can be used in pages, posts and text widgets.
Links can be simple text links to products, images or thumbnails, or complex javascript widgets using the template facility. The options in the shortcode are a combination of keywords used in the templates and options as set in the settings page.

The simplest shortcode is of the form `[amazon asin=0123456789]` this will insert a simple text link in your post. The 'asin' option is the only one that is mandatory as it tells the plugin which product to display and link to on the Amazon site. By entering a list of ASINs the plugin will generate a sequence of links, one for each product.

To produce more complex items there is a template facility with a number of pre-defined templates, these are populated using data entered in the shortcode or by grabbing the information from the Amazon site when the link is displayed. For example:

`[amazon asin=0340993766&template=thumbnail&title=My Favourite Le Carre&thumbnail=http://ecx.images-amazon.com/images/I/51ytv2iNEtL._SL160_.jpg]`, will generate a thumbnail image which links to the Amazon site, with the specified title.

The option 'live=1' can be used so that the standard product information is requested from the Amazon site to populate the template. `[amazon asin=0340993766&template=thumbnail&live=1]` will generate a similar thumbnail, options specified in the shortcode will always override the data extracted from the Amazon site, so if you want your own title this can be specified in the shortcode.

The default behaviour of the shortcode can be changed using the settings page - be aware that changes made in the settings page are site wide, so any shortcodes written previous to the settings change will also be affected.

If the keyword 'cat' (Category) is used then the plugin will automatically generate a list of products for you. This list either be based on items found within a particular post category or categories (cat=3,4,7) or based on items found in the content so far displayed on the current page (cat=local).
The list of items can either be a random selection of the ones found (wishlist_type=random), a short list (maximum of 5 products) of items related to the ones found (wishlist_type=similar), or a sorted list (wishlist_type=multi). The number of items in the list can be changed using the settings 'wishlist_items'.

For example putting the shortcode `[amazon cat=local&template=carousel&wishlist_type=random]` in a text widget in the sidebar will generate an Amazon Carousel widget showing random products featured in content of the current displayed page.

The products used to generate the wishlist do not need to have explicit links, by entering the shortcode `[amazon text=&asin=0340993766,0340993766,0340993766]` in the post content will provide the plugin with extra products to insert in the wishlist without cluttering the main post content with external links.

Shortcode Options:

= text =
The text used to generate the amazon link, Enter any plain string e.g. 'text=My Text'.

= thumb =
The URL used to display an image thumbnail for the amazon link in the post, if '1' is used then the thumbnail of image stored in the Wordpress media library is used.

= image =
The URL used to display a fulll size image for the amazon link in the post, if '1' is used then the fullsize image stored in the Wordpress media library is used. If both 'image' and 'thumbnail' are set then
the shortcode will cause a thumbnail image to be displayed in the post which links to the fullsize image (rather than to the Amazon store).

= image_class =
The css class used when displaying the image in the post.

= asin =
The unique Amazon product ID or IDs, of the form '1405235675'. Enter as 'asin=1405235675,0001118892'.

= cat =
When creating a wishlist you must specify the post category(s) through which to search for other Amazon links. Enter as 'cat=4,7'.

= last =
When creating a wishlist you must specify how many posts to search through for Amazon links. Enter as 'last=30'.

When using the shortcode it is possible to override the settings used in the options screen, currently available shortcode arguments include:

= localise =
Overides the 'Localise Amazon Link' setting. 0 to force the default country, 1 to force localisation.

= multi_cc =
Overides the 'Multinational Link' setting. 0 to disable the popup, 1 to enable the popup.

= default_cc =
Overides the 'Default Country' setting. Must be one of 'uk', 'us', 'ca', 'de', 'fr' or 'jp'.

= pub_key =
Overides the 'AWS Public Key' setting.

= priv_key =
Overides the 'AWS Private Key' setting.

== Settings ==

= Link Text =
If you do not specify the 'text' argument in your [amazon] shortcode, then this text will be used by default.
= Remote Images =
If this option is selected then when generating shortcodes for image links to insert into your posts, the plugin will point to ones hosted on Amazon, rather than ones in the media library.
= Image Class =
This is the class used for images displayed in Amazon image links.
= Localise Amazon Link =
If this option is selected and the [ip2nation](http://www.ip2nation.com/) database has been installed then the plugin will attempt to use the most appropriate Amazon site when creating the link, currently supports <a href="http://www.amazon.co.uk">www.amazon.co.uk</a>, <a href="http://www.amazon.com">www.amazon.com</a>, <a href="http://www.amazon.ca">www.amazon.ca</a>, <a href="http://www.amazon.de">www.amazon.de</a>, <a href="http://www.amazon.fr">www.amazon.fr</a> , <a href="http://www.amazon.it">www.amazon.it</a>, <a href="http://www.amazon.cn">www.amazon.cn</a>  and <a href="http://www.amazon.jp">www.amazon.jp</a>.
= Multinational Link =
If this option is selected then the plugin will enable a small popup menu of country specific links whenever the user's mouse rolls over the Amazon link, enabling them to select the site they feel is most appropriate.
= Default Country =
If localisation is not enabled, or has failed for some reason, then this is the default Amazon site to use for the link.
= AWS Public Key =
If you wish to use the wishlist/recommendations part of the plugin then you must have the appropriate AWS public and private key pair and enter them in these two settings. To get these keys simply register with the <a href="http://aws.amazon.com/">Amazon Web Service</a> site and this will provide you with the appropriate strings.
= AWS Private Key =
See above.

== Disclosure ==

Note if you do not update the affiliate tags then I will earn the commission on any sales your links make! For which I would be very grateful, as soon as you change the settings this will ensure all links on your site will be credited to your affiliate account(s).

== Future Updates ==

There are a number of things I want to update the plugin to do, some of which have already been done in other plugins, but not quite how I would like. I would like to bring it all together in one plugin.
Features I will be adding to the plugin in the future:

* Allow the wishlist to search for legacy amazon links not just ones embedded in the shortcode.

