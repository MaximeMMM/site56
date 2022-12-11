<?php
/**
 * @brief		Announcements Extension : Download Categories
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		28 Apr 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\extensions\core\Announcements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcements Extension: Download Categories
 */
class _Categories
{
	/**
	 * @brief	ID Field
	 */
	public static $idField = "id";
	
	/**
	 * @brief	Controller classes
	 */
	public static $controllers = array( "browse" );
	
	/**
	 * Get Setting Field
	 *
	 * @param	\IPS\core\Announcements\Announcement	$announcement
	 * @return	Form element
	 */
	public function getSettingField( $announcement )
	{
		return new \IPS\Helpers\Form\Node( 'announce_download_categories', ( $announcement AND $announcement->ids ) ? explode( ",", $announcement->ids ) : 0, FALSE, array( 'class' => 'IPS\downloads\Category', 'zeroVal' => 'any', 'multiple' => TRUE ), NULL, NULL, NULL, 'announce_download_categories' );
	}
}