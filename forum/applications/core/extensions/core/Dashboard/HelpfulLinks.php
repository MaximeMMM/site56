<?php
/**
 * @brief		Dashboard extension: Helpful Links
 *
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 *
 * @package		IPS Social Suite
 * @since		03.10.2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Helpful Links
 */
class _HelpfulLinks
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		return '
		<div class="ipsGrid ipsGrid_collapseTablet">
			<div class="ipsGrid_span6 ipsType_center">
				<a class="ipsButton ipsButton_medium ipsButton_alternate ipsButton_fullWidth" href="https://webflake.sx/forum/69-ipboard-support/" target="_blank">IPB Support</a>
			</div>

			<div class="ipsGrid_span6 ipsType_center">
				<a class="ipsButton ipsButton_medium ipsButton_alternate ipsButton_fullWidth" href="https://webflake.sx/files/category/69-applications/" target="_blank">Applications</a>
			</div>
		</div>
		</br>
		<div class="ipsGrid ipsGrid_collapseTablet">
			<div class="ipsGrid_span6 ipsType_center">
				<a class="ipsButton ipsButton_medium ipsButton_alternate ipsButton_fullWidth" href="https://webflake.sx/files/category/71-plugins/" target="_blank">Plugins</a>
			</div>
			
			<div class="ipsGrid_span6 ipsType_center">
				<a class="ipsButton ipsButton_medium ipsButton_alternate ipsButton_fullWidth" href="https://webflake.sx/files/category/72-themes/" target="_blank">Themes</a>
			</div>
		</div>
		';
	}
}