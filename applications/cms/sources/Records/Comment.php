<?php
/**
 * @brief		Post Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		8 Jan 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\Records;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Post Model
 */
class _Comment extends \IPS\Content\Comment implements \IPS\Content\ReportCenter, \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Reputation, \IPS\Content\Shareable, \IPS\Content\Searchable, \IPS\Content\Embeddable
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = NULL;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_database_comments';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'comment_';
	
	/**
	 * @brief	Application
	 */
	public static $application = 'cms';

	/**
	 * @brief	Title
	 */
	public static $title = 'content_comment';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'record_id',
		'author'			=> 'user',
		'author_name'		=> 'author',
		'content'			=> 'post',
		'date'				=> 'date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_date',
		'edit_show'			=> 'edit_show',
		'edit_member_name'	=> 'edit_member_name',
		'edit_member_id'    => 'edit_member_id',
		'edit_reason'		=> 'edit_reason',
		'approved'			=> 'approved',
	#	'first'				=> 'new_topic'
	);
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'comment';
	
	/**
	 * @brief	Reputation Type
	 */
	public static $reputationType = 'id';
	
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'display', 'cms', 'database' ), 'commentContainer' );

	/**
	 * @brief	Database ID
	 */
	public static $customDatabaseId = NULL;
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'ccs-records';
	
	/**
	 * @brief Store generated search index titles for efficiency
	 */
	protected static $searchIndexTitles = array();
	
	/**
	 * Return custom where for SQL delete
	 *
	 * @param   int     $id     Content item to delete from
	 * @return array
	 */
	public static function deleteWhereSql( $id )
	{
		return array( array( static::$databasePrefix . static::$databaseColumnMap['item'] . '=?', $id ), array( static::$databasePrefix . 'database_id=?', static::$customDatabaseId ) );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	string		$view			'expanded' or 'condensed'
	 * @param	bool		$asItem	Displaying results as items?
	 * @param	bool		$canIgnoreComments	Can ignore comments in the result stream? Activity stream can, but search results cannot.
	 * @return	string
	 */
	public static function searchResult( array $indexData, array $authorData, array $itemData, array $containerData = NULL, array $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments=FALSE )
	{
		/* Ensure that the comment title is formatted correctly */
		try
		{
			if ( ! isset( static::$searchIndexTitles[ $itemData['primary_id_field'] ] ) )
			{
				$fields     = '\IPS\cms\Fields' .  static::$customDatabaseId;
				$databases  = \IPS\cms\Databases::databases();
				$titleField = $databases[ static::$customDatabaseId ]->field_title;
				
				static::$searchIndexTitles[ $itemData['primary_id_field'] ] = $fields::load( $databases[ static::$customDatabaseId ]->field_title )->displayValue( $itemData['field_' . $databases[ static::$customDatabaseId ]->field_title ] );
			}

			$itemData['field_' . $databases[ static::$customDatabaseId ]->field_title ] = static::$searchIndexTitles[ $itemData['primary_id_field'] ];
		}
		catch ( \Exception $ex ) { }

		return parent::searchResult( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $iPostedIn, $view, $asItem );
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		$this->database_id = static::$customDatabaseId;
		$this->save();
		
		$item = $this->item();
		if ( $item->hidden() OR ( \IPS\cms\Databases::load( static::$customDatabaseId )->_comment_bump & \IPS\cms\Databases::BUMP_ON_COMMENT ) )
		{
			parent::postCreate();
		}
		else
		{
			/* No bump, so don't update the record's last_action stuff */
			if ( isset( $item::$databaseColumnMap['num_comments'] ) )
			{
				$item->resyncCommentCounts();
				$item->save();
			}
				
			if ( $item->containerWrapper() AND $item->container()->_comments !== NULL )
			{
				$item->container()->_comments = ( $item->container()->_comments + 1 );
				$item->container()->save();
			}
		}
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$template = static::$commentTemplate[1];
		static::$commentTemplate[0][0] = $this->item()->database()->template_display;
		return call_user_func_array( array( \IPS\cms\Theme::i(), 'getTemplate' ), static::$commentTemplate[0] )->$template( $this->item(), $this );
	}
	
	/**
	 * Get URL for doing stuff
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$url = parent::url( $action );
		
		if ( $action !== NULL )
		{
			$url = $url->setQueryString( 'd', static::$customDatabaseId );
		}
		
		return $url;
	}
	
	/**
	 * Get attachment IDs
	 *
	 * @return	array
	 */
	public function attachmentIds()
	{
		$item = $this->item();
		$idColumn = $item::$databaseColumnId;
		$commentIdColumn = static::$databaseColumnId;
		return array( $this->item()->$idColumn, $this->$commentIdColumn, static::$customDatabaseId ); 
	}

	/**
	 * Addition where needed for fetching comments
	 *
	 * @return	array|NULL
	 */
	public static function commentWhere()
	{
		return array( 'comment_database_id=?', static::$customDatabaseId );
	}
}