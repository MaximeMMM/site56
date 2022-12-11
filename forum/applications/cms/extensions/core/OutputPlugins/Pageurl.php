<?php
/**
 * @brief		Template Plugin - Pages: Page Url
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		27 May 2015
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
 * Template Plugin - Content: Block
 */
class _Pageurl
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = FALSE;
	
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
				$url = \IPS\cms\Pages\Page::load( $data )->url();
			}
			catch( \OutOfRageException $ex )
			{
				$url = NULL;
			}
		}
		else
		{
			$data = ltrim( $data );
			$url = \IPS\Http\Url::internal( 'app=cms&module=pages&controller=page&path=' . $data, 'front', 'content_page_path', array( $data ) );
		}
		
		return "'" . (string) $url . "'";
	}
}