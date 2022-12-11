<?php
/**
 * @brief		Create Menu Extension : gallery
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: gallery
 */
class _gallery
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{		
		if ( \IPS\gallery\Category::canOnAny( 'add' ) )
		{
			return array(
				'gallery_image' => array(
					'link' 		=> \IPS\Http\Url::internal( "app=gallery&module=gallery&controller=submit&_new=1", 'front', 'gallery_submit' ),
					'extraData'	=> array( 'data-ipsDialog-size' => "fullscreen", 'data-ipsDialog' => true )
				)
			);
		}

		return array();
	}
}