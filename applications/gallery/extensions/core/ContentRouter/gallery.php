<?php
/**
 * @brief		Content Router extension: Gallery
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Gallery
 */
class _Gallery
{	
	/**
	 * @brief	Content Item Classes
	 */
	public $classes = array();
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member|NULL	$member		If checking access, the member to check for, or NULL to not check access
	 * @return	void
	 */
	public function __construct( \IPS\Member $member = NULL )
	{
		if ( $member === NULL or $member->canAccessModule( \IPS\Application\Module::get( 'gallery', 'gallery', 'front' ) ) )
		{
			$this->classes[] = 'IPS\gallery\Image';
		}
	}
	
	/**
	 * @brief	Owned Node Classes
	 */
	public $ownedNodes = array( 'IPS\gallery\Album' );

	/**
	 * Use a custom table helper when building content item tables
	 *
	 * @param	string			$className	The content item class
	 * @param	\IPS\Http\Url	$url		The URL to use for the table
	 * @param	array			$where		Custom where clause to pass to the table helper
	 * @return	\IPS\Helpers\Table|void		Custom table helper class to use
	 */
	public function customTableHelper( $className, $url, $where=array() )
	{
		if( !in_array( $className, $this->classes ) )
		{
			return new \IPS\Helpers\Table\Content( $className, $url, $where );
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery.css', 'gallery', 'front' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery_responsive.css', 'gallery', 'front' ) );
		}

		$table = new \IPS\gallery\Image\Table( $className, $url, $where );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'imageTable' );

		/* Get rows template */
		if( isset( \IPS\Request::i()->cookie['thumbnailSize'] ) AND \IPS\Request::i()->cookie['thumbnailSize'] == 'large' AND \IPS\Request::i()->controller != 'search' )
		{
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'tableRowsLarge' );
		}
		else if( isset( \IPS\Request::i()->cookie['thumbnailSize'] ) AND \IPS\Request::i()->cookie['thumbnailSize'] == 'rows' AND \IPS\Request::i()->controller != 'search' )
		{
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'tableRowsRows' );
		}
		else
		{
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'tableRowsThumbs' );
		}	

		return $table;
	}
}