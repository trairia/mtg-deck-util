<?php
/*
Plugin Name: MtgDeckUtil
Version: 0.1-alpha
Description: Magic the Gathering Deck Utilities for Wordpress
Author: Kazunori Kimura
Author URI: http://twitter.com/trairia
Plugin URI: PLUGIN SITE HERE
Text Domain: mtg-deck-util
Domain Path: /languages
*/

/// register hooks
register_activation_hook(   __FILE__, array( 'MtGDeckUtil', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'MtGDeckUtil', 'on_deactive' ) );
register_uninstall_hook(    __FILE__, array( 'MtGDeckUtil', 'on_uninstall' ) );
/// Plugin Implement
class MtGDeckUtil{

	/// Initialize the plugin
	function __construct(){
		/// plugin menu page
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
	}

	function plugin_menu(){
		add_menu_page(
			__('Magic the Gaghering Deck Utility', 'mtg-deck-util'),
			__('MtG Deck Utility', 'mtg-deck-util'),
			'manage_options',
			'mtgdeckutil',
			array( $this, 'menu_page' )
		);
		add_submenu_page(
			'mtgdeckutil',
			__('Add New Deck', 'mtg-deck-util'),
			__('Add New Deck', 'mtg-deck-util'),
			'manage_options',
			'mtgdeckutil-add-deck',
			array( $this, 'register_new_deck' )
		);
	}

	function menu_page(){
	}

	function register_new_deck(){
		$formats = array(
			'Standard' => __('Standard', 'mtg-deck-util'),
			'Modern' => __('Modern', 'mtg-deck-util'),
			'Legacy' => __('Legacy', 'mtg-deck-util')
		);

?>
	<div id="register_deck_div">
		<h2 class="menu_title"><?php echo __("Register New Deck", 'mtg-deck-util') . "<br/>" ?></h2>
		<form action="<?php echo esc_url( $_SERVER['REQUEST_URI']) ?>" method="post">
			<table border="0">
				<tr>
					<td align="right"><?php echo __( 'Player', 'mtg-deck-util' ) ?>:</td>
					<td><input type="text" name="player_name" size="40">
					</td>
				</tr>
				<tr>
					<td align="right"><?php echo __( 'Deckname', 'mtg-deck-util' ) ?>:</td>
					<td><input type="text" name="deck_name" size="40">
					</td>
				</tr>
				<tr>
					<td align="right"><?php echo __( 'Format', 'mtg-deck-util' ) ?>:</td>
					<td>
						<select name="format">
							<?php
							foreach ( $formats as $t => $name ) {
								echo "<option value=\"$t\">$name</option>";
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td align="right" valign="top"><?php echo __( 'Decklist', 'mtg-deck-util' ) ?>:</td>
					<td><textarea name="decklist" align="left" rows="20" cols="40"></textarea></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" align="right"" value=<?php echo __('Register Deck', 'mtg-deck-util') ?>>
					</td>
				</tr>
			</table>
		</form>
	</div>
<?php
	}
	
	/// callback to register_activation_hook
	public static function on_activate(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_${plugin}" );
	}

	/// callback to register_deactivation_hook
	public static function on_deactivate(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_${plugin}" );
	}

	/// callback to register_uninstall_hook
	public static function on_uninstall(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		check_admin_referer( 'bulk-plugins' );
		if ( __FILE__ != WP_UNINSTALL_PLUGIN )
			return;
	}
}

function MtGDeckUtil(){
	global $mtg_deck_util;
	$mtg_deck_util = new MtGDeckUtil();
}
add_action( 'init', 'MtGDeckUtil' );
