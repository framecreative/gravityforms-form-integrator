<?php
/*
 * Plugin Name: Gravity Forms - Form Integrator
 * Plugin URI: https://framecreative.com.au
 * Description: A plugin to send gravity forms submission data to 3rd parties such as salesforce or marketo
 * Version: 2.3.4
 * Author: F/R/A/M/E/ Creative
 * Author URI: https://framecreative.com.au
 * Github Plugin URI: https://bitbucket.org/framecreative/gravityforms-form-integrator
 * Github Branch:     master

------------------------------------------------------------------------

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_FORM_INTEGRATOR_VERSION', '2.3.4' );

add_action( 'gform_loaded', array( 'Gf_form_integrator_Bootstrap', 'load' ), 5 );

class Gf_form_integrator_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once('class-gfformintegrator.php');

		GFAddOn::register('GFFormIntegrator');
	}

}

function gf_form_integrator() {
	return GFFormIntegrator::get_instance();
}