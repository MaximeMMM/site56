<?php
/**
 * @brief		Blog Node
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		3 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog Node
 */
class _Blog extends \IPS\Node\Model implements \IPS\Node\Ratings, \IPS\Content\Embeddable
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'blog_blogs';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'blog_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'member_id',
		'title'					=> 'name',
		'views'					=> 'num_views',
		'pinned'				=> 'pinned',
		'featured'				=> 'featured',
		'date'					=> 'last_edate',
		'cover_photo'			=> 'cover_photo',
		'cover_photo_offset'	=> 'cover_photo_offset'
	);
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'blogs';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'blogs_blog_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';
	
	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'blogs';
	
	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\blog\Entry';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';
	
	/**
	* @brief	[Node] If the node can be "owned", the owner "type" (typically "member" or "group") and the associated database column
	*/
	public static $ownerTypes = array( 'member' => 'member_id', 'group' => array( 'ids' => 'groupblog_ids', 'name' => 'groupblog_name' ) );
	
	/**
	 * @brief	[Node] By mapping appropriate columns (rating_average and/or rating_total + rating_hits) allows to cache rating values
	 */
	public static $ratingColumnMap	= array(
			'rating_average'	=> 'rating_average',
			'rating_total'		=> 'rating_total',
			'rating_hits'		=> 'rating_count',
	);
	
	/**
	 * @brief	Cover Photo Storage Extension
	 */
	public static $coverPhotoStorageExtension = 'blog_Blogs';

	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'blog_name';
		$return[] = 'blog_member_id';
		return $return;
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'blog' ), 'rows' );
	}
	
	/**
	 * Can create blog?
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $member->member_id and $member->group['g_blog_allowlocal'] )
		{
			if ( $member->group['g_blog_maxblogs'] )
			{
				return ( \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', array( 'blog_member_id=?', $member->member_id ) )->first() < $member->group['g_blog_maxblogs'] );
			}
			
			return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Get title from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the author. Only includes columns returned by container::basicDataColumns()
	 * @return	\IPS\Http\Url
	 */
	public static function titleFromIndexData( $indexData, $itemData, $containerData )
	{
		return ( $containerData['blog_member_id'] ) ? $containerData['blog_name'] : parent::titleFromIndexData( $indexData, $itemData, $containerData );
	}

	/**
	 * Check permissions
	 *
	 * @param	mixed								$permission		A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 */
	public function can( $permission, $member=NULL )
	{		
		if ( $this->social_group )
		{
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				return FALSE;
			}
			
			if ( \IPS\Member::loggedIn()->member_id !== $this->member_id )
			{
				try
				{
					\IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=? AND member_id=?', $this->social_group, \IPS\Member::loggedIn()->member_id ) )->first();
				}
				catch ( \UnderflowException $e )
				{
					return FALSE;
				}
			}
		}
		
		if ( $permission === 'add' )
		{
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				return FALSE;
			}
			elseif ( \IPS\Member::loggedIn()->member_id === $this->member_id )
			{
				return TRUE;
			}
			else
			{
				if ( $this->groupblog_ids )
				{
					return \IPS\Member::loggedIn()->inGroup( explode( ',', $this->groupblog_ids ) );
				}
				else
				{
					return FALSE;
				}
			}
		}
		
		return parent::can( $permission, $member );
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		$return = parent::searchIndexPermissions();
		
		if ( $this->social_group )
		{
			$return = ( $return === '*' ) ? array() : explode( ',', $return );
			
			if ( $this->member_id )
			{
				$return[] = "m{$this->member_id}";
			}
			$return[] = "s{$this->social_group}";
			
			$return = implode( ',', array_unique( $return ) );
		}
		
		return $return;
	}
	
	/**
	 * Additional WHERE clauses for Follow view
	 *
	 * @return	array
	 */
	public static function followWhere()
	{
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$where = array( array( '( blog_blogs.blog_social_group IS NULL OR blog_blogs.blog_member_id=' . \IPS\Member::loggedIn()->member_id . ' OR ( ' . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() ) . ' ) )' ) );
		}
		else
		{
			$where = array( \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', \IPS\Member::loggedIn() ) );
		}
		
		return $where;
	}
		
	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		foreach ( \IPS\Db::i()->select( 'data', 'core_queue', array( 'app=? AND `key`=?', 'core', 'DeleteOrMoveContent' ) ) as $row )
		{
			$data = json_decode( $row, TRUE );
			if ( $data['class'] === get_class( $this ) and $data['id'] == $this->_id )
			{
				return FALSE;
			}
		}
		
		return static::restrictionCheck( 'delete' ) or ( $this->member_id === \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->group['g_blog_allowdelete'] );
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form, $public=FALSE )
	{
		if( $public )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'blog_name', $this->id ? $this->_title : NULL, TRUE, array(), NULL, NULL, NULL, 'blog_name' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'blog_desc', $this->id ? $this->description : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}" : "blogs-new-blog" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ), NULL, NULL, NULL, 'blog_desc_wrap' ) );
		}
		else
		{
			$form->addHeader( 'blog_settings' );

			/* Owned blogs */
			$form->add( new \IPS\Helpers\Form\Text( 'blog_name', $this->id ? $this->_title : NULL, FALSE, array(), function( $value ) {
				if ( \IPS\Request::i()->blog_type === 'member' AND !$value )
				{
					throw new \InvalidArgumentException( 'form_required' );
				}
			}, NULL, NULL, 'blog_name' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'blog_desc', $this->id ? $this->desc : NULL, FALSE, array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}m" : "blogs-new-blogm" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ), NULL, NULL, NULL, 'blog_desc_wrap' ) );

			/* Group blogs - only one or the other will show at any given time */
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_name_group', ( $this->id AND !$this->groupblog_ids ) ? $this->_title : NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_blog_{$this->id}" : NULL ) ), function( $value ) {
				if ( \IPS\Request::i()->blog_type !== 'member' AND !$value )
				{
					throw new \InvalidArgumentException( 'form_required' );
				}
			}, NULL, NULL, 'blog_name_group' ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_desc_group', NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_blog_{$this->id}_desc" : NULL ), 'editor' => array( 'app' => 'blog', 'key' => 'Blogs', 'autoSaveKey' => ( $this->id ? "blogs-blog-{$this->id}-group" : "blogs-new-blog-group" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'blog_desc_placeholder' ) ), NULL, NULL, NULL, 'blog_desc_group_wrap' ) );
		
			$groups = array();
			foreach ( \IPS\Member\Group::groups() as $k => $v )
			{
				$groups[ $k ] = $v->name;
			}
			
			$type = 'member';
			if ( $this->id )
			{
				if ( $this->groupblog_ids )
				{
					$type = 'group';
				}
			}
			
			$id = $this->id ?: 'new';
	
			$form->add( new \IPS\Helpers\Form\Radio( 'blog_type', $type, TRUE, array(
					'options' => array(
							'member' 	=> 'blog_type_normal',
							'group' 	=> 'blog_type_group'
					),
					'toggles'	=> array(
							'member'	=> array( 'blog_member_id', 'blog_name', 'blog_desc_wrap' ),
							'group'		=> array( 'blog_groupblog_ids', 'blog_groupblog_name', 'blog_name_group', 'blog_desc_group_wrap' )
					)
			) ) );
			
			$form->add( new \IPS\Helpers\Form\Member( 'blog_member_id', $this->member_id ? \IPS\Member::load( $this->member_id ) : NULL, FALSE, array(), function( $member ) use ( $form )
			{
				if ( \IPS\Request::i()->blog_type === 'member' )
				{
					if( !is_object( $member ) or !$member->member_id )
					{
						throw new \InvalidArgumentException( 'no_blog_author_selected' );
					}
				}
			},
			NULL, NULL, 'blog_member_id' ) );
	
			$form->add( new \IPS\Helpers\Form\Select( 'blog_groupblog_ids', $this->id ? explode( ',', $this->groupblog_ids ) : array(), FALSE, array( 'options' => $groups, 'multiple' => TRUE ), NULL, NULL, NULL, 'blog_groupblog_ids' ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'blog_groupblog_name', NULL, FALSE, array( 'app' => 'blog', 'key' => ( $this->id ? "blogs_groupblog_name_{$this->id}" : NULL ) ), NULL, NULL, NULL, 'blog_groupblog_name' ) );				
		}

		if ( \IPS\Member::loggedIn()->group['g_blog_allowprivate'] )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'blog_privacy', $this->social_group ? 'private' : 'open', FALSE, array(
				'options' => array(
					'open' 		=> 'blog_privacy_open',
					'private' 	=> 'blog_privacy_private'
				),
				'toggles'	=> array(
					'private'		=> array( 'blog_social_group' )
				)
			) ) );
			$form->add( new \IPS\Helpers\Form\SocialGroup( 'blog_social_group', $this->social_group, NULL, array( 'owner' => $this->owner() ), NULL, NULL, NULL, 'blog_social_group' ) );
		}
		
		if( \IPS\Settings::i()->blog_allow_rss )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_rss', $this->id ? $this->settings['allowrss'] : TRUE ) );
		}
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['blog_type'] ) and $values['blog_type'] == 'member' and isset( $values['blog_member_id'] ) and is_object( $values['blog_member_id'] ) )
		{
			$values['blog_member_id']->create_menu = NULL;
			$values['blog_member_id']->save();
			$values['blog_member_id']		= $values['blog_member_id']->member_id;
			$values['blog_groupblog_ids']	= '';
			$this->member_id				= $values['blog_member_id'];
		}
		else if ( !$this->id and !isset( $values['blog_type'] ) )
		{
			$values['blog_member_id'] = \IPS\Member::loggedIn()->member_id;
			\IPS\Member::loggedIn()->create_menu = NULL;
			\IPS\Member::loggedIn()->save();
			$this->member_id = $values['blog_member_id'];
		}
		else if ( isset( $values['blog_type'] ) )
		{
			$this->member_id = 0;
		}
		
		if( isset( $values['blog_type'] ) )
		{
			unset( $values['blog_type'] );
		}
		
		$this->massUpdateIndex = FALSE;
		if ( isset( $values['blog_privacy'] ) and $values['blog_privacy'] === 'private' )
		{
			if ( $this->id and !$this->social_group )
			{
				$this->massUpdateIndex = TRUE;
			}
		}
		else
		{
			if ( $this->id and $this->social_group )
			{
				$this->massUpdateIndex = TRUE;

				\IPS\Db::i()->delete( 'core_sys_social_groups', array( 'group_id=?', $this->social_group ) );
				\IPS\Db::i()->delete( 'core_sys_social_group_members', array( 'group_id=?', $this->social_group ) );
			}
			
			$values['blog_social_group'] = NULL;
		}

		if( isset($values['blog_privacy'] ) )
		{
			unset( $values['blog_privacy'] );
		}

		if ( !$this->id )
		{
			$this->save();

			if( $values['blog_member_id'] )
			{
				\IPS\File::claimAttachments( 'blogs-new-blog', $this->id, NULL, 'description', TRUE );
			}
			else
			{
				\IPS\File::claimAttachments( 'blogs-new-blog-group', $this->id, NULL, 'description', TRUE );
			}
		}

		/* If this is not a member blog we store the languages in the language system */
		if( !$this->member_id )
		{
			foreach ( array( 'blog_name_group' => "blogs_blog_{$this->id}", 'blog_desc_group' => "blogs_blog_{$this->id}_desc", 'blog_groupblog_name' => "blogs_groupblog_name_{$this->id}" ) as $fieldKey => $langKey )
			{
				if ( isset( $values[ $fieldKey ] ) )
				{
					\IPS\Lang::saveCustom( 'blog', $langKey, $values[ $fieldKey ] );
		
					if ( $fieldKey === 'blog_name' )
					{
						$values['seo_name'] = \IPS\Http\Url::seoTitle( ( is_array( $values[ $fieldKey ] ) ) ? $values[ $fieldKey ][ \IPS\Lang::defaultLanguage() ] : $values[ $fieldKey ] );
					}
		
					unset( $values[ $fieldKey ] );
				}
			}

			if( array_key_exists( 'blog_name', $values ) )
			{
				unset( $values['blog_name'] );
			}

			if( array_key_exists( 'blog_desc', $values ) )
			{
				unset( $values['blog_desc'] );
			}
		}
		else
		{
			try
			{
				$feed = \IPS\blog\Blog\Feed::constructFromData( \IPS\Db::i()->select( '*', 'blog_rss_import', array( 'rss_blog_id=?', $this->id ) )->first() );
			}
			catch ( \UnderflowException $e )
			{
				$feed = NULL;
			}

			if( $feed )
			{
				$feed->member = $values['blog_member_id'];
				$feed->save();
			}

			/* This is here in case an admin changes a group blog to a member blog */
			\IPS\Lang::deleteCustom( 'blog', "blogs_blog_{$this->id}" );
			\IPS\Lang::deleteCustom( 'blog', "blogs_blog_{$this->id}_desc" );
			\IPS\Lang::deleteCustom( 'blog', "blogs_groupblog_name_{$this->id}" );

			$values['seo_name'] = \IPS\Http\Url::seoTitle( $values['blog_name'] );
		}

		if( array_key_exists( 'blog_name_group', $values ) )
		{
			unset( $values['blog_name_group'] );
		}

		if( array_key_exists( 'blog_desc_group', $values ) )
		{
			unset( $values['blog_desc_group'] );
		}

		if( array_key_exists( 'blog_groupblog_name', $values ) )
		{
			unset( $values['blog_groupblog_name'] );
		}

		if( array_key_exists( 'blog_enable_rss', $values ) )
		{
			$values['settings'] =  array( 'allowrss' => $values['blog_enable_rss'] );
			unset( $values['blog_enable_rss'] );
		}

		/* Send to parent */
		if( array_key_exists( 'blog_member_id', $values ) )
		{
			unset( $values['blog_member_id'] );
		}

		return $values;
	}

	/**
	 * [Node] Get the title to store in the log
	 *
	 * @return	string|null
	 */
	public function titleForLog()
	{
		if ( !$this->member_id )
		{
			try
			{
				return \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( static::$titleLangPrefix . $this->_id );
			}
			catch( \UnderflowException $e )
			{
				/* If we're changing from a member blog to a group blog, the language string won't exist yet */
				return $this->_title;
			}
		}
		else
		{
			return $this->_title;
		}
	}

	/**
	 * @brief	Mass update search index after changes
	 */
	protected $massUpdateIndex	= FALSE;

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		/* Update index? */
		if ( $this->massUpdateIndex )
		{
			\IPS\Content\Search\Index::i()->massUpdate( 'IPS\blog\Entry', $this->id, NULL, $this->searchIndexPermissions() );
		}
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_seo_name()
	{
		/* If we have the seo_name, just return it - don't query the language string */
		if( $this->_data['seo_name'] )
		{
			return $this->_data['seo_name'];
		}

		/* The seo_name isn't set, so let's fix that real quick and return it */
		$title = $this->member_id ? $this->name : \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'blogs_blog_' . $this->id );

		$this->seo_name	= \IPS\Http\Url::seoTitle( $title );
		$this->save();

		return $this->seo_name;
	}

	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=blog&module=blogs&controller=view&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'blogs_blog';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'seo_name';
	
	/**
	 * @brief	Cached latest entry
	 */
	protected $latestEntry = NULL;
	
	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	void
	 * @note	Like Gallery, we actually want to set this to the last entry rather than comment. As such $comment is ignored.
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$lastUpdateColumn	= static::$databaseColumnMap['date'];
		
		if ( $latestEntry = $this->latestEntry() )
		{
			$this->$lastUpdateColumn = $latestEntry->date;
		}
		else
		{
			$this->$lastUpdateColumn = 0;
		}
		
		$this->save();
	}

	/**
	 * Get latest entry
	 *
	 * @return	\IPS\blog\Entry|NULL
	 */
	public function latestEntry()
	{
		if( $this->latestEntry !== NULL )
		{
			return $this->latestEntry;
		}

		try
		{
			/* @note entry_hidden is flipped to map to "approved" */
			$this->latestEntry = \IPS\blog\Entry::constructFromData( \IPS\Db::i()->select( '*', 'blog_entries', array( 'entry_blog_id=? AND entry_is_future_entry=0 AND entry_hidden=1 AND entry_status!=?', $this->_id, 'draft' ), 'entry_date DESC', 1 )->first() );
			return $this->latestEntry;
		}
		catch ( \UnderflowException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Contributors
	 *
	 * @return	array
	 */
	public function contributors()
	{
		$contributors = array();
		
		try 
		{
			/* Get member IDs and contributions count */
			$select = \IPS\Db::i()->select(
					"entry_author_id, count( entry_id ) as contributions",
					'blog_entries',
					array( "entry_blog_id=? AND entry_author_id !=?", $this->id, 0 ),
					"contributions DESC",
					NULL,
					array( 'entry_author_id' )
			)->setKeyField( 'entry_author_id' )->setValueField( 'contributions' );

			/* Get the member ids to load them in one query */
			$memberIds	= array();
			$members	= array();

			foreach( $select as $member => $contributions )
			{
				$memberIds[] = $member;
			}

			if( count( $memberIds ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'core_members', 'member_id IN(' . implode( ',', $memberIds ) . ')' ) as $member )
				{
					$members[ $member['member_id'] ] = \IPS\Member::constructFromData( $member );
				}
			}

			/* Get em! */
			foreach( $select as $member => $contributions )
			{
				$contributors[] = array( 'member' => $members[ $member ], 'contributions' => $contributions );
			}
		}
		catch ( \UnderflowException $e ) {}

		return $contributors;
	}
	
	/**
	 * Retrieve recent entries
	 *
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function get__recentEntries()
	{
		return \IPS\blog\Entry::getItemsWithPermission( array( array( 'entry_blog_id=? AND entry_is_future_entry=0 AND entry_status!=?', $this->id, 'draft' ) ), NULL, 5 );
	}
	
	/**
	 * [Node] Get number of content items
	 *
	 * @return	int
	 */
	protected function get__items()
	{
		return $this->count_entries;
	}
	
	/**
	 * [Node] Get number of content comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return $this->count_comments;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unnapprovedItems()
	{
		return $this->count_entries_hidden;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return $this->count_comments_hidden;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	void
	 */
	protected function set__items( $val )
	{
		$this->count_entries = (int) $val;
	}
	
	/**
	 * Set number of items
	 *
	 * @param	int	$val	Comments
	 * @return	void
	 */
	protected function set__comments( $val )
	{
		$this->count_comments = (int) $val;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->count_entries_hidden = $val;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @param	int	$val	Unapproved Comments
	 * @return	void
	 */
	protected function set__unapprovedComments( $val )
	{
		$this->count_comments_hidden = $val;
	}
	
	/**
	 * [Node] Get number of future publishing items
	 *
	 * @return	int
	 */
	protected function get__futureItems()
	{
		return $this->count_entries_future;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__futureItems( $val )
	{
		$this->count_entries_future = $val;
	}
	
	/**
	 * Returns the title
	 *
	 * @return string
	 */
	protected function get__title()
	{
		return $this->member_id ? $this->name : \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id );
	}
	
	/**
	 * Node titles can contain HTML. Apparently.
	 *
	 * @return string
	 */
	public function get__stripTagsTitle()
	{
		if ( $this->member_id )
		{
			return $this->name;
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id, NULL, array( 'striptags' => true ) );
		}
	}

	/**
	 * Returns the title
	 *
	 * @return string
	 */
	protected function get_description()
	{
		return $this->member_id ? $this->desc : \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix );
	}

	/**
	 * Get settings
	 *
	 * @return	array
	 */
	public function get_settings()
	{
		$settings = json_decode( $this->_data['settings'], TRUE );

		return $settings;
	}
	
	/**
	 * Set settings
	 *
	 * @param	array	$values	Values
	 * @return	void
	 */
	public function set_settings( $values )
	{
		$this->_data['settings'] = json_encode( $values );
	}
	
	/**
	 * Ping Ping-o-matic
	 *
	 * @return	void
	 */
	public function ping()
	{		
		$xml = \IPS\Xml\SimpleXML::create('methodCall');
	
		$methodName = $xml->addChild( 'methodName', 'weblogUpdates.ping' );
		$params = $xml->addChild( 'params' );
 		$params->addChild( 'param' )->addChild( 'value', $this->_title );
 		$params->addChild( 'param' )->addChild( 'value', $this->url() );
		
		try
		{
	 		\IPS\Http\Url::external( 'http://rpc.pingomatic.com/RPC2' )
			->request()
			->setHeaders( array( 'Content-Type' => 'text/xml', 'User-Agent' => "InvisionPowerServices/" . \IPS\Application::load('core')->long_version ) )
			->post( $xml->asXML() );
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			\IPS\Log::log( $e, 'pingomatic' );
		}
	}

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'blog' ), 'rows' );
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'blog_rss_import', array( "rss_blog_id=?", $this->_id ) );

		foreach ( array( 'blog_groupblog_name' => "blogs_groupblog_name_{$this->id}" ) as $fieldKey => $langKey )
		{
			\IPS\Lang::deleteCustom( 'blog', $langKey );
		}
		
		$member = \IPS\Member::load( $this->member_id );
		$member->create_menu = NULL;
		$member->save();

		\IPS\File::unclaimAttachments( 'blog_Blogs', $this->id );

		return parent::delete();
	}
	
	/**
	 * Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto()
	{
		$photo = parent::coverPhoto();
		$photo->overlay = \IPS\Theme::i()->getTemplate( 'view', 'blog' )->coverPhotoOverlay( $this );
		return $photo;
	}
	
	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'blog' )->embedBlogs( $this, $this->url()->setQueryString( $params ) );
	}

	/**
	 * [Node] Get content table meta description 
	 *
	 * @return	string
	 */
	public function metaDescription()
	{
		if( $this->member_id )
		{
			return strip_tags( $this->desc );
		}

		return parent::metaDescription();
	}
	
	/**
	 * Get output for API
	 *
	 * @return	array
	 * @apiresponse	int					id			ID number
	 * @apiresponse	string				name		Name
	 * @apiresponse	string				description	Description
	 * @apiresponse	\IPS\Member			owner		If the blog is owned by a single member, the member
	 * @apiresponse	[\IPS\Member\Group]	groups		If the blog is owned by groups, an array of the groups
	 * @apiresponse	bool				pinned		If the blog is pinned
	 * @apiresponse	int					entries		Number of entries
	 * @apiresponse	int					comments	Number of comments
	 * @apiresponse	string				url			URL
	 */
	public function apiOutput()
	{
		$groups = array();
		if ( $this->groupblog_ids )
		{
			foreach ( array_filter( explode( ',', $this->groupblog_ids ) ) as $groupId )
			{
				try
				{
					$groups[] = \IPS\Member\Group::load( $groupId )->apiOutput();
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		return array(
			'id'			=> $this->id,
			'name'			=> $this->_title,
			'description'	=> $this->member_id ? $this->desc : \IPS\Member::loggedIn()->language()->addToStack("blogs_blog_{$this->id}_desc"),
			'owner'			=> $this->member_id ? $this->owner()->apiOutput() : null,
			'groups'		=> $groups,
			'pinned'		=> (bool) $this->pinned,
			'entries'		=> $this->count_entries,
			'comments'		=> $this->count_comments,
			'url'			=> (string) $this->url()
		);
	}
	
	/**
	 * Permission Types
	 *
	 * @return	array
	 */
	public function permissionTypes()
	{
		return array( 'view' => 'view', 'add' => 'add' );
	}
}