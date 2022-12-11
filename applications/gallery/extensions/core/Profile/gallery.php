<?php
/**
 * @brief		Profile extension: Gallery
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		02 Apr 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\Profile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Profile extension: Gallery
 */
class _gallery
{
	/**
	 * Member
	 */
	protected $member;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member whose profile we are viewing
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
	}
	
	/**
	 * Is there content to display?
	 *
	 * @return	bool
	 */
	public function showTab()
	{
		$where = array( array( 'album_owner_id=?', $this->member->member_id ) );
		
		if( count( \IPS\Member::loggedIn()->socialGroups() ) )
		{
			$where[] = array( '( album_type=1 OR ( album_type=2 AND album_owner_id=? ) OR ( album_type=3 AND ( album_owner_id=? OR ( album_allowed_access IS NOT NULL AND album_allowed_access IN(' . implode( ',', \IPS\Member::loggedIn()->socialGroups() ) . ') ) ) ) )', \IPS\Member::loggedIn()->member_id, \IPS\Member::loggedIn()->member_id );
		}
		else
		{
			$where[] = array( '( album_type=1 OR ( album_type IN (2,3) AND album_owner_id=? ) )', \IPS\Member::loggedIn()->member_id );
		}

		$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_view', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'core_permission_index.perm_view=? )', '*' );
		
		$select = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', $where );
		$select->join( 'gallery_categories', array( "gallery_categories.category_id=gallery_albums.album_category_id" ) );
		$select->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=gallery_categories.category_id", 'gallery', 'category' ) );

		return (bool) $select->first();
	}
	
	/**
	 * Display
	 *
	 * @return	string
	 */
	public function render()
	{
		//\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'profile.css', 'gallery', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery.css', 'gallery', 'front' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery_responsive.css', 'gallery', 'front' ) );
		}
		
		$table = new \IPS\gallery\Album\Table( $this->member->url()->setQueryString( 'tab', 'node_gallery_gallery') );
		$table->setOwner( $this->member );
		$table->limit = 10;
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'gallery' ), 'profileAlbumTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'albums' );
		
		return (string) $table;
	}
}