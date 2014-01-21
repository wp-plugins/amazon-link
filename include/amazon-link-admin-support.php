<?php

/*
Copyright 2012-2013 Paul Stuttard (email : wordpress_amazonlink@ redtom.co.uk)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! class_exists ( 'Amazon_Link_Admin_Support' ) ) {
   class Amazon_Link_Admin_Support extends AmazonWishlist_For_WordPress {

      // Constructor for the Admin Support
      function __construct () {
         
         // Call Parent Constructor - still need all frontend operations
         parent::__construct (); 
         
         // Hook in the admin menu registration
         add_action ( 'admin_menu', array ( $this, 'admin_menu' ) );

         // Register hooks to perform options installation and removal & plugin initialisation
         register_activation_hook( __FILE__, array($this, 'install'));
         register_uninstall_hook(  __FILE__, array('Amazon_Link_Admin_Support', 'uninstall'));
         
      }

      /*****************************************************************************************/
      // On WordPress initialisation - load text domain and register styles & scripts
      function init () {

         // Register default channel rule creation filter - needed for Upgrading Settings 7->8
         add_filter('amazon_link_save_channel_rule', array($this, 'create_channel_rules'), 10,4);
         
         // Call Parent Inititialisation - still need to do frontend initialisation
         parent::init ();
         
         $settings = $this->getSettings();

         /* load localisation  */
         load_plugin_textdomain('amazon-link', $this->plugin_dir . '/i18n', $this->plugin_dir . '/i18n');

         /* Initialise dependent classes */
         $this->form = new AmazonWishlist_Options;
         $this->form->init($this);                     // Need to register form styles
         $this->search = new AmazonLinkSearch;
         $this->search->init($this);                   // Need to register scripts & ajax callbacks

         /* Register backend scripts */
         $edit_script  = $this->URLRoot."/postedit.js";
         $admin_script = $this->URLRoot."/include/amazon-admin.js";
         wp_register_script ( 'amazon-link-edit-script',  $edit_script,  array('jquery', 'amazon-link-search'), $this->plugin_version);
         wp_register_script ( 'amazon-link-admin-script', $admin_script, false, $this->plugin_version);
         
      }

      /*
       * Install the Plugin Options.
       *
       * On activation of plugin - used to create default settings.
       */
      function install() {
         $opts = $this->getOptions();
         $this->saveOptions( $opts );
      }

      /*
       * Uninstall the Plugin Options.
       *
       * Called on removal of plugin - used to delete all related database entries.
       */
      function uninstall() {
         global $wpdb;
         $opts = get_option(self::optionName, array());
         if ($opts['full_uninstall']) {

            /* Remove Cache */
            if (!empty($opts['cache_enabled'])) {
               $cache_table = $wpdb->prefix . self::cache_table;
               $sql = "DROP TABLE $cache_table;";
               $wpdb->query($sql);
            }
            if (!empty($opts['sc_cache_enabled'])) {
               $cache_table = $wpdb->prefix . self::sc_cache_table;
               $sql = "DROP TABLE $cache_table;";
               $wpdb->query($sql);
            }
            
            /* Delete all Options */
            self::delete_options();
         }
      }

      function delete_options() {
         delete_option(self::optionName);
         delete_option(self::channels_name);
         delete_option(self::templatesName);
      }

      // If in admin section then register options page and required styles & metaboxes
      function admin_menu () {
         
         $submenus = $this->get_menus();

         // Add plugin options page, with load hook to bring in meta boxes and scripts and styles
         $this->menu = add_menu_page(__('Amazon Link Options', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options',  $this->menu_slug, NULL, $this->icon, '102.375');

         foreach ($submenus as $slug => $menu) {
            $ID= add_submenu_page($this->menu_slug, $menu['Title'], $menu['Label'], $menu['Capability'],  $slug, array($this, 'show_settings_page'));
            $this->pages[$ID] = $menu;
            add_action( 'load-'.$ID, array(&$this, 'load_settings_page'));
            add_action( 'admin_print_styles-' . $ID, array($this,'amazon_admin_styles') );
            add_action( 'admin_print_scripts-' . $ID, array($this,'amazon_admin_scripts') );

            if (isset($menu['Scripts'])) {
               foreach ($menu['Scripts'] as $script)
                  add_action( 'admin_print_scripts-' . $ID, $script );

            }
            if (isset($menu['Styles'])) {
               add_action( 'admin_print_styles-' . $ID, $menu['Styles'] );
            }
         }

         // Add support for Post edit metabox, this requires our styles and post edit AJAX scripts.
         $post_types = get_post_types();
         foreach ( $post_types as $post_type ) {
            add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), $post_type, 'normal');
         }

         add_action( "admin_print_scripts-post.php", array($this,'edit_scripts') );
         add_action( "admin_print_scripts-post-new.php", array($this,'edit_scripts') );
         add_action( "admin_print_styles-post-new.php", array($this,'amazon_admin_styles') );
         add_action( "admin_print_styles-post.php", array($this,'amazon_admin_styles') );

         add_filter('plugin_row_meta', array($this, 'register_plugin_links'),10,2);  // Add extra links to plugins page

         $options = $this->getOptions();
         if (!empty($options['user_ids'])) {
            add_action('show_user_profile', array($this, 'show_user_options') );        // Display User Options
            add_action('edit_user_profile', array($this, 'show_user_options') );        // Display User Options
            add_action('personal_options_update', array($this, 'update_user_options')); // Update User Options
            add_action('edit_user_profile_update', array($this, 'update_user_options'));// Update User Options
         }
         
      }

      // Hooks required to bring up options page with meta boxes:
      function load_settings_page() {

         $screen = get_current_screen();

         if (!isset($this->pages[$screen->id])) return;

         $page = $this->pages[$screen->id];
         $slug = $page['Slug'];

         add_filter('screen_layout_columns', array(&$this, 'admin_columns'), 10, 2);

         wp_enqueue_script('common');
         wp_enqueue_script('wp-lists');
         wp_enqueue_script('postbox');

         if (isset($page['Metaboxes'])) {
            foreach($page['Metaboxes'] as $id => $data) {
               add_meta_box( $id, $data['Title'], $data['Callback'], $screen->id, $data['Context'], $data['Priority'], $this);
            }
         }

         add_meta_box( 'alInfo', __( 'About', 'amazon-link' ), array (&$this, 'show_info' ), $screen->id, 'side', 'core' );

         /* Help TABS only supported after version 3.3 */
         if (!method_exists( $screen, 'add_help_tab' )) {
            return;
         }

         // Add Contextual Help
         if (isset($page['Help'])) {
            $tabs = include( $page['Help']);
            foreach ($tabs as $tab) $screen->add_help_tab( $tab );
         }

         $screen->set_help_sidebar('<p><b>'. __('For more information:', 'amazon-link'). '</b></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . '">' . __('Plugin Home Page','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/">' . __('Plugin FAQ','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" title= "Guide on how to sign up for the various Amazon Programs" href="'. $this->plugin_home . 'getting-started">' . __('Getting Started','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#channels">' . __('Channels Help','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#templates">' . __('Template Help','amazon-link') . '</a></p>');

      }

      function admin_columns($columns, $id) {
         if (isset($this->pages[$id])) {
            $columns[$id] = 2;
         }
         return $columns;
      }

      function amazon_admin_styles() {
         wp_enqueue_style('amazon-link-style');
         $this->form->enqueue_styles();
      }

      function amazon_admin_scripts() {
         wp_enqueue_script('amazon-link-admin-script');
      }

      function edit_scripts() {
         wp_enqueue_script('amazon-link-edit-script');
         wp_localize_script('amazon-link-edit-script', 'AmazonLinkData', $this->get_country_data());
      }

      function register_plugin_links($links, $file) {
         if ($file == $this->base_name) {
            foreach ($this->pages as $page => $data) {
               $links[] = '<a href="admin.php?page=' . $data['Slug'].'">' . $data['Label'] . '</a>';
            }
         }
         return $links;
      }

/*****************************************************************************************/
      /// Display Content, Widgets and Pages
/*****************************************************************************************/

      function show_settings_page() {

         global $screen_layout_columns;
         $screen = get_current_screen();

         if (!isset($this->pages[$screen->id])) return;

         $page = $screen->id;
         $data = $this->pages[$page];
         $title = $data['Title'];
         $description = $data['Description'];
         $icon = isset($data['Icon']) ? $data['Icon'] : 'options-general';

         wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false );
         wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );

?>
<div class="wrap">
 <?php screen_icon($icon); ?>
  <h2><?php echo $title ?></h2>
   <p><?php echo $description ?></p>
   <div id="poststuff">
    <div id="post-body" class="metabox-holder columns-<?php echo $screen_layout_columns; ?>" >
     <div id="post-body-content">
      <?php do_meta_boxes($page, 'normal',0); ?>
     </div>
     <div id="postbox-container-1" class="postbox-container">
      <?php do_meta_boxes($page, 'side',0); ?>
     </div>
     <div id="postbox-container-2" class="postbox-container">
      <?php do_meta_boxes($page, 'advanced',0); ?>
     </div>
    </div>
   <br class="clear"/>
  </div>
 </div>
<script type="text/javascript">
//<![CDATA[
 jQuery(document).ready( function($) {
  // close postboxes that should be closed
  $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
  // postboxes setup
  postboxes.add_postbox_toggles('<?php echo $page; ?>');
 });
//]]>
</script>
<?php
      }

/*****************************************************************************************/

      // Main Options Page
      function show_options() {
         include('showOptions.php');
      }

      // Extras Management Page
      function show_extras() {
         include('showExtras.php');
      }

      // User Options Page Hooks
      function show_user_options($user) {
         include('showUserOptions.php');
      }
      function update_user_options($user) {
         include('updateUserOptions.php');
      }

/*****************************************************************************************/

      function show_default_templates() {
         include('showDefaultTemplates.php');
      }

      function show_templates() {
         include('showTemplates.php');
      }

      function show_channels() {
         include('showChannels.php');
      }

      function show_info() {
         include('showInfo.php');
      }

/*****************************************************************************************/

      function show_template_help() {
         /*
          * Populate the help popup.
          */
         $text = __('<p>Hover the mouse pointer over the keywords for more information.</p>', 'amazon-link');
         foreach ($this->get_keywords() as $keyword => $details) {
            $title = (!empty($details['Description']) ? 'title="'. htmlspecialchars($details['Description']) .'"' : '');
            $text = $text . '<p><abbr '.$title.'>%' . strtoupper($keyword) . '%</abbr></p>';
         }
         echo $text;
      }

/*****************************************************************************************/

      // Page/Post Edit Screen Widget
      function insertForm($post, $args) {
         include('insertForm.php');
      }
      
      function get_default_templates() {

         if (!isset($this->default_templates)) {
            // Default templates
            include('defaultTemplates.php');
            $this->default_templates= apply_filters('amazon_link_default_templates', $this->default_templates);
         }
         return $this->default_templates;
      }

      function get_user_option_list() {
        $option_list = array( 
            'title'       => array ( 'Type' => 'subhead', 'Value' => __('Amazon Link Affiliate IDs', 'amazon-link'), 'Description' => __('Valid affiliate IDs from all Amazon locales can be obtained from the relevant Amazon sites: ', 'amazon-link'), 'Class' => 'al_pad al_border'),
         );

         $country_data = $this->get_country_data();
         // Populate Country related options
         foreach ($country_data as $cc => $data) {
            $option_list ['tag_' . $cc] = array('Type' => 'text', 'Default' => '',
                                                'Name' => '<img style="height:14px;" src="'. $data['flag'] . '"> ' . $data['country_name'],
                                                'Hint' => sprintf(__('Enter your affiliate tag for %1$s.', 'amazon-link'), $data['country_name'] ));
            $option_list ['title']['Description'] .= '<a href="' . $data['site']. '">'. $data['country_name']. '</a>, ';
         }
         $option_list = apply_filters('amazon_link_user_option_list', $option_list, $this);
         return $option_list;
      }

      function get_menus() {
         $menus = array('amazon-link-settings'   => array( 'Slug' => 'amazon-link-settings', 
                                                           'Help' => WP_PLUGIN_DIR . '/'.$this->plugin_dir .'/'.'help/settings.php',
                                                           'Description' => __('Use this page to update the main Amazon Link settings to control the basic behaviour of the plugin, the appearance of the links and control the additional features such as localisation and the data cache. Use the Contextual Help tab above for more information about the settings.','amazon-link'),
                                                           'Title' => __('Amazon Link Settings', 'amazon-link'), 
                                                           'Label' =>__('Settings', 'amazon-link'), 
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alOptions' => array( 'Title' => __( 'Options', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_options' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'))
                                                           ),
                        'amazon-link-channels'   => array( 'Slug' => 'amazon-link-channels', 
                                                           'Help' => WP_PLUGIN_DIR . '/'.$this->plugin_dir .'/'.'help/channels.php',
                                                           'Description' => __('If you have joined the Amazon Affiliate Program then on this page you can enter your Amazon Associate Tracking Identities. If you have more than one Tracking ID on each locale then you can create extra Channels to manage them.','amazon-link'),
                                                           'Title' => __('Manage Amazon Associate IDs', 'amazon-link'), 
                                                           'Label' =>__('Associate IDs', 'amazon-link'), 
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alChannels' => array( 'Title' => __( 'Amazon Tracking ID Channels', 'amazon-link' ),
                                                                                                        'Callback' => array (&$this, 'show_channels' ), 
                                                                                                        'Context' => 'normal', 
                                                                                                        'Priority' => 'core'))
                                                           ),
                        'amazon-link-templates'  => array( 'Slug' => 'amazon-link-templates',
                                                           'Help' => WP_PLUGIN_DIR . '/'.$this->plugin_dir .'/'.'help/templates.php',
                                                           'Description' => __('Use this page to manage your templates - pre-designed html and javascript code that can be used to quickly create consistant page content. Use the editor to modify existing templates, make copies, delete or add new ones of your own design.','amazon-link'),
                                                           'Title' => __('Manage Amazon Link Templates', 'amazon-link'), 
                                                           'Label' =>__('Templates', 'amazon-link'),
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alTemplateHelp' => array( 'Title' => __( 'Template Help', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_template_help' ), 
                                                                                                       'Context' => 'side', 
                                                                                                       'Priority' => 'low'),
                                                                                 'alTemplates' => array( 'Title' => __( 'Templates', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_templates' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'),
                                                                                 'alManageTemplates' => array( 'Title' => __( 'Default Templates', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_default_templates' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'low'))
                                                           ),
                        'amazon-link-extras'     => array( 'Slug' => 'amazon-link-extras',
                                                           'Help' => WP_PLUGIN_DIR . '/'.$this->plugin_dir .'/'.'help/extras.php',
                                                           'Icon' => 'plugins',
                                                           'Description' => __('On this page you can manage user provided or requested extra functionality for the Amazon Link plugin. These items are not part of the main Amazon Link plugin as they provide features that not every user wants and may have a negative impact on your site (e.g. reduced performance, extra database usage, etc.).', 'amazon-link'),
                                                           'Title' => __('Manage Amazon Link Extras', 'amazon-link'), 
                                                           'Label' => __('Extras', 'amazon-link'), 
                                                           'Capability' => 'activate_plugins',
                                                           'Metaboxes' => array( 'alExtras' => array( 'Title' => __( 'Extras', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_extras' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'))
                                                           ));
         return apply_filters( 'amazon_link_admin_menus', $menus, $this);
      }


/*****************************************************************************************/
      // Various Options, Arguments, Templates and Channels Handling
/*****************************************************************************************/

      function create_channel_rules($rules, $channel, $data, $al)
      {
         // Extract rules 'rand = xx <CR> cat = aa,bb,cc <CR> tag = dd,ee,ff <CR> author = ID <CR> type = TYPE'

         if (empty($data['Filter'])) return $rules;

         preg_match('~rand\s*=\s*(?P<rand>\d*)~i', $data['Filter'], $matches);
         if (!empty($matches['rand']))
            $rules['rand'] = $matches['rand'];

         $author = preg_match('~author\s*=\s*(?P<author>\w*)~i', $data['Filter'], $matches);
         if (!empty($matches['author']))
            $rules['author'] = $matches['author'];

         $type   = preg_match('~type\s*=\s*(?P<type>\w*)~i', $data['Filter'], $matches);
         if (!empty($matches['type']))
            $rules['type'] = $matches['type'];

         $cat    = preg_match('~cat\s*=\s*(?P<cat>(\w*)(\s*,\s*(\w*))*)~i', $data['Filter'], $matches);
         if (!empty($matches['cat']))
            $rules['cat'] = array_map('trim',explode(",",$matches['cat']));

         $tag    = preg_match('~tag\s*=\s*(?P<tag>(\w*)(\s*,\s*(\w*))*)~i', $data['Filter'], $matches);
         if (!empty($matches['tag']))
            $rules['tag'] = array_map('trim',explode(",",$matches['tag']));

         return $rules;

      }

      function save_channels($channels) {
         if (!is_array($channels)) {
            return;
         }
         $defaults = $channels['default'];
         unset($channels['default']);
         ksort($channels);
         $channels = array('default' => $defaults) + $channels;
         foreach ($channels as $channel => &$data) {
            $data = array_filter($data);
            $data['Rule'] = apply_filters('amazon_link_save_channel_rule', array(), $channel, $data, $this);

         }
         update_option(self::channels_name, $channels);
         $this->channels = $channels;
      }
      
      function validate_keys($Settings = NULL) {
         if ($Settings === NULL) $Settings = $this->getSettings();

         $result['Valid'] = 0;
         if (empty($Settings['pub_key']) || empty($Settings['priv_key'])) {
            $result['Message'] = "Keys not set";
            return $result;
         }
         $result['Message'] = 'AWS query failed to get a response - try again later.';
         $request = array('Operation'     => 'ItemSearch', 
                          'ResponseGroup' => 'ItemAttributes',
                          'SearchIndex'   =>  'All', 'Keywords' => 'a');
         $Settings['default_local'] = 'uk';
         $Settings['localise'] = '0';
         $pxml = $this->doQuery($request, $Settings);
         if (isset($pxml['Items'])) {
            $result['Valid'] = 1;
         } else if (isset($pxml['Error'])) {
            $result['Valid'] = 0;
            $result['Message'] = $pxml['Error']['Message'];
         }
         return $result;
      }

/*****************************************************************************************/
      // Cache Facility
/*****************************************************************************************/

      function cache_install() {
         global $wpdb;
         $settings = $this->getOptions();
         if (!empty($settings['cache_enabled'])) return False;
         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "CREATE TABLE $cache_table (
                 asin varchar(10) NOT NULL,
                 cc varchar(5) NOT NULL,
                 updated datetime NOT NULL,
                 xml blob NOT NULL,
                 PRIMARY KEY  (asin, cc)
                 );";
         require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
         dbDelta($sql);
         $settings['cache_enabled'] = 1;
         $this->saveOptions($settings);
         return True;
      }

      function cache_remove() {
         global $wpdb;

         $settings = $this->getOptions();
         if (empty($settings['cache_enabled'])) return False;
         $settings['cache_enabled'] = 0;
         $this->saveOptions($settings);

         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "DROP TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_empty() {
         global $wpdb;

         $settings = $this->getOptions();
         if (empty($settings['cache_enabled'])) return False;

         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "TRUNCATE TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_flush() {
         global $wpdb;
         $settings = $this->getOptions();
         if (empty($settings['cache_enabled']) || empty($settings['cache_age'])) return False;
         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "DELETE FROM $cache_table WHERE updated < DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR);";
         $wpdb->query($sql);
      }

/*****************************************************************************************/
      // Shortcode Cache Facility
/*****************************************************************************************/

      function sc_cache_install() {
         global $wpdb;
         $settings = $this->getOptions();
         if (!empty($settings['sc_cache_enabled'])) return False;
         $cache_table = $wpdb->prefix . self::sc_cache_table;
         $sql = "CREATE TABLE $cache_table (
                 cc varchar(5) NOT NULL,
                 postid bigint(20) NOT NULL,
                 hash varchar(32) NOT NULL,
                 updated datetime NOT NULL,
                 args text NOT NULL,
                 content blob NOT NULL,
                 PRIMARY KEY  (hash, cc, postid)
                 );";
         require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
         dbDelta($sql);
         $settings['sc_cache_enabled'] = 1;
         $this->saveOptions($settings);
         return True;
      }

      function sc_cache_remove() {
         global $wpdb;

         $settings = $this->getOptions();
         if (empty($settings['sc_cache_enabled'])) return False;
         $settings['sc_cache_enabled'] = 0;
         $this->saveOptions($settings);

         $cache_table = $wpdb->prefix . self::sc_cache_table;
         $sql = "DROP TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function sc_cache_empty() {
         global $wpdb;

         $settings = $this->getOptions();
         if (empty($settings['sc_cache_enabled'])) return False;

         $cache_table = $wpdb->prefix . self::sc_cache_table;
         $sql = "TRUNCATE TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function sc_cache_flush() {
         global $wpdb;
         $settings = $this->getOptions();
         if (empty($settings['sc_cache_enabled']) || empty($settings['sc_cache_age'])) return False;
         $cache_table = $wpdb->prefix . self::sc_cache_table;
         $sql = "DELETE FROM $cache_table WHERE updated < DATE_SUB(NOW(),INTERVAL " . $settings['sc_cache_age']. " HOUR);";
         $wpdb->query($sql);
      }

   } // End Class

} // End if exists

// vim:set ts=4 sts=4 sw=4 st:
?>
