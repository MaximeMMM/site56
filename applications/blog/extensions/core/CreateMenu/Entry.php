<?php
/**
 * @brief		Create Menu Extension : Entry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		10 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: Entry
 */
class _Entry
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{
		if ( \IPS\blog\Entry::canCreate( \IPS\Member::loggedIn() ) )
		{
			return array(
				'blog_entry' => array(
					'link' 		=> \IPS\Http\Url::internal( "app=blog&module=blogs&controller=submit", 'front', 'blog_submit' ),
					'extraData'	=> array( 'data-ipsDialog' => true, 'data-ipsDialog-size' => "narrow", ),
					'title' 	=> 'select_blog'
				)
			);
		}
		else
		{
			return array();
		}
	}
}