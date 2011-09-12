<?php
/*
Plugin Name: Custom Taxonomy Order
Plugin URI: http://drewgourley.com
Description: Allows for the ordering of categories and custom taxonomies through a simple drag-and-drop interface.
Version: 1.0
Author: Drew Gourley
*/
function customtaxorder_menu() {   
	add_menu_page(__('Category Order'), __('Category Order'), 'manage_categories', 'customtaxorder', 'customtaxorder', plugins_url('images/cat_order.png', __FILE__), 122);
	add_submenu_page('customtaxorder', __('Order Categories'), __('Order Categories'), 'manage_categories', 'customtaxorder', 'customtaxorder');
	$args=array( '_builtin' => false ); 
	$output = 'objects';
	$taxonomies=get_taxonomies($args,$output); 
	foreach ($taxonomies as $taxonomy ) {
		add_submenu_page('customtaxorder', __('Order '.$taxonomy->label), __('Order '.$taxonomy->label), 'manage_categories', 'customtaxorder-'.$taxonomy->name, 'customtaxorder');
	}
}
function customtaxorder_css() {
	$pos_page = $_GET['page'];
	$pos_args = 'customtaxorder';
	$pos = strpos($pos_page,$pos_args);
	if ( $pos === false ) {} else {
		wp_enqueue_style('customtax', plugins_url('css/customtaxorder.css', __FILE__), 'screen');
	}
}
function customtaxorder_js_libs() {
	$pos_page = $_GET['page'];
	$pos_args = 'customtaxorder';
	$pos = strpos($pos_page,$pos_args);
	if ( $pos === false ) {} else {
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-sortable');
	}
}
add_action('admin_menu', 'customtaxorder_menu');
add_action('admin_print_styles', 'customtaxorder_css');
add_action('admin_print_scripts', 'customtaxorder_js_libs');

function customtaxorder() {
	global $wpdb;
	$tax_label = 'Categories';
	if ( $_GET['page'] == 'customtaxorder' ) { 
		$tax_label = 'Categories';
	} else {
		$args=array( '_builtin' => false ); 
		$output = 'objects';
		$taxonomies=get_taxonomies($args,$output); 
		foreach ($taxonomies as $taxonomy ) {
			$com_page = 'customtaxorder-'.$taxonomy->name;
			if ( $_GET['page'] == $com_page ) { 
				$tax_label = $taxonomy->label; 
			}
		}
	}
	$parentID = 0;
	if (isset($_POST['btnSubCats'])) { 
		$parentID = $_POST['taxes'];
	}
	elseif (isset($_POST['hdnParentID'])) { 
		$parentID = $_POST['hdnParentID'];
	}
	if (isset($_POST['btnReturnParent'])) { 
		$parentsParent = $wpdb->get_row("SELECT parent FROM $wpdb->term_taxonomy WHERE term_id = $parentID ", ARRAY_N);
		$parentID = $parentsParent[0];
	}
	$success = "";
	if (isset($_POST['btnOrderTaxes'])) { 
		$success = customtaxorder_updateOrder();
	}
	$subCatStr = customtaxorder_getSubCats($parentID);
?>
<div class='wrap'>
	<form name="frmCustomTaxOrder" method="post" action="">
		<?php screen_icon('customtaxorder'); ?>
		<h2><?php _e('Order '.$tax_label, 'customtaxorder') ?></h2>
		<?php echo $success; ?>
		<?php if ( $subCatStr ) { ?>
		<h3><?php _e('Order Subcategories', 'customtaxorder') ?></h3>
		<p><?php _e('Choose a category from the drop down to order its subcategories.') ?></p>
		<select id="taxes" name="taxes">
			<?php echo $subCatStr; ?>
		</select>
		<input type="submit" name="btnSubCats" class="button" id="btnSubCats" value="<?php _e('Order Subcategories', 'customtaxorder') ?>" />
		<?php } ?>
		<?php $results = customtaxorder_catQuery($parentID); 
		if ( $results ) { ?>
		<p><?php _e('Order the categories by dragging and dropping them into the desired order.', 'customtaxorder') ?></p>
		<div class="metabox-holder">
			<div class="postbox-container" style="width:80%">
				<div class="stuffbox">
					<h3><?php _e($tax_label, 'customtaxorder') ?></h3>
					<div id="minor-publishing">
						<ul id="customTaxOrderList">
							<?php foreach($results as $row) { echo '<li id="id_'.$row->term_id.'" class="lineitem">'.__($row->name).'</li>'; } ?>
						</ul>
					</div>
					<div id="major-publishing-actions">
						<?php echo customtaxorder_getParentLink($parentID); ?>
						<div id="publishing-action">
							<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" id="custom-loading" style="visibility:hidden;" alt="" />
							<input type="submit" name="btnOrderTaxes" id="btnOrderTaxes" class="button-primary" value="<?php _e('Update Category Order', 'customtaxorder') ?>" onclick="javascript:orderTaxes(); return true;" />
						</div>
						<div class="clear"></div>
					</div>
					<input type="hidden" id="hdnCustomTaxOrder" name="hdnCustomTaxOrder" />
					<input type="hidden" id="hdnParentID" name="hdnParentID" value="<?php echo $parentID; ?>" />
				</div>
			</div>
		</div>
		<?php } else { ?>
		<p><?php _e('No categories found', 'customtaxorder'); ?></p>
		<?php } ?>
	</form>
</div>
<?php if ( $results ) { ?>
<script type="text/javascript">
// <![CDATA[
	function customtaxorderaddloadevent(){
		jQuery("#customTaxOrderList").sortable({ 
			placeholder: "sortable-placeholder", 
			revert: false,
			tolerance: "pointer" 
		});
	};
	addLoadEvent(customtaxorderaddloadevent);
	function orderTaxes() {
		jQuery("#custom-loading").css( "visibility", "visible" );
		jQuery("#hdnCustomTaxOrder").val(jQuery("#customTaxOrderList").sortable("toArray"));
	}
// ]]>
</script>
<?php }
}

function customtaxorder_updateOrder() {
	if (isset($_POST['hdnCustomTaxOrder']) && $_POST['hdnCustomTaxOrder'] != "") { 
		global $wpdb;

		$hdnCustomTaxOrder = $_POST['hdnCustomTaxOrder'];
		$IDs = explode(",", $hdnCustomTaxOrder);
		$result = count($IDs);

		for($i = 0; $i < $result; $i++) {
			$str = str_replace("id_", "", $IDs[$i]);
			$wpdb->query("UPDATE $wpdb->terms SET term_order = '$i' WHERE term_id ='$str'");
		}
		return '<div id="message" class="updated fade"><p>'. __('Category order updated successfully.', 'customtaxorder').'</p></div>';
	} else {
		return '<div id="message" class="error fade"><p>'. __('An error occured, order has not been saved.', 'customtaxorder').'</p></div>';
	}
}

function customtaxorder_getSubCats($parentID) {
	global $wpdb;

	$args=array( '_builtin' => false ); 
	$output = 'objects';
	$taxonomies=get_taxonomies($args,$output); 

	$query_string = "SELECT t.term_id, t.name FROM $wpdb->term_taxonomy tt, $wpdb->terms t, $wpdb->term_taxonomy tt2 WHERE tt.parent = $parentID AND tt.taxonomy = '";
	if ( $_GET['page'] == 'customtaxorder' ) {
		$query_string .= "category";
	} else {
		foreach ($taxonomies as $taxonomy) { 
			$com_page = 'customtaxorder-'.$taxonomy->name;
			if ( $_GET['page'] == $com_page ) { 
				$query_string .= $taxonomy->name; 
			}
		}
	}
	$query_string .= "' AND t.term_id = tt.term_id AND tt2.parent = tt.term_id GROUP BY t.term_id, t.name HAVING COUNT(*) > 0 ORDER BY t.term_order ASC";
	$query_result = $wpdb->get_results($query_string);
	foreach($query_result as $row) {
    	$subCatStr .= '<option value="'.$row->term_id.'">'.__($row->name).'</option>';
	}

	if ( $subCatStr ) {
		return $subCatStr;
	} else {
		return false;
	}
}

function customtaxorder_catQuery($parentID) {
	global $wpdb;
	$args=array( '_builtin' => false ); 
	$output = 'objects';
	$taxonomies=get_taxonomies($args,$output); 
	$query_string = "SELECT * FROM $wpdb->terms t inner join $wpdb->term_taxonomy tt on t.term_id = tt.term_id WHERE taxonomy = '";
	if ( $_GET['page'] == 'customtaxorder' ) {
		$query_string .= "category";
	} else {
		foreach ($taxonomies as $taxonomy) { 
			$com_page = 'customtaxorder-'.$taxonomy->name;
			if ( $_GET['page'] == $com_page ) { 
				$query_string .= $taxonomy->name; 
			}
		}
	}
	$query_string .= "' AND parent = $parentID ORDER BY term_order ASC";
	$query_result = $wpdb->get_results($query_string);
	if ( empty($query_result) ) {
		return false;
	} else {
		return $query_result;
	}
}

function customtaxorder_getParentLink($parentID) {
	if($parentID != 0) {
		return '<input type="submit" class="button" style="float:left" id="btnReturnParent" name="btnReturnParent" value="'. __('Return to Parent Category', 'customtaxorder') .'" />';
	} else {
		return "";
	}
}

function customtaxorder_applyorderfilter($orderby, $args) {
	if($args['orderby'] == 'term_order')
		return 't.term_order';
	else
		return $orderby;
}
add_filter('get_terms_orderby', 'customtaxorder_applyorderfilter', 10, 2);

function customtaxorder_init() {
	global $wpdb;
	
	$init_query = $wpdb->query("SHOW COLUMNS FROM $wpdb->terms LIKE 'term_order'");
	if ($init_query == 0) {	$wpdb->query("ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'"); }
}
register_activation_hook(__FILE__, 'customtaxorder_init');
?>