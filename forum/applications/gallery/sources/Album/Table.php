<?php
/**
 * @brief		Table Builder for Gallery albums
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		17 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\Album;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Table Builder for Gallery albums
 */
class _Table extends \IPS\Helpers\Table\Table
{
	/**
	 * @brief	Container
	 */
	protected $container;

	/**
	 * @brief	Additional CSS classes to apply to columns
	 */
	public $classes = array( 'cGalleryAlbums' );

	/**
	 * @brief	Pagination parameter
	 */
	protected $paginationKey	= 'albumPage';

	/**
	 * @brief	Table resort parameter
	 */
	public $resortKey			= 'albumResort';

	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url|NULL		$url			Base URL (defaults to container URL)
	 * @param	\IPS\Node\Model|NULL	$container		The container
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL, \IPS\Node\Model $container=NULL )
	{
		/* Init */
		$this->container	= $container;
		
		/* Init */
		parent::__construct( ( $url !== NULL ) ? $url : $container->url() );
		
		$this->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'albums' );
		
		/* Set container */
		if ( $container !== NULL )
		{
			$this->where[] = array( 'album_category_id=?', $container->_id );
			
			if ( $this->sortBy === 'album_name' )
			{
				$this->sortDirection = 'asc';
			}
			
			if ( !$this->sortBy and $container->_sortBy !== NULL )
			{
				$this->sortBy = $container->_sortBy;
				$this->sortDirection = $container->_sortOrder;
			}
			if ( !$this->filter )
			{
				$this->filter = $container->_filter;
			}
		}

		/* If we can't moderate in this category, restrict results */
		if( $container === NULL OR !\IPS\gallery\Image::modPermission( 'edit', NULL, $container ) )
		{
			if( count( \IPS\Member::loggedIn()->socialGroups() ) )
			{
				$this->where[]	= array( '( album_type=1 OR ( album_type=2 AND album_owner_id=? ) OR ( album_type=3 AND ( album_owner_id=? OR ( album_allowed_access IS NOT NULL AND album_allowed_access IN(' . implode( ',', \IPS\Member::loggedIn()->socialGroups() ) . ') ) ) ) )', \IPS\Member::loggedIn()->member_id, \IPS\Member::loggedIn()->member_id );
			}
			else
			{
				$this->where[]	= array( '( album_type=1 OR ( album_type IN (2,3) AND album_owner_id=? ) )', \IPS\Member::loggedIn()->member_id );
			}
		}
		else
		{
			$this->where[]	= array( 'album_type<>4' );
		}
		
				
		/* Set available sort options */
		foreach ( array( 'last_img_date', 'count_comments', 'rating_aggregate', 'name', 'count_imgs' ) as $k ) 
		{
			if( $k == 'count_comments' AND ( $container === NULL OR !$this->container->allow_comments ) )
			{
				continue;
			}

			if( $k == 'rating_aggregate' AND ( $container === NULL OR !$this->container->allow_rating ) )
			{
				continue;
			}

			$this->sortOptions[ $k ] = 'album_' . $k;
		}

		if ( !$this->sortBy )
		{
			$this->sortBy = 'album_last_img_date';
		}
	}

	/**
	 * Set owner
	 *
	 * @param	\IPS\Member	$member		The member to filter by
	 * @return	void
	 */
	public function setOwner( \IPS\Member $member )
	{
		$this->where[]	= array( 'album_owner_id=?', $member->member_id );
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	public function getRows( $advancedSearchValues )
	{
		/* Init */
		$class		= "IPS\\gallery\\Album";
		$subquery	= NULL;
		
		/* Check sortBy */
		$this->sortBy	= in_array( $this->sortBy, $this->sortOptions ) ? $this->sortBy : 'album_last_img_date';

		/* What are we sorting by? */
		$sortBy = $this->sortBy . ' ' . ( mb_strtolower( $this->sortDirection ) == 'asc' ? 'asc' : 'desc' );

		/* Specify filter in where clause */
		$where = $this->where ? is_array( $this->where ) ? $this->where : array( $this->where ) : array();
		if ( $this->filter and isset( $this->filters[ $this->filter ] ) )
		{
			$where[] = is_array( $this->filters[ $this->filter ] ) ? $this->filters[ $this->filter ] : array( $this->filters[ $this->filter ] );
		}

		$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_view', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'core_permission_index.perm_view=? )', '*' );

		/* Get results */
		$it = \IPS\Db::i()->select( '*', 'gallery_albums', $where, $sortBy, array( ( $this->limit * ( $this->page - 1 ) ), $this->limit ), NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		$it->join( 'gallery_categories', array( "gallery_categories.category_id=gallery_albums.album_category_id" ) );
		$it->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=gallery_categories.category_id", 'gallery', 'category' ) );

		$this->pages = ceil( $it->count( TRUE ) / $this->limit );

		$rows = iterator_to_array( $it );

		foreach( $rows as $index => $row )
		{
			$rows[ $index ]	= \IPS\gallery\Album::constructFromData( $row );
		}

		/* Return */
		return $rows;
	}

	/**
	 * Return the table headers
	 *
	 * @param	array|NULL	$advancedSearchValues	Advanced search values
	 * @return	array
	 */
	public function getHeaders( $advancedSearchValues )
	{
		return array();
	}
}