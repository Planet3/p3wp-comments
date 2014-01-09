<?php
/*
Plugin Name: P3 Comments
Plugin URI: http://planet3.org
Version 1.0
Author: Dan Moutal
Author URI: http://ofdan.ca
License: GPLv2
 */

register_activation_hook( __FILE__, 'p3_comments_activation' ); // Hook to run installation function

function p3_comments_activation() {
	//Runs on plugin activation
	global $wp_version;
	if ( version_compare( $wp_version, '3.6' , '<' ) ) {
		wp_die( 'This plugin needs a more recent version of WordPress' );
	}
}

register_deactivation_hook( __FILE__, 'p3_comments_deactivation' ); // Hook to run deactivation function

function p3_comments_deactivation() {
	//Runs on plugin deactivation
}

// Removes the recent comments widget from the main wordpress admin dashboard
// This is needed since the plugin currently breaks this widget
function custom_remove_dashboard_widgets() {
	global $wp_meta_boxes;
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'] );
}

add_action( 'wp_dashboard_setup', 'custom_remove_dashboard_widgets' );

// Add new heading to comments tables
add_filter( 'manage_edit-comments_columns', 'p3_comment_status_column' );
function p3_comment_status_column( $columns ) {
	$columns['p3_comment_status'] = __( 'P3 Comment Status' );
	return $columns;
}

// Print comment meta for new meta column
add_action( 'manage_comments_custom_column', 'p3_comment_column', 10, 2 );
function p3_comment_column( $column, $comment_ID ) {
	if ( 'p3_comment_status' != $column )
		return;
	echo esc_attr( get_comment_meta( $comment_ID, 'p3_comment_status', true ) );
}

// Replace the quickedit action to call an additional expandedOpen function
add_filter( 'comment_row_actions', 'p3_quick_edit_action', 10, 2 );
function p3_quick_edit_action( $actions, $comment ) {
	global $post;
	$actions['quickedit'] = '<a onclick="commentReply.close();if( typeof(expandedOpen) == \'function\' ) expandedOpen('.$comment->comment_ID.');commentReply.open( \''.$comment->comment_ID.'\',\''.$post->ID.'\',\'edit\' );return false;" class="vim-q" title="'.esc_attr__( 'Quick Edit' ).'" href="#">' . __( 'Quick Edit' ) . '</a>';
	return $actions;
}

// Add some javscript into the footer for the edit comments page
add_action('admin_footer-edit-comments.php', 'p3_comment_quick_edit_javascript');
function p3_comment_quick_edit_javascript() {

	// Create nonce
	$nonce = wp_create_nonce( 'p3_cmeta' );
	?>
	<script type="text/javascript">
	function expandedOpen(id) {
		// Pull the data from the new column and pass that along to our new input
		var mv = jQuery('tr#comment-'+id+' .column-p3_comment_status').text() || '';
		if ( mv == 'shadow' ) {
			jQuery('#comment-meta-p3-custom-status').prop('checked', true);
		}
		else {
			jQuery('#comment-meta-p3-custom-status').prop('checked', false);
		}
	}
	function saveCommentMeta() {
		// Build the data object for the ajax action setup to update comment meta
		var cid = jQuery('#comment_ID').val() || 0;
		if ( jQuery('#comment-meta-p3-custom-status').prop('checked') ) {
			var mv  = 'shadow';
		}
		else {
			var mv = ''
		}
		var data = {
			action: "update_p3_comment_status",
			_ajax_nonce: "<?php echo $nonce; ?>",
			comment_ID: cid,
			comment_metaKey: 'p3_comment_status',
			comment_metaValue: mv
		};
		// Post data to ajax
		jQuery.post( ajaxurl, data, function(response) {
			if( 'success' != response )
				return;
			// Valid response, update the new table column(so you don't need to refresh)
			jQuery('tr#comment-'+cid+' .column-p3_comment_status').text(mv);
			// Close the quickedit
			commentReply.close();
		});
	};
	</script>
	<?php
}

// Setup ajax callback for the new action
add_action( 'wp_ajax_update_p3_comment_status', 'update_p3_comment_status_ajax' );
function update_p3_comment_status_ajax() {

	// Check nonce
	check_ajax_referer( 'p3_cmeta' );
	// Check expected fields are there
	foreach( array( 'comment_ID', 'comment_metaKey', 'comment_metaValue' ) as $field )
		if( !isset( $_POST[$field] ) )
			die;

	// Validate ID
	$commentID  = absint( $_POST['comment_ID'] );
	if( !$commentID )
		die;

	// Validate meta key
	$commentKey = sanitize_title( $_POST['comment_metaKey'] );
	if( empty( $commentKey ) )
		die;

	// Validate meta value(or empty)
	$commentVal = sanitize_title( $_POST['comment_metaValue'] );

	// Update meta 
	update_comment_meta( $commentID, $commentKey, $commentVal );

	// Success response
	echo 'success';// Echoed value is received as the response
	// And die, expected behaviour for ajax actions
	die;
}

// Run filter on the comment reply box(necessary to add new fields into the quickedit box)
add_filter( 'wp_comment_reply', 'p3_quick_edit_menu', 10, 2 );
function p3_quick_edit_menu($str, $input) {

	extract( $input );
	$table_row = true;

	if( $mode == 'single' )
		$wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
	else 
		$wp_list_table = _get_list_table('WP_Comments_List_Table');

	// Get editor string
	ob_start();
		$quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,spell,close' );
	wp_editor( '', 'replycontent', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings, 'tabindex' => 104 ) );
	$editorStr = ob_get_contents();
	ob_end_clean();


	// Get nonce string
	ob_start();    
	wp_nonce_field( "replyto-comment", "_ajax_nonce-replyto-comment", false );
		if ( current_user_can( "unfiltered_html" ) )
		wp_nonce_field( "unfiltered-html-comment", "_wp_unfiltered_html_comment", false );
	$nonceStr = ob_get_contents();
	ob_end_clean();


	$content = '<form method="get" action="">';
	if ( $table_row ) :
		$content .= '<table style="display:none;"><tbody id="com-reply"><tr id="replyrow" style="display:none;"><td colspan="'.$wp_list_table->get_column_count().'" class="colspanchange">';
	else :
		$content .= '<div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">';
	endif;

	$content .= '
			<div id="replyhead" style="display:none;"><h5>Reply to Comment</h5></div>
			<div id="addhead" style="display:none;"><h5>Add new Comment</h5></div>
			<div id="edithead" style="display:none;">';

	$content .= '  
				<div class="inside">
					<label for="author">Name</label>
					<input type="text" name="newcomment_author" size="50" value="" tabindex="101" id="author" />
				</div>

				<div class="inside">
					<label for="author-email">E-mail</label>
					<input type="text" name="newcomment_author_email" size="50" value="" tabindex="102" id="author-email" />
				</div>

				<div class="inside">
					<label for="author-url">URL</label>
					<input type="text" id="author-url" name="newcomment_author_url" size="103" value="" tabindex="103" />
				</div>';

	// Add new quick edit fields       
	$content .= '
				
				<div style="clear:both;"></div>
				<div class="inside">

				<input type="checkbox" id="comment-meta-p3-custom-status" name="comment-meta-p3-custom-status" value="shadow" style="width:1em;" />
					<label for="comment-meta-p3-custom-status">Shadow</label>
					<a onclick="saveCommentMeta();" class="button-primary save-p3-custom-status" href="#">'.__( 'Save P3 custom comment status' ).'</a>
				</div>

				<div style="clear:both;"></div>

			</div>';

	// Add editor
	$content .= "<div id='replycontainer'>\n";   
	$content .= $editorStr;
	$content .= "</div>\n";  

	$content .= '          
			<p id="replysubmit" class="submit">
			<a href="#comments-form" class="cancel button-secondary alignleft" tabindex="107">Cancel</a>
			<a href="#comments-form" class="save button-primary alignright" tabindex="106">
			<span id="addbtn" style="display:none;">Add Comment</span>
			<span id="savebtn" style="display:none;">Update Comment</span>
			<span id="replybtn" style="display:none;">Submit Reply</span></a>
			<img class="waiting" style="display:none;" src="'.esc_url( admin_url( "images/wpspin_light.gif" ) ).'" alt="" />
			<span class="error" style="display:none;"></span>
			<br class="clear" />
			</p>';

		$content .= '
			<input type="hidden" name="user_ID" id="user_ID" value="'.get_current_user_id().'" />
			<input type="hidden" name="action" id="action" value="" />
			<input type="hidden" name="comment_ID" id="comment_ID" value="" />
			<input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
			<input type="hidden" name="status" id="status" value="" />
			<input type="hidden" name="position" id="position" value="'.$position.'" />
			<input type="hidden" name="checkbox" id="checkbox" value="';

	if ($checkbox) $content .= '1'; else $content .=  '0';
	$content .= "\" />\n";
		$content .= '<input type="hidden" name="mode" id="mode" value="'.esc_attr( $mode ).'" />';

	$content .= $nonceStr;
	$content .="\n";

	if ( $table_row ) :
		$content .= '</td></tr></tbody></table>';
	else :
		$content .= '</div></div>';
	endif;
	$content .= "\n</form>\n";
	return $content;
}

add_action( 'add_meta_boxes_comment', 'p3_comment_status_add_meta_box' );
function p3_comment_status_add_meta_box() {
	add_meta_box( 'p3-comment-status', __( 'P3 Comment Status' ), 'p3_comment_status_meta_box_cb', 'comment', 'normal', 'high' );
}

function p3_comment_status_meta_box_cb( $comment ) {
	$title = get_comment_meta( $comment->comment_ID, 'p3_comment_status', true );
	wp_nonce_field( 'p3_comment_update', 'p3_comment_update', false );
	?>
	<p>
		<label for="p3_comment_status">Shadow</label>;
		<input type="checkbox" id="comment-meta-p3-custom-status" name="p3_comment_status" value="shadow" class="" />
	</p>
	<?php if ( get_comment_meta( $comment->comment_ID, 'p3_comment_status', true ) == 'shadow' ) : ?>
		<script type="text/javascript">
			jQuery('#comment-meta-p3-custom-status').prop('checked', true);
		</script>
	<?php endif;
}

add_action( 'edit_comment', 'p3_comment_status_edit_comment' );
function p3_comment_status_edit_comment( $comment_id ) {
	if( ! isset( $_POST['p3_comment_update'] ) || ! wp_verify_nonce( $_POST['p3_comment_update'], 'p3_comment_update' ) )
		return;
	if( isset( $_POST['p3_comment_status'] ) ) {
		update_comment_meta( $comment_id, 'p3_comment_status', esc_attr( $_POST['p3_comment_status'] ) );
	}
	else {
		delete_comment_meta( $comment_id, 'p3_comment_status' );
	}
}


/*
* Front end editing
 */
add_filter( 'comment_text', 'p3_comment_moderation_buttons' );
function p3_comment_moderation_buttons ( ) {
if ( ( get_the_author_meta( 'ID' ) == get_current_user_id() ) || current_user_can( 'moderate_comments' ) ) {
		// Adds moderation buttons under every comment
		$comment_id = get_comment_ID();
		$text = get_comment_text();
		$nonce = wp_create_nonce( 'p3_comment_moderation' );

		$p3_approve_link = admin_url('admin-ajax.php?action=p3_comment_moderation_save&p3moderation=approve&comment_id='. $comment_id.'&nonce='.$nonce);
		$p3_shadow_link  = admin_url('admin-ajax.php?action=p3_comment_moderation_save&p3moderation=shadow&comment_id='. $comment_id .'&nonce='.$nonce);
		$p3_spam_link    = admin_url('admin-ajax.php?action=p3_comment_moderation_save&p3moderation=spam&comment_id='. $comment_id .'&nonce='.$nonce);


		$p3_edit_links = '<div class="p3-edit-links"><a class="p3-comment-moderation" href="' . $p3_approve_link . '" data-comment_id="' . $comment_id . '" data-nonce="' . $nonce . '">Approve</a> | <a class="p3-comment-moderation" href="' . $p3_shadow_link . '" data-comment_id="' . $comment_id . '" data-nonce="' . $nonce . '">Shadow</a> | <a class="p3-comment-moderation" href="' . $p3_spam_link . '" data-comment_id="' . $comment_id . '" data-nonce="' . $nonce . '">Spam</a></div>';
		return $text . $p3_edit_links;
	}
	else{
		return get_comment_text();
	}
}


//Function to mark comments as approved, shaddow or spam 
add_action("wp_ajax_p3_comment_moderation_save", "p3_comment_moderation_save");
function p3_comment_moderation_save() {
	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'p3_comment_moderation' ) ) {
		exit("Go away!"); //If nonce check fails stop everything
	}

	$p3_mod = $_REQUEST[ "p3moderation" ];
	$comment_id = $_REQUEST[ "comment_id" ];

	if ( $p3_mod == "approve" ) {
		$success1 = wp_set_comment_status( $comment_id, 'approve' );
		$success2 = delete_comment_meta( $comment_id, 'p3_comment_status' );
		if ( $success1 && $success2 ) {
			$success = true;
		}
		else {
			$success = false;
		}
	//$success = $success1 && $success2;
	}
	if ( $p3_mod == "shadow" ) {
		$success1 = wp_set_comment_status( $comment_id, 'approve' );
		$success2 = update_comment_meta( $comment_id, 'p3_comment_status', 'shadow' );
		$success = $success1 && $success2;
	}
	if ( $p3_mod == "spam" ) {
		$success = wp_set_comment_status( $comment_id, 'spam' );
	}


	if ( $success == true ) {
		$result['mod_action'] = $p3_mod;
		$result['type'] = 'success';
		$result['comment_id'] = $_REQUEST["comment_id"];
	}
	else {
		$result['mod_action'] = $p3_mod;
		$result['type'] = 'error';
		$result['comment_id'] = $_REQUEST["comment_id"];
		//echo "Something didn't work!!!";
	}

	if ( defined('DOING_AJAX') && DOING_AJAX ) {
		wp_send_json( $result );
	}
	else {
		header("Location: ".$_SERVER["HTTP_REFERER"]);
	}


	//die(); // this is required to return a proper result
}


// //Functions to mark comments as approved, shaddow or spam 
// add_action("wp_ajax_p3_comment_approve", "p3_comment_approve");
// function p3_comment_approve() {
// 	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'p3_comment_moderation' ) ) {
// 		exit("Go away!"); //If nonce check fails stop everything
// 	}
// 	$comment_id = $_REQUEST["comment_id"];
// 	$success = wp_set_comment_status( $comment_id, 'approve' );
// 	$success = update_comment_meta( $comment_id, 'p3_comment_status', '' );

// 	if ( $success == true ) {
// 		$result['type'] = 'success';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 	}
// 	else {
// 		$result['type'] = 'error';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 		echo "Something didn't work";
// 	}

// 	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
// 		$result = json_encode($result);
// 		echo $result;
// 	}
// 	else {
// 		header("Location: ".$_SERVER["HTTP_REFERER"]);
// 	}

// 	die(); // this is required to return a proper result
// }

// add_action("wp_ajax_p3_comment_shadow", "p3_comment_shadow");
// function p3_comment_shadow() {
// 	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'p3_comment_moderation' ) ) {
// 		exit("Go away!"); //If nonce check fails stop everything
// 	}
// 	$comment_id = $_REQUEST["comment_id"];
// 	$success = wp_set_comment_status( $comment_id, 'approve' );
// 	$success = update_comment_meta( $comment_id, 'p3_comment_status', 'shadow' );

// 	if ( $success == true ) {
// 		$result['type'] = 'success';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 	}
// 	else {
// 		$result['type'] = 'error';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 		echo "Something didn't work";
// 	}

// 	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
// 		$result = json_encode($result);
// 		echo $result;
// 	}
// 	else {
// 		header("Location: ".$_SERVER["HTTP_REFERER"]);
// 	}

// 	die(); // this is required to return a proper result
// }

// add_action("wp_ajax_p3_comment_spam", "p3_comment_spam");
// function p3_comment_spam() {
// 	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'p3_comment_moderation' ) ) {
// 		exit("Go away!"); //If nonce check fails stop everything
// 	}
// 	$comment_id = $_REQUEST["comment_id"];
// 	$success = wp_set_comment_status( $comment_id, 'spam' );

// 	if ( $success == true ) {
// 		$result['type'] = 'success';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 	}
// 	else {
// 		$result['type'] = 'error';
// 		$result['comment_id'] = $_REQUEST["comment_id"];
// 		echo "Something didn't work";
// 	}

// 	if( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
// 		$result = json_encode($result);
// 		echo $result;
// 	}
// 	else {
// 		header("Location: ".$_SERVER["HTTP_REFERER"]);
// 	}

// 	die(); // this is required to return a proper result
// }


add_action( 'init', 'p3_comment_meta_script_enqueuer' );
function p3_comment_meta_script_enqueuer() {
	wp_register_script( "p3_comment_meta", plugins_url().'/p3wp-comments/js/p3_comment_meta.js', array('jquery') );
	wp_localize_script( "p3_comment_meta", 'p3cmetaAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'p3_comment_meta' );
}
