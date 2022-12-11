<?php
/**
 * @brief		Dashboard extension: Overview
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Overview
 */
class _Overview
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
	 * Return the block HTML show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		/* Basic stats */
		$data = array(
			'total_disk_spaceg'	=> (int) \IPS\Db::i()->select( 'SUM(image_file_size)', 'gallery_images' )->first(),
			'total_images'		=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first(),
			'total_views'		=> (int) \IPS\Db::i()->select( 'SUM(image_views)', 'gallery_images' )->first(),
			'total_comments'	=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'gallery_comments' )->first(),
			'total_albums'		=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums' )->first(),
		);
		
		/* Specific files (will fail if no files yet) */
		try
		{
			$data['largest_image']		= \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_file_size DESC', 1 )->first() );
			$data['most_viewed_image']	= \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_views DESC', 1 )->first() );
		}
		catch ( \Exception $e ) { }
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'dashboard', 'gallery' )->overview( $data );
	}
}