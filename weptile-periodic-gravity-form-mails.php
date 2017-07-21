<?php
/*
 Plugin Name: Gravity Forms Periodic Notification E-Mails
 Plugin URI: http://wordpress.org/plugins/gravity-forms-periodic-notification-e-mails-by-weptile/
 Description: Sends periodic e-mails for Gravity Forms entries created within the period. Daily, weekly, monthly udpates instead of 1 e-mail per form entry.
 Version: 1.2.2
 Author: Weptile
 Author URI: http://weptile.com
 */


/*
 * wp_get_schedules() can be used to list all of the intervals currently available
 * in wp-cron
 * http://codex.wordpress.org/Function_Reference/wp_get_schedules
 * 
 * The following code can be used to display all the current schedule intervals
 */
//echo '<pre>'; print_r(wp_get_schedules()); echo '</pre>';

/*
 * The following intervals are built-in to wp-cron:
 *      hourly
 *      twicedaily
 *      daily
 * 		two days
 * 		weekly
 */





/*
 * When creating a new scheduled event in WordPress you must give it a hook to
 * call when the scheduled time arrives. This is a custom hook that you must 
 * create yourself. Let's create that hook now
 * 
 * First parameter names the hook
 * Second parameter is the name of our function to call
 */

register_activation_hook(__FILE__, 'wdgfm_activation');
add_action( 'wdgfm_send_mail_hook', 'wdgfm_send_mail' );

function wdgfm_activation() {

}

add_filter('cron_schedules', 'weptile_add_scheduled_interval');

add_action('admin_menu' , 'wdgfm_create_menu');

function wdgfm_create_menu(){

    add_menu_page('Weptile Periodic Gravity Form Mail','Form Mail','manage_options','wdgfm_settings_page','wdgfm_settings_page');

    add_submenu_page('wdgfm_settings_page','Display Scheduled Tasks','Mail Tasks','manage_options','wdgfm_tasks_page','wdgfm_tasks_page');



    

}
function wdgfm_tasks_page(){
    wdgfm_print_tasks();
}

function add_wdgfm_event($form_id,$fields,$mail_address,$time_period,$start_hour,$schedule_name){
        $args=array(array('form_id'=>$form_id,'fields'=>$fields,'mail_address'=>$mail_address,'schedule_name'=>$schedule_name));
		
		switch($time_period){
		
		case 'hourly':
		$time_interval='hourly';
		break;
		case 'twicedaily':
		$time_interval='twicedaily';
		break;
		case 'daily':
		$time_interval='daily';
		break;
		case 'twodays':
		$time_interval='twodays';
		break;
		case 'weekly':
		$time_interval='weekly';
		break;
		default:
		$time_interval='daily';
		break;
		}
		if(!empty($start_hour)){
			$timestamp=mktime($start_hour,0,0,date("m"),date("d"),date("Y"));
		}else{
			$timestamp=time();
		}
        if(!wp_next_scheduled('wdgfm_send_mail_hook')){

            wp_schedule_event( $timestamp, $time_interval, 'wdgfm_send_mail_hook',$args );
        }
		 

}
function weptile_add_scheduled_interval($schedules) {

	

    $schedules['twodays'] = array('interval'=>172800, 'display'=>__('Two days'));

	$schedules['weekly'] = array('interval'=>604800, 'display'=>__('Weekly'));

 

    return $schedules;

  }

function wdgfm_settings_page(){

    require_once(GFCommon::get_base_path() . "/gravityforms.php");

    require_once(GFCommon::get_base_path() . "/tooltips.php");

    // check POST parameters after submit button is clicked
    if(isset($_POST["export_lead_mail"])){
        check_admin_referer("rg_start_export", "rg_start_export_nonce");
        //see if any fields chosen
        if (empty($_POST["export_field"])){
            echo "<div class='error' style='padding:15px;'>" . __("Please select the fields to be exported", "gravityforms") . "</div>";
            return;
        }
        if (empty($_POST["time_period"]) ){
            echo "<div class='error' style='padding:15px;'>Bad time interval</div>";
            return;
        }
        if (empty($_POST["mail_address"])||GFCommon::is_invalid_or_empty_email($_POST["mail_address"])){
            echo "<div class='error' style='padding:15px;'>Please check the mail address</div>";
            return;
        }

        $form_id=$_POST["export_form"];
        $fields = $_POST["export_field"];
        $mail_address=$_POST["mail_address"];
		$time_period=$_POST["time_period"];
		$start_hour=$_POST["start_hour"];
		$schedule_name=$_POST["schedule_name"];
        add_wdgfm_event($form_id,$fields,$mail_address,$time_period,$start_hour,$schedule_name);
        echo '<div id="message" class="updated"><p>Schedule added</p></div>';


    }
    
    //get forms and prepare settings page

        wp_enqueue_script("sack");
        wp_print_scripts();
        ?>
        <script type='text/javascript' src='<?php echo GFCommon::get_base_url()?>/js/datepicker.js?ver=<?php echo GFCommon::$version ?>'></script>
        <script type="text/javascript">
            function SelectExportForm(formId){
                if(!formId)
                    return;

                var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "rg_select_export_form" );
                mysack.setVar( "rg_select_export_form", "<?php echo wp_create_nonce("rg_select_export_form") ?>" );
                mysack.setVar( "form_id", formId);
                mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while selecting a form", "gravityforms")) ?>' )};
                mysack.runAJAX();

                return true;
            }

            function EndSelectExportForm(aryFields){
                if(aryFields.length == 0)
                {
                    jQuery("#export_field_container, #export_date_container, #export_submit_container").hide()
                    return;
                }

                var fieldList = "<li><input type='checkbox' onclick=\"jQuery('.gform_export_field').attr('checked', this.checked); jQuery('#gform_export_check_all').html(this.checked ? '<strong><?php _e("Deselect All", "gravityforms") ?></strong>' : '<strong><?php _e("Select All", "gravityforms") ?></strong>'); \"> <label id='gform_export_check_all'><strong><?php _e("Select All", "gravityforms") ?></strong></label></li>";
                for(var i=0; i<aryFields.length; i++){
                    fieldList += "<li><input type='checkbox' id='export_field_" + i + "' name='export_field[]' value='" + aryFields[i][0] + "' class='gform_export_field'> <label for='export_field_" + i + "'>" + aryFields[i][1] + "</label></li>";
                }
                jQuery("#export_field_list").html(fieldList);
                //jQuery("#export_date_start, #export_date_end").datepicker({dateFormat: 'yy-mm-dd'});

                jQuery("#export_field_container, #export_date_container, #export_submit_container").hide().show();
            }
        </script>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css"/>

        <div class="wrap">

            <div class="icon32" id="gravity-export-icon"><br></div>

            <h2><?php _e("Schedule Periodic Gravity Form Mail Report", "gravityforms") ?></h2>
            

            <p class="textleft">Select a form below to get entries in selected time interval via email. Once you have selected a form you may select the fields you would like to recieve . When you click the schedule button below, plugin will send an e-mail  with selected time interval, form and fields.Don't forget to deactivate notifications in Gravity Form settings page.</p>
            <div class="hr-divider"></div>
            <form method="post" style="margin-top:10px;">
                <?php echo wp_nonce_field("rg_start_export", "rg_start_export_nonce"); ?>
                <table class="form-table">
                  <tr valign="top">

                       <th scope="row"><label for="export_form"><?php _e("Select A Form", "gravityforms"); ?></label> <?php gform_tooltip("export_select_form") ?></th>
                        <td>

                          <select id="export_form" name="export_form" onchange="SelectExportForm(jQuery(this).val()); jQuery('#schedule_name').val(jQuery('#export_form option:selected').text());">
                            <option value=""><?php _e("Select a form", "gravityforms"); ?></option>
                            <?php
                            $forms = RGFormsModel::get_forms(null, "title");
                            foreach($forms as $form){
                                ?>
                                <option value="<?php echo absint($form->id) ?>"><?php echo esc_html($form->title) ?></option>
                                <?php
                            }
                            ?>
                        </select>

                        </td>
                    </tr>
                  <tr valign="top">
                    <th scope="row">Schedule Name:</th>
                    <td><input type="text" style="width: 300px; " name="schedule_name" id="schedule_name" value=""/></td>
                  </tr>
                  <tr id="export_field_container" valign="top" style="display: none;">
                       <th scope="row"><label for="export_fields"><?php _e("Select Fields", "gravityforms"); ?></label> <?php gform_tooltip("export_select_fields") ?></th>
                        <td>
                            <ul id="export_field_list">
                            </ul>
                        </td>
                   </tr>
                    <tr>
                        <th scope="row"><label for="mail_address">E-mail Address: </label>
                            <td>
                            <input type="text" style="width: 300px; " name="mail_address" value="<?php echo get_option('admin_email'); ?>"/>
                            </td>
                        </th>
                    </tr>
					<tr>
                        <th scope="row"><label for="time_period">Time Period: </label>
                            <td>
								<select name="time_period" style="width:150px;">
									<option value="hourly">Hourly</option>
									<option value="twicedaily">Twice Daily</option>
									<option value="daily">Daily</option>
									<option value="twodays">Two days</option>
									<option value="weekly">Weekly</option>
								</select>
                            
                            </td>
                        </th>
                    </tr>
					<tr>
					  <th scope="row"><label for="start_hour">Start Hour:</label>
					  <td><select name="start_hour" style="width:150px;">
                      <?php for($i=0;$i<24;$i++){?>
					    <option value="<?php echo $i;?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT);?>:00</option>
                      <?php }?>
				      </select></td>
					  <td>                    
				  </tr>
                
                  
                </table>
                <ul>
                    <li id="export_submit_container" style="display:none; clear:both;">
                        <br/><br/>
                        <input type="submit" name="export_lead_mail" value="Schedule" class="button-primary"/>
                        <span id="please_wait_container" style="display:none; margin-left:15px;">
                            <img src="<?php echo GFCommon::get_base_url()?>/images/loading.gif"> <?php _e("Exporting entries. Please wait...", "gravityforms"); ?>
                        </span>

                        <iframe id="export_frame" width="1" height="1" src="about:blank"></iframe>
                    </li>
                </ul>
            </form>            
			<div class="hr-divider"></div>
			<div>Does this plugin help you out?
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
					<input type="hidden" name="cmd" value="_s-xclick">
					<input type="hidden" name="hosted_button_id" value="9RUSUW5XBBESE">
					<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
					<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
				</form>
			</div>
        </div>
        <?php


    
}

/*
 * Now to schedule the event. WordPress can be a little naive in scheduling
 * events, so you MUST check to make sure this event has not already been 
 * scheduled. Failure to do so can result in hundreds of entries for this
 * event existing in wp-cron
 */



/*
 * This is the function that is called from the custom hook 'bl_cron_hook' we
 * created earlier. It does really cool stuff!
 */


function wdgfm_send_mail($args){
    
		
        $form_id=$args['form_id'];
        $fields=$args['fields'];
        $mail_address=$args['mail_address'];
		
        require_once(GFCommon::get_base_path() . "/export.php");        
        $form = RGFormsModel::get_form_meta($form_id);        
        $form_id = $form["id"];
        $read=0;
       // $form = GFExport::add_default_export_fields($form);
        $entry_count = RGFormsModel::get_lead_count($form_id, "", null, $read, $start_date, $end_date);
        $page_size = 200;
        $offset = 0;
        $attachment='';
        echo $entry_count;
        while($entry_count > 0){
            $leads = RGFormsModel::get_leads($form_id,"date_created", "DESC", "", $offset, $page_size, null, $read, false, $start_date, $end_date);
            echo $entry_count;
            foreach($leads as $lead){
                foreach($fields as $field_id){
                    switch($field_id){
                        case "date_created" :
                            $lead_gmt_time = mysql2date("G", $lead["date_created"]);
                            $lead_local_time = GFCommon::get_local_timestamp($lead_gmt_time);
                            $value = date_i18n("Y-m-d H:i:s", $lead_local_time);
                        break;
                        default :
                            $long_text = "";
                            if(strlen($lead[$field_id]) >= (GFORMS_MAX_FIELD_LENGTH-10)){
                                $long_text = RGFormsModel::get_field_value_long($lead, $field_id, $form);
                            }

                            $value = !empty($long_text) ? $long_text : $lead[$field_id];
							$value = apply_filters("gform_export_field_value", $value, $form_id, $field_id, $lead);
                        break;
                    }

                    if(isset($field_rows[$field_id])){
                        $list = empty($value) ? array() : unserialize($value);

                        foreach($list as $row){
                            $row_values = array_values($row);
                            $row_str = implode("|", $row_values);
                            $lines .= '"' . str_replace('"', '""', $row_str) . '"' . $separator;
                        }

                        //filling missing subrow columns (if any)
                        $missing_count = intval($field_rows[$field_id]) - count($list);
                        for($i=0; $i<$missing_count; $i++)
                            $lines .= '""' . $separator;

                    }
                    else{
                        $value = maybe_unserialize($value);
                        if(is_array($value))
                            $value = implode("|", $value);

                        $lines .= '"' . str_replace('"', '""', $value) . '"' . $separator;
                    }
                }
                $lines = substr($lines, 0, strlen($lines)-1);
                $lines.= "\n";
                //mark entries as read
                RGFormsModel::update_leads_property($lead, "is_read", 1);
            }
           
            $offset += $page_size;
            $entry_count -= $page_size;

            if ( !seems_utf8( $lines ) )
                $lines = utf8_encode( $lines );

            $attachment.= $lines;
            $lines = "";
        }
        
        $to = $mail_address;
        $subject='Weptile Gravity Forms Periodic Report ';
        if(!$attachment==''){
            wp_mail($to, $subject, $attachment);
			}
        


        
     

    
}


/*
 * Whenever you create a wp-cron item in a plugin you will want to remove
 * any scheduled tasks when your plugin is deactivated or WordPress will
 * continue to attempt to execute the wp-cron task.
 * 
 * What we will do here is setup a function which will run if the plugin
 * is deactivated and remove the scheduled task
 */
register_deactivation_hook( __FILE__, 'wdgfm_deactivate' );

/*
 * This is the deactivation function we setup previously
 */
function wdgfm_deactivate() {
    //remove all events=streventname

    $strEventName='wdgfm_send_mail_hook';
    $arrCronEvents = _get_cron_array();
	foreach ($arrCronEvents as $nTimeStamp => $arrEvent)
		if (isset($arrCronEvents[$nTimeStamp][$strEventName])) unset( $arrCronEvents[$nTimeStamp] );
	_set_cron_array( $arrCronEvents );
    
}


/*
 * Simple helper function that prints out all existing tasks in the wp-cron list
 */
function wdgfm_print_tasks() {
	
	if($_GET['act']=="del"){
		//$original_args = array();
		//wp_unschedule_event($_GET['id'],'wdgfm_send_mail_hook',$original_args);
		$crons = _get_cron_array();
		unset($crons[$_GET['id']]);	
		_set_cron_array($crons);
		echo '<div id="message" class="updated"><p>Schedule deleted succesfully...</p></div>';
	}
   
    $strEventName='wdgfm_send_mail_hook';
    $cron = _get_cron_array();
	$schedules = wp_get_schedules();
	$date_format = 'M j, Y @ G:i';
	?>
	<div class="wrap" id="cron-gui">
	<h2>Mail Tasks</h2>
    
	<table class="widefat fixed">
		<thead>
			<tr>
				<th scope="col">Next Run (GMT/UTC)</th>
				<th scope="col">Schedule</th>
				<th scope="col">Schedule Name</th>
				<th scope="col">E-mail</th>
				<th scope="col">Start Hour</th>
				<th scope="col">Current Time</th>
				<th scope="col">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cron as $timestamp => $cronhooks ) { ?>
                <?php if (isset($cron[$timestamp][$strEventName])){?>
				    <?php foreach ( (array) $cronhooks as $hook => $events ) { ?>
					    <?php foreach ( (array) $events as $event ) { ?>
						    <tr>
							    <td nowrap="nowrap">
								    <?php echo date_i18n( $date_format, wp_next_scheduled( $hook ) ); ?>
                                </td>
							    <td nowrap="nowrap">
								    <?php 
								    if ( $event[ 'schedule' ] ) {
									    echo $schedules[ $event[ 'schedule' ] ][ 'display' ]; 
								    } else {
									    ?>One-time<?php
								    }
								    ?>
							    </td>
							    <td nowrap="nowrap"><?php echo $event['args'][0]['schedule_name']; ?></td>
							    <td nowrap="nowrap"><?php echo $event['args'][0]['mail_address']; ?></td>
							    <td nowrap="nowrap"><?php echo date("H:i",$timestamp);?></td>
							    <td nowrap="nowrap"><?php echo date("H:i:s"); ?></td>
							    <td nowrap="nowrap"><a class="button-primary" href="<?php echo admin_url('admin.php?page=wdgfm_tasks_page&act=del&id='.$timestamp);?>">DELETE</a></td>
						    </tr>
					    <?php } ?>
				    <?php } ?>
                <?php } ?>
			<?php } ?>
		</tbody>
	</table>
	        
			<div class="hr-divider"></div>
			<div>Does this plugin help you out?
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
					<input type="hidden" name="cmd" value="_s-xclick">
					<input type="hidden" name="hosted_button_id" value="9RUSUW5XBBESE">
					<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
					<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
				</form>
			</div>
    </div>
<?
   
}