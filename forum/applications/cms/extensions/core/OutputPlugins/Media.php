<?php
/**
 * @brief		Template Plugin - Pages: Media
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		07 July 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\extensions\core\OutputPlugins;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Content: Media
 */
class _Media
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = TRUE;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options )
	{
		if ( is_numeric( $data ) )
		{
			try
			{
				$url = \IPS\cms\Media::load( $data )->url();
			}
			catch( \OutOfRangeException $ex )
			{
				$url = NULL;
			}
		}
		else
		{
			try
			{
				$url = \IPS\cms\Media::load( $data, 'media_full_path' )->url();
			}
			catch( \OutOfRangeException $ex )
			{
				$url = NULL;
			}
		}
		
		return "'" . (string) $url . "'";
	}
}