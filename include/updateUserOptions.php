<?php

   if ( !current_user_can( 'edit_user', $user ) )
      return false;

   $delete_options = True;
   $channel_opts = $this->get_user_option_list();

   foreach ( $channel_opts as $opt => $data) {
      if (isset($data['Default'])) {
         $options[$opt] = $_POST[$opt];
         if ($_POST[$opt] != '') $delete_options = False;
      }
   }

   if ($delete_options) {
      $this->save_user_options($user, '');
   } else {
      $this->save_user_options($user, $options);
   }
?>