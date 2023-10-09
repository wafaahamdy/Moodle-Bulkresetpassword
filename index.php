<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Bulk user registration script from a comma separated file
 *
 * @package     tool_resetpasswords
 * @copyright   2023 Wafaa Hamdy <eng.wafaa.hamdy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->libdir.'/csvlib.class.php');   /// required to handle CSV functions
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/resetpasswords/form.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/uploaduser/locallib.php');

require_login();
$systemcontext   = context_system::instance();
// Check access control.
if ($USER->id) {
    echo ($USER->id) ;
    //  require_login() MUST NOT be used here, it would result in infinite loop!
    if (!has_capability('tool/resetpasswords:bulkresetpassword', $systemcontext)) {
        throw new \moodle_exception('accessdenied', 'admin');
   //  throw new \moodle_exception('nopermissions','', '', 'bulk reset password') ;
    }

}  else { throw new \moodle_exception('usernotavailable') ;}



// set the url of the page
$returnurl = new moodle_url('/admin/tool/resetpasswords/index.php');

///   Page Header
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadusers', 'tool_resetpasswords'));


$iid         = optional_param('iid', '', PARAM_INT);


if (empty($iid)) { /// this is the first load to page
    
// intiate form object
 $mform1 = new upload_list_form();  
 
 if ($formdata = $mform1->get_data()) {   
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');

        $content = $mform1->get_file_content('userfile');
//  bool false if error, count of data lines if ok; use get_error() to get error string
        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
      //  echo json_encode($readcount);
        $csvloaderror = $cir->get_error();
        unset($content);

        if (!is_null($csvloaderror)) {
            throw new \moodle_exception('csvloaderror', '', $returnurl, $csvloaderror);
        }
       

    } else {
 // if no data is sent, just display the form
        $mform1->display();
        echo $OUTPUT->footer();
        die;
    }
} else {
   $cir = new csv_import_reader($iid, 'uploaduser');
}


/// Start working on Reset password process


// 1) Test if columns are ok.

 
$filecolumns =$cir->get_columns();

    if (empty($filecolumns)) {
        $cir->close();
        $cir->cleanup();
        throw new \moodle_exception('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($filecolumns) > 1 || !(in_array('username', $filecolumns)) )  {
        $cir->close();
        $cir->cleanup();
        
     throw new \moodle_exception('csvloaderror', 'error', $returnurl, 'Only one column with header username is allowed');
    }


$dd = $cir-> init();

echo '<table    class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('uploadusersresult', 'tool_uploaduser').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header" scope="col">'.$filecolumns[0].'</th>';
        echo '<th class="header" scope="col"> Action </th>';
        echo '</tr>';
 
// make the 
global $DB ;
$generated = 0 ;   // counter for number of processed data
$escaped = 0;   // counter for number of escaper data


for ($i=0; $i<$readcount-1; $i++){
   $usernames = $cir-> next(); 
     echo '<tr class="r0">';
     echo '<td scope="col">'. $usernames[0] . '</td>';
       $cuser =   get_complete_user_data('username', $usernames[0]);
       if($cuser){
               
     //   $user = get_user_by_username($usernames[0]) ;

          set_user_preference('create_password',1, $cuser);
          echo '<td scope="col"> Password is generated in cron </td>';
          $generated ++;
  
   }  else {
    echo '<td scope="col"> User not found </td>'; 
    $escaped ++;
   }
echo '<tr/>' ;
}

echo "<div>
Password reseted users: $generated <br/>
Escaped users: $escaped <br/>
Total : ".($generated+$escaped)."
</div>";
 

echo $OUTPUT->continue_button($returnurl);

echo $OUTPUT->footer();
