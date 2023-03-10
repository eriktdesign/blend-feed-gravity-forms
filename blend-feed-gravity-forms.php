<?php
/**
 * Plugin Name: Blend Feed for Gravity Forms
 * Plugin URI: http://www.inverseparadox.com
 * Description: Adds a feed to connect your Gravity Forms data to your Blend instance.
 * Version: 1.0.0
 * Author: Inverse Paradox
 * Author URI: http://www.inverseparadox.com
 * 
 * ------------------------------------------------------------------------
 * Copyright 2023 Inverse Paradox
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */
define( 'BLEND_GFEED_VERSION', '2.0' );

add_action( 'gform_loaded', array( 'Blend_GFeed_Bootstrap', 'load' ), 5 );

class Blend_GFeed_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'blend-feed-gravity-forms/inc/class-blend-api.php';
		require_once 'inc/class-blend-gfeed.php';
		require_once 'vendor/autoload.php';
		require_once 'inc/class-blend-api.php';

		GFAddOn::register( 'BlendGFeed' );
	}

}

function blend_gfeed() {
	return BlendGFeed::get_instance();
}