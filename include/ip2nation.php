<?php

if (!class_exists('AmazonWishlist_ip2nation')) {
   class AmazonWishlist_ip2nation {

/*****************************************************************************************/

      /// Set up paths and other constants

      function __construct() {

         $this->db = 'ip2nation';
         $this->remote_file = 'http://www.ip2nation.com/ip2nation.zip';
         $upload_dir = wp_upload_dir();
         $this->temp_sql_dir  = $upload_dir['basedir'] . '/ip2nation';
         $this->temp_sql_file = $this->temp_sql_dir . '/ip2nation.sql';
      }

      function init() {
         // Not currently needed
      }
/*****************************************************************************************/

      /// Check if the database is install and if it is up-to-date, and
      /// construct an appropriate status message

      function status () {
         global $wpdb;

         $sql = "SHOW TABLE STATUS WHERE Name LIKE '". $this->db ."'";
         $db_info = $wpdb->get_row($sql);
         if ($db_info != NULL) {
            $ip2nationdb_ts = ($db_info->Update_time != NULL) ? strtotime($db_info->Update_time) : strtotime($db_info->Create_time);
            $ip2nationdb_time = date('D, d M Y H:i:s', $ip2nationdb_ts);
            $uninstall = True;
         } else {
            $ip2nationdb_ts = False;
            $uninstall = False;
         }

         $result = wp_remote_head($this->remote_file, array('timeout' => 1));
         if (is_wp_error($result))
         {
            $ip2nationfile_ts = False;
         } else {
            $ip2nationfile_ts = strtotime($result['headers']['last-modified']);
            $ip2nationfile_time = date('D, d M Y H:i:s', $ip2nationfile_ts);
            $ip2nationfile_length = $result['headers']['content-length'];
         }

         $install = False;
         if (!$ip2nationdb_ts) {
            if (!$ip2nationfile_ts) {
               $message = __('You currently do not have <b>ip2nation</b> installed, and the remote file is unavailable', 'amazon-link');
            } else {
               $install = True;
               $message = sprintf(__('You currently do not have <b>ip2nation</b> installed, the latest version available is dated: %s','amazon-link'),$ip2nationfile_time);
            }
         } else {
            if (!$ip2nationfile_ts) {
               $message = sprintf(__('You\'re <b>ip2nation</b> database was last updated on %1$s, the remote file is unavailable.', 'amazon-link'), $ip2nationdb_time);
            } else {
               if ($ip2nationfile_ts > $ip2nationdb_ts) {
                  $message = sprintf(__('<b>WARNING!</b> You\'re <b>ip2nation</b> database is out of date. It was last updated on %1$s, the latest version available is dated: %2$s', 'amazon-link'), $ip2nationdb_time, $ip2nationfile_time);
                  $install = True;
               } else {
                  $message = sprintf(__('You\'re <b>ip2nation</b> database is up to date. (It was last updated on %1$s, the latest version available is dated: %2$s).', 'amazon-link'), $ip2nationdb_time, $ip2nationfile_time);
               }
            }
         }

         return array( 'Uninstall' => $uninstall, 'Install' => $install, 'Message' => $message);
      }

/*****************************************************************************************/

      /// Download and install the ip2nation mysql database

      function install ($url, $args) {
         global $wpdb, $wp_filesystem;
         
         /*
          * Use WordPress WP_Filesystem methods to install DB
          */
         
         // Check Credentials
         if (false === ($creds = request_filesystem_credentials($url,NULL,false,false,$args))) {
            // Not yet valid, a form will have been presented - drop out.
            return array ( 'HideForm' => true);
         }
         
         if ( ! WP_Filesystem($creds) ) {
            // our credentials were no good, ask the user for them again
            request_filesystem_credentials($url,NULL,true,false,$args);
            return array ( 'HideForm' => true);
         }
         
         $temp_file = download_url($this->remote_file);
         if (is_wp_error($temp_file))
            return array ( 'Message' => __('ip2nation install: Failed to download file: ','amazon-link') . $temp_file->get_error_message());
         
         $result = unzip_file($temp_file, $this->temp_sql_dir);
         if (is_wp_error($result)) {
            unlink ($temp_file);
            return array ( 'Message' => __('ip2nation install: Failed to unzip file: ','amazon-link') . $result->get_error_message());
         }
         
         $sql = $wp_filesystem->get_contents($this->temp_sql_file);
         
         // Install the database
         $index = 0;
         $queries =0;
         $end = strpos($sql, ';', $index)+1;
         $query = substr ($sql, $index, ($end-$index));
         while ($query !== FALSE) {
            if ($wpdb->query($query) === FALSE)
            return array ( 'Message' => sprintf(__('ip2nation install: Database downloaded and unzipped but failed to install [%s]','amazon-link'), $wpdb->last_error));
            $index=$end;
            $queries++;
            $end = strpos($sql, ';', $index)+1;
            $query = substr ($sql, $index, ($end-$index));
         }

         $wp_filesystem->delete($this->temp_sql_dir,true);
         return array ( 'Message' =>  sprintf(__('ip2nation install: Database downloaded and installed successfully. %s queries executed.','amazon-link'), $queries));
      }
/*****************************************************************************************/

      function uninstall () {
         global $wpdb;
         $query = 'DROP TABLE ip2nation,ip2nationCountries;';
         if ($wpdb->query($query) === FALSE) {
            return sprintf(__('ip2nation uninstall: Database failed to uninstall [%s]','amazon-link'), $wpdb->last_error);
         } else {
            return sprintf(__('ip2nation uninstall: Databases successfully uninstalled.','amazon-link'));
         }
      }

/*****************************************************************************************/

      function get_cc ($ip = FALSE) {
         global $wpdb, $_SERVER;

         if ($ip === FALSE)
            $ip = $_SERVER['REMOTE_ADDR'];

         $sql = "SHOW TABLE STATUS WHERE Name LIKE '". $this->db ."'";
         $db_info = $wpdb->get_row($sql);
         if ($db_info != NULL) {
            $sql = 'SELECT country FROM ' . $this->db .' WHERE ip < INET_ATON(%s) ORDER BY ip DESC LIMIT 0,1';
            return $wpdb->get_var($wpdb->prepare($sql, $ip));
         } else {
            return NULL;
         }

      }

/*****************************************************************************************/

   }
}

?>