<?php
/**
 * @brief		Uninstall callback
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		10 Feb 2016
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\extensions\core\Uninstall;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Uninstall callback
 */
class _Widgets
{
	/**
	 * Code to execute before the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function preUninstall( $application )
	{
	}

	/**
	 * Code to execute after the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function postUninstall( $application )
	{
	}

	/**
	 * Remove all applications widgets from the pages cms_page_widget_areas database table
	 *
	 * @param	string	$application	Application directory
	 * @return	void
	 */
	public function onOtherAppUninstall( $application )
	{
		/* clean up widget areas table */
		foreach ( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $row )
		{
			$data = json_decode( $row['area_widgets'], true );

			foreach ( $data as $key => $widget )
			{
				if ( isset( $widget['app'] ) and $widget['app'] == $application )
				{
					unset( $data[$key] );
				}
			}

			\IPS\Db::i()->update( 'cms_page_widget_areas', array( 'area_widgets' => json_encode( $data ) ), array( 'area_page_id=? AND area_area=?', $row['area_page_id'], $row['area_area'] ) );
		}
	}
}