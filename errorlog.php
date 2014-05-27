<?php

/*
Plugin Name: Error Log
Plugin URI: http://minilua.com/
Description: Watch error logs from the admin interface. Zero configuration and using ajax auto-updating.
Author: Edenilson Lisboa
Author URI: http://minilua.com/
Version: 1.0
*/

function errorlog_add_menu()
	{
		if(function_exists('add_management_page'))
			{
				add_management_page('Error Log', 'Error Log', 'edit_others_posts', basename(__FILE__), 'errorlog_menu');
			}
	}

function errorlog_menu()
	{
		//error_reporting(E_ALL);
		//ini_set('display_errors', 'On');
?>
<script type="text/javascript">
function updateErrLog(){
    if((typeof jQuery)!="undefined"){
        jQuery("#updtmsg").show();
        jQuery.ajax({
    	  url: document.URL,
    	  success: function(data) {
            jQuery("#updtmsg").hide();
    	    window['lastData'] = data;
            if(window['lastData']!=data){
                data = lastData.match(new RegExp("LOGSTART[^>]+>(.*widefat.*)<[^>]+LOGEND"));
                if(data.length>1){
    	           jQuery('table.widefat,div.wrap > p').eq(0).replaceWith(data[1]);
    	       }
            }
    	  }
    	});
     }
 } 
 setInterval(updateErrLog, 5000);
</script>
<div class="wrap" style="padding:10px 0 0 10px;text-align:left">
    <?php

	$logs = array(ABSPATH.'/error_log');
 
    if ($dh = opendir(ABSPATH)) {
        while (($file = readdir($dh)) !== false) {
            if(file_exists(ABSPATH.'/'.$file.'/error_log')) {
                $logs[] = ABSPATH.'/'.$file.'/error_log';
            }
        }
        closedir($dh);
    }

    if(file_exists(ABSPATH.'/wp-content/debug.log')){
        $logs[] = ABSPATH.'/wp-content/debug.log';
    }
    
    $default_log = ini_get('error_log');
    if($default_log && file_exists($default_log)){
        $logs[] = $default_log;
    }
    
    $logs = array_unique($logs);

    if( !$logs )
		echo '<p>' . __('Error logging disabled.', 'error-log-widget') . ' <a href="http://codex.wordpress.org/Editing_wp-config.php#Configure_Error_Log">' . __('Configure error log', 'error-log-widget') . '</a></p>';

    ?>
	<form method="POST" onsubmit="setTimeout(function (){e=document.getElementById('send');e.value='Cleaning...';e.disabled=true},500)">
		<p>
            <input type="submit" value=" Clean it all! " id="send" />
            <span id="updtmsg" style="font-style: italic;margin-left: 7px;display:none;">Updating...</span>
        </p>
    </form>
    <?php

    if($_SERVER['REQUEST_METHOD']=='POST'){
        foreach($logs as $log){
            file_put_contents($log, '');
        }
    }

	$count = 1000;
	$lines = array();

	foreach ($logs as $log)
		$lines = array_merge($lines, errorlog_last_lines($log, $count));

	$lines = array_map('trim', $lines);
	$lines = array_filter($lines);

	if( empty($lines) ) {

		echo '<p>' . __('No errors found... Yet.', 'error-log-widget') . '</p>';

		return;
	}

	foreach($lines as $key => $line) {

		if( false != strpos($line, ']') )
			list($time, $error) = explode(']', $line, 2);
		else
			list($time, $error) = array('', $line);

		$time = trim($time, '[]');
		$error = trim($error);
		$lines[$key] = compact('time', 'error');
	}

	if( count($logs) > 1 ) {

		uasort($lines, 'errorlog_time_field_compare');
		$lines = array_slice($lines, 0, $count);
	}

	?><!-- LOGSTART //--><table class="widefat"><?php

		foreach( $lines as  $line ) {

			$error = esc_html( $line['error'] );
			$time = esc_html( $line['time'] );

			if( !empty($error) )
				echo( "<tr><td>{$time}</td><td>{$error}</td></tr>" );
		}

	?>
	</table><!-- LOGEND //-->
</div>
<?php
	}

function errorlog_time_field_compare($a, $b) {

	if ($a == $b)
		return 0;

	return ( strtotime($a['time']) > strtotime($b['time']) ) ? -1 : 1;
}


/**
 * Reads lines from end of file. Memory-safe.
 *
 * @link http://stackoverflow.com/questions/6451232/php-reading-large-files-from-end/6451391#6451391
 *
 * @param string $path
 * @param integer $line_count
 * @param integer $block_size
 * 
 * @return array
 */
function errorlog_last_lines($path, $line_count, $block_size = 512)	{
	$lines = array();
	$leftover = "";
    $data = "";
	$fh = fopen($path, 'r');
	fseek($fh, 0, SEEK_END);
	do {
		$can_read = $block_size;
		if (ftell($fh) <= $block_size)
			$can_read = ftell($fh);
		fseek($fh, -$can_read, SEEK_CUR);
		if($can_read) $data = fread($fh, $can_read);
		$data .= $leftover;
		fseek($fh, -$can_read, SEEK_CUR);
		$split_data = array_reverse(explode("\n", $data));
		$new_lines = array_slice($split_data, 0, -1);
		$lines = array_merge($lines, $new_lines);
		$leftover = $split_data[count($split_data) - 1];
	}
	while (count($lines) < $line_count && ftell($fh) != 0);
	if (ftell($fh) == 0)
		$lines[] = $leftover;
	fclose($fh);
	return array_slice($lines, 0, $line_count);
}

add_action('admin_menu', 'errorlog_add_menu');
