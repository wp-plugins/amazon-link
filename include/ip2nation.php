<?php

if (!class_exists('AmazonWishlist_ip2nation')) {
   class AmazonWishlist_ip2nation {

/*****************************************************************************************/

      /// Set up paths and other constants

      function __construct() {

         $this->db = 'ip2nation';
         $this->remote_file = 'http://www.ip2nation.com/ip2nation.zip';
         $upload_dir = wp_upload_dir();
         $this->temp_zip_file = $upload_dir['basedir'] . '/ip2nation.zip';

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
            $ip2nationdb_ts = strtotime($db_info->Update_time);
            $ip2nationdb_time = date('D, d M Y H:i:s', $ip2nationdb_ts);
         } else {
            $ip2nationdb_ts = False;
         }

         if( !class_exists( 'WP_Http' ) ) 
            include_once( ABSPATH . WPINC. '/class-http.php' );

         $request = new WP_Http;
         $result = $request->head( $this->remote_file, array('timeout' => 5));
         if ($result instanceof WP_Error )
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

         return array( 'Install' => $install, 'Message' => $message);
      }

/*****************************************************************************************/

      /// Download and install the ip2nation mysql database

      function install () {
         global $wpdb;

          // Download zip file...
         if( !class_exists( 'WP_Http' ) ) 
            include_once( ABSPATH . WPINC. '/class-http.php' );

          $request = new WP_Http;
          $result = $request->request( $this->remote_file );
          if ($result instanceof WP_Error )
             return __('ip2nation install: Failed to Download remote database file.','amazon-link');

          // Save file to temp directory
          $zip_content = $result['body'];
          $zip_size = file_put_contents ($this->temp_zip_file, $zip_content);
          if (!$zip_size)
             return sprintf(__('ip2nation install: Failed to open temporary  file (%s).','amazon-link'), $this->temp_zip_file) ;

          // Unzip the file
          if (!($zfh = zip_open ($this->temp_zip_file)) || !($zpe = zip_read ($zfh))) {
             unlink ($this->temp_zip_file);
             return __('ip2nation install: Failed to open local zip file', 'amazon-link');
          }

          if (($unzip_size = zip_entry_filesize($zpe)) > 5000000) {
             unlink ($this->temp_zip_file);
             return sprintf(__('ip2nation install: Failed - Content of the Zip file looks too large (%s bytes).', 'amazon-link'), $unzip_size);
          }

          // Read the unzipped content into memory and free up the zip file.
          $sql = zip_entry_read($zpe, $unzip_size);
          unlink ($this->temp_zip_file);

          // Install the database
          $index = 0;
          $end = strpos($sql, ';', $index)+1;
          $query = substr ($sql, $index, ($end-$index));
          while ($query !== FALSE) {
             if ($wpdb->query($query) === FALSE)
                return sprintf(__('ip2nation install: Database downloaded and unzipped but failed to install [%s]','amazon-link'), $wpdb->last_error);
             $index=$end;
             $end = strpos($sql, ';', $index)+1;
             $query = substr ($sql, $index, ($end-$index));
          }
          return sprintf(__('ip2nation install: Database downloaded and installed successfully. %s queries executed.','amazon-link'), $index);
      }

/*****************************************************************************************/

      function get_cc ($ip = FALSE) {
         global $wpdb, $_SERVER;
         
         if ($ip === FALSE)
            $ip = $_SERVER['REMOTE_ADDR'];


         $sql = "SHOW TABLE STATUS WHERE Name LIKE '". $this->db ."'";
         $db_info = $wpdb->get_row($sql);
         if ($db_info != NULL) {
            $sql = 'SELECT country FROM ' . $this->db .' WHERE ip < INET_ATON("'.$ip.'") ORDER BY ip DESC LIMIT 0,1';
            return $wpdb->get_var($sql);
         } else {
            return NULL;
         }

      }

/*****************************************************************************************/

   }
}

?>