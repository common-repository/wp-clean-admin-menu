<?php

/**
 * Plugin Name: WP Clean Admin Menu
 * Plugin URI:  http://wordpress.org/plugins/wp-clean-admin-menu
 * Description: Simplify WordPress admin-menu by hiding the rarely used admin-menu items/links.
 * Tags: wp clean admin menu, wordpress clean admin menu, wp hide admin menu, clean admin menu
 * Author: P. Roy
 * Author URI: https://www.proy.info
 * Version: 3.2.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-clean-admin-menu
 **/

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}


class WP_Clean_Admin_Menu
{

	//protected $loader;
	protected $plugin_name;
	protected $version;

	public $toggleItemSlug = 'toggle_wpcleanadminmenu';
	public $toggleItemOrder = '300.1';
	public $hiddenItemsOptionName = 'toggle_wpcleanadminmenu_items';
	public $hiddenSubItemsOptionName = 'toggle_wpcleanadminmenu_subitems';
	public $nonceName = 'toggle_wpcleanadminmenu_options';

	public function __construct()
	{

		$this->plugin_name = 'wp-clean-admin-menu';
		$this->version = '1.0.0';

		add_action('admin_init', array($this, 'admin_init'));

		//add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		//add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );


		//action to add menu pages on admin
		add_action('admin_menu', array($this, 'addMenuPages'));

		//action for adding classes to admin menu items
		add_action('admin_menu', array($this, 'adminMenuAction'), 9999);
	}

	public function admin_init()
	{
		if (is_admin() && current_user_can('manage_options')) {
			ob_start(); // this is require to resolve redirect issue
			add_action('admin_head', array($this, 'toggle_menu_items'));
		}
	}

	public function toggle_menu_items()
	{
?>
		<script>
			(function($) {
				var menusAreHidden = true;
				$(function() {
					/**
					 * When the toggle extra item clicked show/hide menu items
					 * Also trigger the wp-window-resized event for left menu
					 */
					$('#toplevel_page_toggle_wpcleanadminmenu a').click(function(e) {
						e.preventDefault();
						$('.menu-top.clean-wp-admin-menu__valid-item').toggleClass('hidden');
						$(document).trigger('wp-window-resized');
					});

					/**
					 * Little hack for some of the submenus declared after the admin_menu hook
					 * If it should be open but hidden, remove the hidden class
					 */
					$('#adminmenu .wp-menu-open.hidden').removeClass('hidden');

					$("ul[class^='wp-submenu wp-submenu-wrap']").each(function() {
						var wrap = $(this);
						var countTotalchild = wrap.children("li").length;
						var countTotalHiddenchild = wrap.children("li.hidden").length;
						if (countTotalchild - countTotalHiddenchild == 1) {
							wrap.addClass('hidden clean-wp-admin-menu__allhidden');
						}
					});

				});
			})(jQuery);
		</script>
	<?php
	}




	/**
	 * Add menu pages in admin
	 */
	public function addMenuPages()
	{

		if (!is_admin() && !current_user_can('manage_options')) return;

		add_menu_page(
			__('Toggle Menu', $this->plugin_name),
			__('Toggle Menu', $this->plugin_name),
			'manage_options',
			$this->toggleItemSlug,
			function () {
				return false;
			},
			"dashicons-hidden",
			$this->toggleItemOrder
		);

		add_options_page(
			__('WP Clean Admin Menu', $this->plugin_name),
			__('WP Clean Admin Menu', $this->plugin_name),
			'manage_options',
			$this->plugin_name . '_options',
			array(
				$this,
				'settingsPage'
			)
		);



		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'), 10, 2);
	}


	public function plugin_settings_link($links, $file)
	{
		$settings_link = '<a href="options-general.php?page=wp-clean-admin-menu_options">' . __('Settings', $this->plugin_name) . '</a>';
		array_unshift($links, $settings_link); // before other links
		return $links;
	}

	/**
	 * Add necessary items
	 */
	public function adminMenuAction()
	{

		global $_parent_pages, $_sub_pages, $menu, $submenu, $parent_file, $submenu_file;

		if (!is_admin() && !current_user_can('manage_options')) return;

		//list of items selected from settings page
		$selectedItems = $this->selectedItems();
		$selectedSubItems = $this->selectedSubItems();

		//print_r($selectedSubItems);

		//$menuItems     = wp_list_pluck($menu, 2);
		//$submenuItems     = wp_list_pluck($submenu, 2);

		$hidden_submenuitems = array();



		foreach ($menu as $k => $item) {
			// Reminder for parent menu items array
			// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url

			$sub_menu_array = isset($submenu[$item['2']]) ? $submenu[$item['2']] : array();
			//check it is array or not
			$sub_menu_array = isset($sub_menu_array) ? (array) $sub_menu_array : array();

			$isSelected      = in_array($item[2], $selectedItems);
			$isCurrentItem   = false;
			$isCurrentParent = false;

			//check if item is parent of current item
			//if not both of them, it deserves to be hidden if it is selected
			if ($parent_file) {
				$isCurrentItem = ($item[2] == $parent_file);

				if (isset($_parent_pages[$parent_file])) {
					$isCurrentParent = ($_parent_pages[$parent_file] === $item[2]);
				}
			}

			$isHidden = ($isSelected && false === ($isCurrentParent or $isCurrentItem));

			if ($isHidden) {
				$menu[$k][4] = $item[4] . ' hidden clean-wp-admin-menu__valid-item';
			}


			foreach ($submenu as $m => $subitems) {
				foreach ($subitems as $j => $subitem) {
					// Reminder for parent menu items array
					// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url

					if (!isset($subitem[4])) $subitem[4] = '';

					$parent_menu = $item[2];
					$checkSubItems = array();
					if (isset($selectedSubItems[$parent_menu])) {
						$checkSubItems = (array)$selectedSubItems[$parent_menu];
					}
					//echo "<pre>";
					//print_r($checkSubItems);
					//echo "--------------------<br>";

					//print_r($subitem);
					//echo "--------------------<br>";
					$isSubSelected  = in_array($subitem[2], $checkSubItems);
					//$isSubSelected      = in_array($subitem[2], $selectedSubItems);
					$isCurrentSubItem   = false;
					$isCurrentSub = false;


					//check if item is parent of current item
					//if not both of them, it deserves to be hidden if it is selected
					if ($submenu_file) {
						$isCurrentSubItem = ($subitem[2] == $submenu_file);

						if (isset($_sub_pages[$submenu_file])) {
							$isCurrentSub = ($_sub_pages[$submenu_file] === $subitem[2]);
						}
					}

					$isHidden = ($isSubSelected && false === ($isCurrentSub or $isCurrentSubItem));

					if ($isHidden) {
						//print_r($subitems[$j]);
						//echo "--------------------<br>";
						//unset($submenu[$m]);

						$hidden_submenuitems[$m][$j] = $subitem[2];
						$submenu[$m][$j][4] = $subitem[4] . ' hidden clean-wp-admin-menu__valid-item';
					}
				}
			}


			//$parent_menu = $item[2];
			//echo is_array($submenu[$parent_menu])?count($submenu[$parent_menu]).'<br>':'';
			//print_r($hidden_submenuitems[$parent_menu]);

		}
	}

	public function settingsPage()
	{
		global $_registered_pages, $_parent_pages, $_sub_pages, $menu, $admin_page_hooks, $submenu;

		$this->saveSettings();
		$pluginName = $this->plugin_name;
		$selectedItems = $this->selectedItems();
		$selectedSubItems = $this->selectedSubItems();
		//print_r($selectedSubItems);

	?>
		<style>
			.wrap td,
			.wrap th {
				text-align: left;
			}

			.txt-white{ color: #fff; }

			.table-menulist {
				background-color: #fff;
				padding: 10px;
				margin-bottom: 20px;
			}

			.table-menulist tr:hover td {
				background-color: #f0f0f1;
			}

			.table-menulist th {
				padding: 5px;
				border-bottom: 1px solid #DFDFDF;
			}

			.table-menulist td {
				padding: 5px;
				border-bottom: 1px solid #DFDFDF;
			}

			.table-menulist tr:last-child td {
				border-bottom: 0;
			}

			.table-menulist .awaiting-mod,
			.table-menulist .update-plugins {
				display: inline-block;
				vertical-align: top;
				box-sizing: border-box;
				margin: 1px 0 -1px 2px;
				padding: 0 5px;
				min-width: 18px;
				height: 18px;
				border-radius: 9px;
				background-color: #ca4a1f;
				color: #fff;
				font-size: 11px;
				line-height: 1.6;
				text-align: center;
				z-index: 26;
			}

			.wpcam_content_wrapper {
				display: table;
				table-layout: fixed;
				width: 100%;
			}

			#wpcam_content {
				width: 100%;
			}

			#wpcam_sidebar {
				padding-left: 20px;
				padding-right: 20px;
				width: 380px;
			}

			.wpcam_content_cell {
				display: table-cell;
				height: 500px;
				margin: 0;
				padding: 0;
				vertical-align: top;
			}

			.wpcam-sidebar__product {
				background: linear-gradient(to right top, #051937, #003f64, #006770, #008c52, #79a810);
				margin-top: 34px;
				height: 380px;
				padding-bottom: 40px;
				-webkit-box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.75);
				-moz-box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.75);
				box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.75);
			}

			.wpcam-sidebar__product_img {
				color: #fff;
				background: url(<?php echo plugin_dir_url( __FILE__ ).'wp-clean-admin-menu-pro.png';?>) no-repeat;
				background-size: 104%;
				background-position: 0px 160px;
				width: auto;
				height: 100%;
				position: relative;
				overflow: hidden;
				padding: 20px;
			}

			.plugin-buy-button {
				position: absolute;
    			bottom: 0;
			}

			.wpcam-button-upsell {
				align-items: center;
				background-color: #fec228;
				border-radius: 4px;
				box-shadow: inset 0 -4px 0 #0003;
				box-sizing: border-box;
				color: #000;
				display: inline-flex;
				filter: drop-shadow(0 2px 4px rgba(0,0,0,.2));
				font-family: Arial,sans-serif;
				font-size: 16px;
				justify-content: center;
				line-height: 1.5;
				min-height: 48px;
				padding: 8px 1em;
				text-decoration: none;
			}

			@media screen and (max-width: 1024px){

				table.table-menulist tr:first-child th:last-child { width: 100% !important; }
				.wpcam_content_cell, .wpcam_content_wrapper {
					display: block;
					height: auto;
				}
				#wpcam_sidebar {
					padding-left: 0;
					width: auto;
				}
			}
		</style>

		<div class="wpcam_content_wrapper">
			<div class="wpcam_content_cell" id="wpcam_content">
				<div class="wrap">
					<h1><?php esc_html_e('WP Clean Admin Menu', $pluginName); ?></h1>
					<?php
					$saved = (isset($_GET['saved'])) ? sanitize_text_field($_GET['saved']) : '';
					echo (($saved == 1) ? '<div class="updated"><p>' . __('Success! Admin menu cleaned successfully', $this->plugin_name) . '</p></div>' : ''); ?>
					<p>
						This plugin helps to simplify WordPress admin-menu by hiding the rarely used admin-menu items/links.<br />
						<strong>Selected menu items will be HIDDEN by default. To show/hide items, use the 'Toggle Menu' item at the bottom of the left menu.</strong>
					</p>
					<form action="<?php echo esc_attr(admin_url('options-general.php?page=wp-clean-admin-menu_options')); ?>" method="post">
						<?php wp_nonce_field($this->nonceName, $this->nonceName, true, true); ?>
						<!-- <table class="table-menulist">
					<tr>
						<th></th>
						<th>Select User role</th>
						<th>
							<div>
								<select id="roles" name="roles" class="fre-chosen-single">

									<?php
									foreach (get_editable_roles() as  $role_name => $role_info) {
										echo '<option value="' . $role_name . '">' . $role_info['name'] . '</option>';
									} ?>
								</select>
							</div>
						</th>
					</tr>
				</table> -->

						<table class="table-menulist">

							<tr>
								<th></th>
								<th></th>
								<th style="width:300px;">Menu Items</th>
							</tr>
							<?php

							$separator = 0;
							//print_r($submenu);
							foreach ($menu as $key => $menuItem) {

								$sub_menu_array = isset($submenu[$menuItem['2']]) ? $submenu[$menuItem['2']] : array();
								//check it is array or not
								$sub_menu_array = isset($sub_menu_array) ? (array) $sub_menu_array : array();

								//print_r($menuItem);
								$isSeparator = strpos($menuItem[4], 'wp-menu-separator');
								$isSelected  = in_array($menuItem[2], $selectedItems);

								//if ($isSeparator !== false OR $menuItem[2] === 'toggle_wpcleanadminmenu') {
								if ($isSeparator !== false) {
									$menuItem[0] = '――――――separator――――――';
									$separator++;
								}

								// Hiding the Separator before the "toggle menu" link
								if ($separator > 1) {
									$separator = 0;
									continue;
								}

							?>
								<tr>
									<td>
										<input type="checkbox" name="toggle_wpcleanadminmenu_items[]" value="<?php echo $menuItem[2]; ?>" id="toggle_wpcleanadminmenu_item_<?php echo $key; ?>" <?php echo ($isSelected) ? 'checked' : ''; ?> <?php //echo ($menuItem[2] === 'index.php') ? 'disabled' : '';
																																																												?> />
									</td>
									<td>
										<?php if ($isSelected) { ?>
											<span style="color:#CA4A1F;" class="dashicons-before dashicons-hidden"></span>
										<?php } else { ?>
											<span style="color:#10af05;" class="dashicons-before dashicons-visibility"></span>
										<?php } ?>
									</td>
									<td>
										<label style="width:100%;display:block;" for="toggle_wpcleanadminmenu_item_<?php echo $key; ?>">
											<strong <?php echo ($isSeparator !== false ? 'style="color:#B7B7B7;"' : '') ?>>
												<?php
												if ($menuItem[2] === 'toggle_wpcleanadminmenu')
													echo '―― ' . strtoupper($menuItem[0]) . ' ――<br><sub style="color:#616A74;">Used to toggle menu items.<br>Check this item to hide from menu list.</sub>';
												else
													echo $menuItem[0];
												?>
											</strong>
										</label>
									</td>
								</tr>


								<?php
								//now we add the sub menu to parent menu

								foreach ($sub_menu_array as $subkey => $submenuItem) {
									//print_r($submenuItem);

									$parent_menu = $menuItem[2];

									$checkSubItems = $isSubSelected  = '';

									if (isset($selectedSubItems[$parent_menu])) {
										$checkSubItems = (array)$selectedSubItems[$parent_menu];
										$isSubSelected  = in_array($submenuItem[2], $checkSubItems);
									}

									$isSeparator = false;
									if (isset($submenuItem[4])) {
										$isSeparator = strpos($submenuItem[4], 'wp-menu-separator');
									}
									//if ($isSeparator !== false OR $submenuItem[2] === 'toggle_wpcleanadminmenu') {
									if (empty(trim($submenuItem[0]))) $isSeparator = true;
									if ($isSeparator !== false) {
										$submenuItem[0] = '――――――separator――――――';
										$separator++;
									}

									// Hiding the Separator before the "toggle menu" link
									if ($separator > 1) {
										$separator = 0;
										continue;
									}
								?>
									<tr>
										<td>
											<input type="checkbox" name="toggle_wpcleanadminmenu_subitems[<?php echo $menuItem[2]; ?>][<?php echo $subkey; ?>]" value="<?php echo $submenuItem[2]; ?>" id="toggle_wpcleanadminmenu_subitem_<?php echo $key . '_' . $subkey; ?>" <?php echo ($isSubSelected) ? 'checked' : ''; ?> <?php //echo ($submenuItem[2] === 'index.php') ? 'disabled' : '';
																																																																																	?> />
										</td>
										<td>
											<?php if ($isSubSelected) { ?>
												<span style="color:#CA4A1F;" class="dashicons-before dashicons-hidden"></span>
											<?php } else { ?>
												<span style="color:#10af05;" class="dashicons-before dashicons-visibility"></span>
											<?php } ?>
										</td>
										<td><label style="width:100%;display:block;" for="toggle_wpcleanadminmenu_subitem_<?php echo $key . '_' . $subkey; ?>">
												<span <?php echo ($isSeparator !== false ? 'style="color:#B7B7B7;"' : '') ?>>
													<?php
													if ($submenuItem[2] === 'toggle_wpcleanadminmenu')
														echo '―― ' . strtoupper($submenuItem[0]) . ' ――<br><sub style="color:#616A74;">Used to toggle menu items.<br>Check this item to hide from menu list.</sub>';
													else
														echo '― ' . $submenuItem[0];
													?>
												</span>
											</label>
										</td>
									</tr>
								<?php

								}
								?>




							<?php } ?>
						</table>
						<input type="submit" class="button-primary" value="<?php esc_html_e('SAVE CHANGES', $pluginName); ?>" />
					</form>
					<hr>
					<?php echo esc_html_e('This Plugin Developed by ', $pluginName); ?><a href="https://www.proy.info" target="_blank">P. Roy</a>
				</div>
			</div>

			<div class="wpcam_content_cell" id="wpcam_sidebar">
				<div class="wpcam-sidebar__product">
					<div class="wpcam-sidebar__product_img">
						<h1 class="txt-white wpcam-premium-title">WP Clean Admin Menu Pro</h1>
						<p>Want to control more of your admin menu.</p>
						<p>With the new WP Clean Admin Menu Pro, you can control menus based on user role.</strong></p>
						<ul style="padding-left: 10px; list-style-type: disc; font-weight: bold;">
							<li></li>
							<li>Restricted settings to authorised user only</li>
						</ul>
						 <p class="plugin-buy-button">
							<a class="wpcam-button-upsell" target="_blank" href="https://demotolive.com/wordpress-plugins/wp-clean-admin-menu-pro">
								Get WP Clean Admin Menu Pro
							</a>
						</p>
					</div>
				</div>
			</div>

		</div>

<?php
	}

	public function selectedItems()
	{
		$items = get_option($this->hiddenItemsOptionName);
		if (!$items) {
			$items = array();
			return $items;
		}
		return $items;
	}

	public function selectedSubItems()
	{
		$items = get_option($this->hiddenSubItemsOptionName);
		if (!$items) {
			$items = array();
			return $items;
		}
		return $items;
	}

	private function saveSettings()
	{
		global $menu, $submenu;

		if (!isset($_POST[$this->nonceName])) {
			return false;
		}

		$verify = check_admin_referer($this->nonceName, $this->nonceName);

		//TODO if empty but has post delete items

		if (!isset($_POST['toggle_wpcleanadminmenu_items'])) {
			$itemsToSave = array();
			$savedSuccess = 0;
		} else {

			$menuItems = wp_list_pluck($menu, 2);

			$items = $_POST['toggle_wpcleanadminmenu_items'];

			//print_r($items); exit;

			//save them after a check if they really exists on menu
			$itemsToSave = array();

			if ($items) {
				foreach ($items as $item) {
					if (in_array($item, $menuItems)) {
						$itemsToSave[] = $item;
					}
				}
			}
			$savedSuccess = 1;
		}

		//update the option and set as autoloading option
		update_option($this->hiddenItemsOptionName, $itemsToSave, true);


		if (!isset($_POST['toggle_wpcleanadminmenu_subitems'])) {
			$subitemsToSave = array();
			$savedSuccess = 0;
		} else {

			//echo "<pre>";
			//print_r($submenu);

			//echo "oooooooooooooooooooo"; exit;
			$submenuItems = $submenu;
			//print_r($submenuItems);
			//echo "oooooooooo==============================oooooooooo<br>";

			$items = $_POST['toggle_wpcleanadminmenu_subitems'];

			//print_r($items);
			//echo "oooooooooo==============================oooooooooo<br>";

			//save them after a check if they really exists on menu
			$subitemsToSave = array();

			if ($items) {
				foreach ($items as $key => $item) {
					if (array_key_exists($key, $submenuItems)) {
						//if (in_array($item, $submenuItems)) {
						$subitemsToSave[$key] = $item;
					}
				}
			}

			//print_r($subitemsToSave); exit;
			$savedSuccess = 1;
		}

		//update the option and set as autoloading option
		update_option($this->hiddenSubItemsOptionName, $subitemsToSave, true);

		// we'll redirect to same page when saved to see results.
		// redirection will be done with js, due to headers error when done with wp_redirect
		$adminPageUrl = admin_url('options-general.php?page=wp-clean-admin-menu_options&saved=' . $savedSuccess);
		wp_safe_redirect($adminPageUrl);
		exit;
	}

	function wp_roles_array()
	{
		$editable_roles = get_editable_roles();
		foreach ($editable_roles as $role => $details) {
			$sub['role'] = esc_attr($role);
			$sub['name'] = translate_user_role($details['name']);
			$roles[] = $sub;
		}
		return $roles;
	}
}

new WP_Clean_Admin_Menu();
