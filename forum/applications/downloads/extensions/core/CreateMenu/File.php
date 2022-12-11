<?php
/**
 * @brief		Create Menu Extension
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		8 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension
 */
class _File
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{		
		if ( \IPS\downloads\Category::canOnAny( 'add' ) )
		{
			return array(
				'file_download' => array(
					'link' 		=> \IPS\Http\Url::internal( "app=downloads&module=downloads&controller=submit&_new=1", 'front', 'downloads_submit' ),
					'title' 	=> 'select_category',
					'extraData'	=> array( 'data-ipsDialog' => true, 'data-ipsDialog-size' => "narrow" )
				)
			);
		}
		
		
		return array();
	}
}