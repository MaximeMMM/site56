<?php
/**
 * @brief		Records Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		8 April 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Records Model
 */
class _Records extends \IPS\Content\Item implements
	\IPS\Content\Permissions,
	\IPS\Content\Pinnable, \IPS\Content\Lockable, \IPS\Content\Hideable, \IPS\Content\Featurable,
	\IPS\Content\Tags,
	\IPS\Content\Followable,
	\IPS\Content\Shareable,
	\IPS\Content\Reputation,
	\IPS\Content\ReportCenter,
	\IPS\Content\ReadMarkers,
	\IPS\Content\Views,
	\IPS\Content\Ratings,
	\IPS\Content\Searchable,
	\IPS\Content\FuturePublishing,
	\IPS\Content\Embeddable
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = NULL;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'primary_id_field';

    /**
     * @brief	[ActiveRecord] Database ID Fields
     */
    protected static $databaseIdFields = array('record_static_furl', 'record_topicid');

	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';

	/**
	 * @brief	Application
	 */
	public static $application = 'cms';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'records';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = NULL;
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = NULL;
	
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = NULL;
	
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	[Content\Item]	Form field label prefix
	 */
	public static $formLangPrefix = 'content_record_form_';
	
	/**
	 * @brief	[Records] Custom Database Id
	 */
	public static $customDatabaseId = NULL;
	
	/**
	 * @brief 	[Records] Database object
	 */
	protected static $database = array();
	
	/**
	 * @brief 	[Records] Database object
	 */
	public static $title = 'content_record_title';
		
	/**
	 * @brief	[Recordss] Standard fields
	 */
	protected static $standardFields = array( 'record_publish_date', 'record_expiry_date', 'record_allow_comments', 'record_comment_cutoff' );
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'ccs-records';

	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';

	/**
	 * @brief	Reputation Type
	 */
	public static $reputationType = NULL;
	
	/**
	 * @brief	Include In Sitemap (We do not want to include in Content sitemap, as we have a custom extension
	 */
	public static $includeInSitemap = FALSE;
	
	/**
	 * @brief	Prevent custom fields being fetched twice when loading/saving a form
	 */
	public static $customFields = NULL;
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );

		/* Prevent infinite redirects */
		if ( ! $obj->record_dynamic_furl and ! $obj->record_static_furl )
		{
			if ( $obj->_title )
			{
				$obj->record_dynamic_furl = \IPS\Http\Url::seoTitle( $obj->_title );
				$obj->save();
			}
		}

		if ( $obj->useForumComments() )
		{
			$obj::$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;
		}

		return $obj;
	}

	/**
	 * Set custom posts per page setting
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		if ( ! empty( \IPS\cms\Databases\Dispatcher::i()->recordId ) )
		{
			$class = 'IPS\cms\Records' . static::$customDatabaseId;
			$record = $class::load( \IPS\cms\Databases\Dispatcher::i()->recordId );
			
			if ( $record->_forum_record and $record->_forum_comments and \IPS\Application::appIsEnabled('forums') )
			{
				return \IPS\forums\Topic::getCommentsPerPage();
			}
		}
		else if( static::database()->forum_record and static::database()->forum_comments and \IPS\Application::appIsEnabled('forums') )
		{
			return \IPS\forums\Topic::getCommentsPerPage();
		}

		return static::database()->field_perpage;
	}

	/**
	 * Returns the database parent
	 * 
	 * @return \IPS\cms\Databases
	 */
	public static function database()
	{
		if ( ! isset( static::$database[ static::$customDatabaseId ] ) )
		{
			static::$database[ static::$customDatabaseId ] = \IPS\cms\Databases::load( static::$customDatabaseId );
		}
		
		return static::$database[ static::$customDatabaseId ];
	}
	
	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		$qs = array_merge( $url->queryString, $url->getFriendlyUrlData() );
		
		if ( isset( $qs['path'] ) )
		{
			$bits = explode( '/', trim( $qs['path'], '/' ) );
			$path = array_pop( $bits );
			
			try
			{
				return static::loadFromSlug( $path, FALSE );
			}
			catch ( \Exception $e ) { }
		}
		
		return parent::loadFromUrl( $url );
	}

	/**
	 * Load from slug
	 * 
	 * @param	string		$slug							Thing that lives in the garden and eats your plants
	 * @param	bool		$redirectIfSeoTitleIsIncorrect	If the SEO title is incorrect, this method may redirect... this stops that
	 * @return	\IPS\cms\Record
	 */
	public static function loadFromSlug( $slug, $redirectIfSeoTitleIsIncorrect=TRUE )
	{
		$slug = trim( $slug, '/' );

		/* Try the easiest option */
		preg_match( '#-r(\d+?)$#', $slug, $matches );

		if ( isset( $matches[1] ) AND is_numeric( $matches[1] ) )
		{
			try
			{
				$record = static::load( $matches[1] );

				/* Check to make sure the SEO title is correct */
				if ( $redirectIfSeoTitleIsIncorrect and urldecode( str_replace( $matches[0], '', $slug ) ) !== $record->record_dynamic_furl and !\IPS\Request::i()->isAjax() and mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\ENFORCE_ACCESS )
				{
					$url = $record->url();

					/* Redirect to the embed form if necessary */
					if( isset( \IPS\Request::i()->do ) and \IPS\Request::i()->do == 'embed' )
					{
						$url = $url->setQueryString( array( 'do' => "embed" ) );
					}

					\IPS\Output::i()->redirect( $url );
				}

				static::$multitons[ $record->primary_id_field ] = $record;

				return static::$multitons[ $record->primary_id_field ];
			}
			catch( \OutOfRangeException $ex ) { }
		}

		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, array( '? LIKE CONCAT( record_dynamic_furl, \'%\') OR record_static_furl=?', $slug, $slug ) ) as $record )
		{
			$pass = FALSE;
			
			if ( $slug === $record['record_static_furl'] )
			{
				$pass = TRUE;
			}
			else
			{
				if ( isset( $matches[1] ) AND is_numeric( $matches[1] ) AND $matches[1] == $record['primary_id_field'] )
				{
					$pass = TRUE;
				}
			}
				
			if ( $pass === TRUE )
			{
				static::$multitons[ $record['primary_id_field'] ] = static::constructFromData( $record );
			
				return static::$multitons[ $record['primary_id_field'] ];
			}	
		}
		
		/* Still here? Consistent with AR pattern */
		throw new \OutOfRangeException();	
	}

	/**
	 * Load from slug history so we can 301 to the correct record.
	 *
	 * @param	string		$slug	Thing that lives in the garden and eats your plants
	 * @return	\IPS\cms\Record
	 */
	public static function loadFromSlugHistory( $slug )
	{
		$slug = trim( $slug, '/' );

		try
		{
			$row = \IPS\Db::i()->select( '*', 'cms_url_store', array( 'store_type=? and store_path=?', 'record', $slug ) )->first();

			return static::load( $row['store_current_id'] );
		}
		catch( \UnderflowException $ex ) { }

		/* Still here? Consistent with AR pattern */
		throw new \OutOfRangeException();
	}

	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$language	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( 'content_db_lang_ia_' . static::$customDatabaseId, FALSE );
	}
	
	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$language	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public static function _indefiniteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( 'content_db_lang_ia_' . static::$customDatabaseId, FALSE );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$customValues = ( $item ) ? $item->fieldValues() : array();
		$database     = \IPS\cms\Databases::load( static::$customDatabaseId );
		$fieldsClass  = 'IPS\cms\Fields' .  static::$customDatabaseId;
		$formElements = array();
		$elements     = parent::formElements( $item, $container );
		static::$customFields = $fieldsClass::fields( $customValues, ( $item ? 'edit' : 'add' ), $container, 0, ( ! $item ? NULL : $item ) );

		/* Build the topic state toggles */
		$options = array();
		$toggles = array();
		$values  = array();
		
		/* Title */
		if ( isset( static::$customFields[ $database->field_title ] ) )
		{
			$formElements['title'] = static::$customFields[ $database->field_title ];
		}

		if ( isset( $elements['guest_name'] ) )
		{
			$formElements['guest_name'] = $elements['guest_name'];
		}

		if ( isset( $elements['captcha'] ) )
		{
			$formElements['captcha'] = $elements['captcha'];
		}

		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_record_slugs') )
		{
			$formElements['record_static_furl_set'] = new \IPS\Helpers\Form\YesNo( 'record_static_furl_set', ( ( $item AND $item->record_static_furl ) ? TRUE : FALSE ), FALSE, array(
					'togglesOn' => array( 'record_static_furl' )
			)  );
			$formElements['record_static_furl'] = new \IPS\Helpers\Form\Text( 'record_static_furl', ( ( $item AND $item->record_static_furl ) ? $item->record_static_furl : NULL ), FALSE, array(), function( $val ) use ( $database )
            {
                /* Make sure key is unique */
                if ( empty( $val ) )
                {
                    return true;
                }

                try
                {
                    $cat = intval( ( isset( \IPS\Request::i()->content_record_form_container ) ) ? \IPS\Request::i()->content_record_form_container : 0 );
                    $recordsClass = '\IPS\cms\Records' . $database->id;

                    /* Fetch record by static slug */
                    $record = $recordsClass::load( $val, 'record_static_furl' );

                    /* In the same category though? */
                    if ( isset( \IPS\Request::i()->id ) and $record->_id == \IPS\Request::i()->id )
                    {
                        /* It's ok, it's us! */
                        return true;
                    }

                    if ( $cat === $record->category_id )
                    {
                        throw new \InvalidArgumentException('content_record_slug_not_unique');
                    }
                }
                catch ( \OutOfRangeException $e )
                {
                    /* Slug is OK as load failed */
                    return true;
                }

                return true;
            }, \IPS\Member::loggedIn()->language()->addToStack('record_static_url_prefix', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->base_url ) ) ), NULL, 'record_static_furl' );
		}
		
		if ( isset( $elements['tags'] ) )
		{ 
			$formElements['tags'] = $elements['tags'];
		}

		/* Now custom fields */
		foreach( static::$customFields as $id => $obj )
		{
			if ( $database->field_title === $id )
			{
				continue;
			}

			$formElements['field_' . $id ] = $obj;

			if ( $database->field_content == $id )
			{
				if ( isset( $elements['auto_follow'] ) )
				{
					$formElements['auto_follow'] = $elements['auto_follow'];
				}

				if ( \IPS\Settings::i()->edit_log and $item )
				{
					if ( \IPS\Settings::i()->edit_log == 2 )
					{
						$formElements['record_edit_reason'] = new \IPS\Helpers\Form\Text( 'record_edit_reason', ( $item ) ? $item->record_edit_reason : NULL, FALSE, array( 'maxLength' => 255 ) );
					}
					if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
					{
						$formElements['record_edit_show'] = new \IPS\Helpers\Form\Checkbox( 'record_edit_show', \IPS\Member::loggedIn()->member_id == $item->author()->member_id );
					}
				}
			}
		}

		$postKey = ( $item ) ? $item->_post_key : md5( uniqid() );

		if ( $fieldsClass::fixedFieldFormShow( 'record_publish_date' ) AND \IPS\Member::loggedIn()->modPermission( "can_future_publish_content" ) )
		{
			$formElements['record_publish_date'] = $elements['date'];
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_image' ) )
		{
			$fixedFieldSettings = static::database()->fixed_field_settings;
			$dims = TRUE;

			if ( isset( $fixedFieldSettings['record_image']['image_dims'] ) )
			{
				$dims = array( 'maxWidth' => $fixedFieldSettings['record_image']['image_dims'][0], 'maxHeight' => $fixedFieldSettings['record_image']['image_dims'][1] );
			}

			$formElements['record_image'] = new \IPS\Helpers\Form\Upload( 'record_image', ( ( $item and $item->record_image ) ? \IPS\File::get( 'cms_Records', $item->record_image ) : NULL ), FALSE, array( 'image' => $dims, 'storageExtension' => 'cms_Records', 'postKey' => $postKey, 'multiple' => false ), NULL, NULL, NULL, 'record_image' );
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_expiry_date' ) )
		{
			$formElements['record_expiry_date'] = new \IPS\Helpers\Form\Date( 'record_expiry_date', ( ( $item AND $item->record_expiry_date ) ? \IPS\DateTime::ts( $item->record_expiry_date ) : NULL ), FALSE, array(
					'time'          => true,
					'unlimited'     => -1,
					'unlimitedLang' => 'record_datetime_noval'
			) );
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_allow_comments' ) )
		{
			$formElements['record_allow_comments'] = new \IPS\Helpers\Form\YesNo( 'record_allow_comments', ( ( $item ) ? $item->record_allow_comments : TRUE ), FALSE, array(
					'togglesOn' => array( 'record_comment_cutoff' )
			)  );
		}
		
		if ( $fieldsClass::fixedFieldFormShow( 'record_comment_cutoff' ) )
		{
			$formElements['record_comment_cutoff'] = new \IPS\Helpers\Form\Date( 'record_comment_cutoff', ( ( $item AND $item->record_comment_cutoff ) ? \IPS\DateTime::ts( $item->record_comment_cutoff ) : NULL ), FALSE, array(
					'time'          => true,
					'unlimited'     => -1,
					'unlimitedLang' => 'record_datetime_noval'
			), NULL, NULL, NULL, 'record_comment_cutoff' );
		}

		if ( static::modPermission( 'lock', NULL, $container ) )
		{
			$options['lock'] = 'create_record_locked';
			$toggles['lock'] = array( 'create_record_locked' );
			
			if ( $item AND $item->record_locked )
			{
				$values[] = 'lock';
			}
		}
			
		if ( static::modPermission( 'pin', NULL, $container ) )
		{
			$options['pin'] = 'create_record_pinned';
			$toggles['pin'] = array( 'create_record_pinned' );
			
			if ( $item AND $item->record_pinned )
			{
				$values[] = 'pin';
			}
		}
			
		if ( static::modPermission( 'hide', NULL, $container ) )
		{
			$options['hide'] = 'create_record_hidden';
			$toggles['hide'] = array( 'create_record_hidden' );
			
			if ( $item AND $item->record_approved === -1 )
			{
				$values[] = 'hide';
			}
		}
			
		if ( static::modPermission( 'feature', NULL, $container ) )
		{
			$options['feature'] = 'create_record_featured';
			$toggles['feature'] = array( 'create_record_featured' );

			if ( $item AND $item->record_featured === 1 )
			{
				$values[] = 'feature';
			}
		}
		
		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			$formElements['record_meta_keywords'] = new \IPS\Helpers\Form\TextArea( 'record_meta_keywords', $item ? $item->record_meta_keywords : '', FALSE );
			$formElements['record_meta_description'] = new \IPS\Helpers\Form\TextArea( 'record_meta_description', $item ? $item->record_meta_description : '', FALSE );
		}
		
		if ( count( $options ) or count ( $toggles ) )
		{
			$formElements['create_record_state'] = new \IPS\Helpers\Form\CheckboxSet( 'create_record_state', $values, FALSE, array(
					'options' 	=> $options,
					'toggles'	=> $toggles,
					'multiple'	=> TRUE
			) );
		}

		return $formElements;
	}

	/**
	 * Total item count (including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCount( \IPS\Node\Model $container, $includeItems=TRUE, $includeComments=FALSE, $includeReviews=FALSE, $depth=0 )
	{
		/* Are we in too deep? */
		if ( $depth > 10 )
		{
			return '+';
		}

		$count = $container->_items;

		if ( static::canViewHiddenItems( NULL, $container ) )
		{
			$count += $container->_unapprovedItems;
		}

		if ( static::canViewFutureItems( NULL, $container ) )
		{
			$count += $container->_futureItems;
		}

		if ( $includeComments )
		{
			$count += $container->record_comments;
		}

		/* Add Children */
		$childDepth	= $depth++;
		foreach ( $container->children() as $child )
		{
			$toAdd = static::contentCount( $child, $includeItems, $includeComments, $includeReviews, $childDepth );
			if ( is_string( $toAdd ) )
			{
				return $count . '+';
			}
			else
			{
				$count += $toAdd;
			}

		}
		return $count;
	}

	/**
	 * [brief] Display title
	 */
	protected $displayTitle = NULL;

	/**
	 * [brief] Display content
	 */
	protected $displayContent = NULL;

	/**
	 * [brief] Record page
	 */
	protected $recordPage = NULL;

	/**
	 * [brief] Custom Display Fields
	 */
	protected $customDisplayFields = array();

	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		$fieldsClass  = 'IPS\cms\Fields' . static::$customDatabaseId;
		$database     = \IPS\cms\Databases::load( static::$customDatabaseId );

		/* Store a revision */
		if ( $database->revisions AND ! $this->_new )
		{
			$revision = new \IPS\cms\Records\Revisions;
			$revision->database_id = static::$customDatabaseId;
			$revision->record_id   = $this->_id;
			$revision->data        = $this->fieldValues( TRUE );

			$revision->save();
		}

		if ( isset( \IPS\Request::i()->postKey ) )
		{
			$this->post_key = \IPS\Request::i()->postKey;
		}

		if ( $this->_new )
		{
			$this->record_approved = ( static::moderateNewItems( \IPS\Member::loggedIn() ) ) ? 0 : 1;
		}

		/* Moderator actions */
		if ( isset( $values['create_record_state'] ) )
		{
			if ( in_array( 'lock', $values['create_record_state'] ) )
			{
				$this->record_locked = 1;
			}
			else
			{
				$this->record_locked = 0;
			}
	
			if ( in_array( 'hide', $values['create_record_state'] ) )
			{
				$this->record_approved = -1;
			}
			else
			{
				$this->record_approved = 1;
			}
	
			if ( in_array( 'pin', $values['create_record_state'] ) )
			{
				$this->record_pinned = 1;
			}
			else
			{
				$this->record_pinned = 0;
			}
	
			if ( in_array( 'feature', $values['create_record_state'] ) )
			{
				$this->record_featured = 1;
			}
			else
			{
				$this->record_featured = 0;
			}
		}
	
		/* Dates */
		if ( isset( $values['record_expiry_date'] ) and $values['record_expiry_date'] )
		{
			if ( $values['record_expiry_date'] === -1 )
			{
				$this->record_expiry_date = 0;
			}
			else
			{
				$this->record_expiry_date = $values['record_expiry_date']->getTimestamp();
			}
		}
		if ( isset( $values['record_comment_cutoff'] ) and $values['record_comment_cutoff'] )
		{
			if ( $values['record_comment_cutoff'] === -1 )
			{
				$this->record_comment_cutoff = 0;
			}
			else
			{
				$this->record_comment_cutoff = $values['record_comment_cutoff']->getTimestamp();
			}
		}

		/* Edit stuff */
		if ( ! $this->_new )
		{
			if ( isset( $values['record_edit_reason'] ) )
			{
				$this->record_edit_reason = $values['record_edit_reason'];
			}

			$this->record_edit_time        = time();
			$this->record_edit_member_id   = \IPS\Member::loggedIn()->member_id;
			$this->record_edit_member_name = \IPS\Member::loggedIn()->name;

			if ( isset( $values['record_edit_show'] ) )
			{
				$this->record_edit_show = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['record_edit_show'] : TRUE;
			}
		}

		/* Record image */
		if ( array_key_exists( 'record_image', $values ) )
		{			
			if ( $values['record_image'] === NULL )
			{			
				if ( $this->record_image )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $this->record_image )->delete();
					}
					catch ( \Exception $e ) { }
				}
				if ( $this->record_image_thumb )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $this->record_image_thumb )->delete();
					}
					catch ( \Exception $e ) { }
				}
					
				$this->record_image = NULL;
				$this->record_image_thumb = NULL;
			}
			else
			{
				$fixedFieldSettings = static::database()->fixed_field_settings;

				if ( isset( $fixedFieldSettings['record_image']['thumb_dims'] ) )
				{
					if ( $this->record_image_thumb )
					{
						try
						{
							\IPS\File::get( 'cms_Records', $this->record_image_thumb )->delete();
						}
						catch ( \Exception $e ) { }
					}
					
					$thumb = $values['record_image']->thumbnail( 'cms_Records', $fixedFieldSettings['record_image']['thumb_dims'][0], $fixedFieldSettings['record_image']['thumb_dims'][1] );
				}
				else
				{
					$thumb = $values['record_image'];
				}

				$this->record_image       = (string)$values['record_image'];
				$this->record_image_thumb = (string)$thumb;
			}
		}
		
		/* Should we just lock this? */
		if ( ( isset( $values['record_allow_comments'] ) AND ! $values['record_allow_comments'] ) OR ( $this->record_comment_cutoff > $this->record_publish_date ) )
		{
			$this->record_locked = 1;
		}
		
		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			foreach( array( 'record_meta_keywords', 'record_meta_description' ) as $k )
			{
				if ( isset( $values[ $k ] ) )
				{
					$this->$k = $values[ $k ];
				}
			}
		}

		/* Custom fields */
		$customValues = array();
		$afterEditNotificationsExclude = array();
	
		foreach( $values as $k => $v )
		{
			if ( mb_substr( $k, 0, 14 ) === 'content_field_' )
			{
				$customValues[$k ] = $v;
			}
		}

		$categoryClass = 'IPS\cms\Categories' . static::$customDatabaseId;
		$container    = ( ! isset( $values['content_record_form_container'] ) ? $categoryClass::load( $this->category_id ) : $values['content_record_form_container'] );
		$fieldObjects = $fieldsClass::data( NULL, $container );
		
		if ( static::$customFields === NULL )
		{
			static::$customFields = $fieldsClass::fields( $customValues, ( $this->_new ? 'add' : 'edit' ), $container, 0, ( $this->_new ? NULL : $this ) );
		}
		
		foreach( static::$customFields as $key => $field )
		{
			$seen[] = $key;
			$key = 'field_' . $key;
			
			if ( !$this->_new )
			{
				$afterEditNotificationsExclude = array_merge_recursive( static::_getQuoteAndMentionIdsFromContent( $this->$key ) );
			}
			
			if ( isset( $customValues[ $field->name ] ) and get_class( $field ) == 'IPS\Helpers\Form\Upload' )
			{
				if ( is_array( $customValues[ $field->name ] ) )
				{
					$items = array();
					foreach( $customValues[ $field->name ] as $obj )
					{
						$items[] = (string) $obj;
					}
					$this->$key = implode( ',', $items );
				}
				else
				{
					$this->$key = (string) $customValues[ $field->name ];
				}
			}
			/* If we're using decimals, then the database field is set to DECIMALS, so we cannot using stringValue() */
			else if ( isset( $customValues[ $field->name ] ) and get_class( $field ) == 'IPS\Helpers\Form\Number' and ( isset( $field->options['decimals'] ) and $field->options['decimals'] > 0 ) )
			{
				$this->$key = $field->value;
			}
			else
			{
				$this->$key = $field::stringValue( isset( $customValues[ $field->name ] ) ? $customValues[ $field->name ] : NULL );
			}
		}

		/* Now set up defaults */
		if ( $this->_new )
		{
			foreach ( $fieldObjects as $obj )
			{
				if ( !in_array( $obj->id, $seen ) )
				{
					/* We've not got a value for this as the field is hidden from us, so let us add the default value here */
					$key        = 'field_' . $obj->id;
					$this->$key = $obj->default_value;
				}
			}
		}

		/* Other data */
		if ( $this->_new OR $database->_comment_bump & \IPS\cms\Databases::BUMP_ON_EDIT )
		{
			$this->record_saved   = time();
			$this->record_updated = time();
		}

		$this->record_allow_comments   = isset( $values['record_allow_comments'] ) ? $values['record_allow_comments'] : ( ! $this->record_locked );
		
		if ( isset( $values[ 'content_field_' . $database->field_title ] ) )
		{
			$this->record_dynamic_furl     = \IPS\Http\Url::seoTitle( $values[ 'content_field_' . $database->field_title ] );
		}

		if ( isset( $values['record_static_furl_set'] ) and $values['record_static_furl_set'] and isset( $values['record_static_furl'] ) and $values['record_static_furl'] )
		{
			$newFurl = \IPS\Http\Url::seoTitle( $values['record_static_furl'] );

			if ( $newFurl != $this->record_static_furl )
			{
				$this->storeUrl();
			}
			
			$this->record_static_furl = $newFurl;
		}
		else
		{
			$this->record_static_furl = NULL;
		}
		
		if ( $this->_new )
		{
			/* Set the author ID on 'new' only */
			$this->member_id = (int) \IPS\Member::loggedIn()->member_id;
		}
		else
		{
			$this->sendQuoteAndMentionNotifications( array_unique( array_merge( $afterEditNotificationsExclude['quotes'], $afterEditNotificationsExclude['mentions'] ) ) );
		}
		
		if ( isset( $values['content_record_form_container'] ) )
		{
			$this->category_id = ( $values['content_record_form_container'] === 0 ) ? 0 : $values['content_record_form_container']->id;
		}

		$isNew    = $this->_new;
		$idColumn = static::$databaseColumnId;
		if ( ! $this->$idColumn )
		{
			$this->save();
		}

		/* Claim attachments once we have an ID */
		foreach( $fieldObjects as $id => $row )
		{
			if ( $row->can( ( $isNew ? 'add' : 'edit' ) ) and $row->type == 'Editor' )
			{
				\IPS\File::claimAttachments( 'RecordField_' . ( $isNew ? 'new' : $this->_id ) . '_' . $row->id, $this->primary_id_field, $id, static::$customDatabaseId );
			}
		}

		parent::processForm( $values );
	}

	/**
	 * Stores the URL so when its changed, the old can 301 to the new location
	 *
	 * @return void
	 */
	public function storeUrl()
	{
		if ( $this->record_static_furl )
		{
			\IPS\Db::i()->insert( 'cms_url_store', array(
				'store_path'       => $this->record_static_furl,
			    'store_current_id' => $this->_id,
			    'store_type'       => 'record'
			) );
		}
	}

	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Determines whether the first comment should be inlcluded in the comment count (e.g. For "posts", use TRUE. For "replies", use FALSE)
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		$return = array();

		if ( static::$commentClass and static::database()->options['comments'] )
		{
			$return['comments'] = (int) $this->mapped('num_comments');
		}

		if ( $this instanceof \IPS\Content\Views )
		{
			$return['num_views'] = (int) $this->mapped('views');
		}

		return $return;
	}

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		if ( ! $this->recordPage )
		{
			/* If we're coming through the database controller embedded in a page, $currentPage will be set. If we're coming in via elsewhere, we need to fetch the page */
			try
			{
				$this->recordPage = \IPS\cms\Pages\Page::loadByDatabaseId( static::$customDatabaseId );
			}
			catch( \OutOfRangeException $ex )
			{
				if ( \IPS\cms\Pages\Page::$currentPage )
				{
					$this->recordPage = \IPS\cms\Pages\Page::$currentPage;
				}
				else
				{
					throw new \LogicException;
				}
			}
		}

		if ( $this->recordPage )
		{
			$pagePath   = $this->recordPage->full_path;
			$class		= '\IPS\cms\Categories' . static::$customDatabaseId;
			$catPath    = $class::load( $this->category_id )->full_path;
			$recordSlug = ! $this->record_static_furl ? $this->record_dynamic_furl . '-r' . $this->primary_id_field : $this->record_static_furl;

			if ( static::database()->use_categories )
			{
				$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath . '/' . $catPath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
			}
			else
			{
				$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
			}
		}

		if ( $action )
		{
			$url = $url->setQueryString( 'do', $action );
			$url = $url->setQueryString( 'd' , static::database()->id );
			$url = $url->setQueryString( 'id', $this->primary_id_field );
		}

		return $url;
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'category_id';
		$return[] = 'record_static_furl';
		$return[] = 'record_dynamic_furl';
		return $return;
	}
	
	/**
	 * Query to get additional data for search result / stream view
	 *
	 * @param	array	$items	Item data (will be an array containing values from basicDataColumns())
	 * @return	array
	 */
	public static function searchResultExtraData( $items )
	{
		$categoryIds = array();
		
		foreach ( $items as $item )
		{
			if ( $item['category_id'] )
			{
				$categoryIds[ $item['category_id'] ] = $item['category_id'];
			}
		}
		
		if ( count( $categoryIds ) )
		{
			$categoryPaths = iterator_to_array( \IPS\Db::i()->select( array( 'category_id', 'category_full_path' ), 'cms_database_categories', \IPS\Db::i()->in( 'category_id', $categoryIds ) )->setKeyField('category_id')->setValueField('category_full_path') );
			
			$return = array();
			foreach ( $items as $item )
			{
				if ( $item['category_id'] )
				{
					$return[ $item['primary_id_field'] ] = $categoryPaths[ $item['category_id'] ];
				}
			}
			return $return;
		}
		
		return array();
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData )
	{
		if ( static::$pagePath === NULL )
		{
			static::$pagePath = \IPS\Db::i()->select( array( 'page_full_path' ), 'cms_pages', array( 'page_id=?', static::database()->page_id ) )->first();
		}
		
		$recordSlug = !$itemData['record_static_furl'] ? $itemData['record_dynamic_furl']  . '-r' . $itemData['primary_id_field'] : $itemData['record_static_furl'];
		
		if ( static::database()->use_categories )
		{
			return \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . static::$pagePath . '/' . $itemData['extra'] . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
		}
		else
		{
			return \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . static::$pagePath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
		}
	}

	/**
	 * Template helper method to fetch custom fields to display
	 *
	 * @param   string  $type       Type of display
	 * @return  array
	 */
	public function customFieldsForDisplay( $type='display' )
	{
		if ( ! isset( $this->customDisplayFields['all'][ $type ] ) )
		{
			$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;
			$this->customDisplayFields['all'][ $type ] = $fieldsClass::display( $this->fieldValues(), $type, $this->container(), 'key', $this );
		}

		return $this->customDisplayFields['all'][ $type ];
	}

	/**
	 * @param mixed      $key       Key to fetch
	 * @param string     $type      Type of display to fetch
	 *
	 * @return mixed
	 */
	public function customFieldDisplayByKey( $key, $type='display' )
	{
		$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;

		if ( ! isset( $this->customDisplayFields[ $key ][ $type ] ) )
		{
			foreach ( $fieldsClass::roots( 'view' ) as $row )
			{
				if ( $row->key === $key )
				{
					$field = 'field_' . $row->id;
					$value = ( $this->$field !== '' AND $this->$field !== NULL ) ? $this->$field : $row->default_value;

					$this->customDisplayFields[ $key ][ $type ] = $row->formatForDisplay( $row->displayValue( $value ), $value, $type, $this );
				}
			}
		}

		/* Still nothing? */
		if ( ! isset( $this->customDisplayFields[ $key ][ $type ] ) )
		{
			$this->customDisplayFields[ $key ][ $type ] = NULL;
		}

		return $this->customDisplayFields[ $key ][ $type ];
	}

	/**
	 * Get custom field_x keys and values
	 *
	 * @param	boolean	$allData	All data (true) or just custom field data (false)
	 * @return	array
	 */
	public function fieldValues( $allData=FALSE )
	{
		$fields = array();
		
		foreach( $this->_data as $k => $v )
		{
			if ( $allData === TRUE OR mb_substr( $k, 0, 6 ) === 'field_')
			{
				$fields[ $k ] = $v;
			}
		}

		return $fields;
	}

	/**
	 * Get the post key or create one if one doesn't exist
	 *
	 * @return  string
	 */
	public function get__post_key()
	{
		return ! empty( $this->post_key ) ? $this->post_key : md5( uniqid() );
	}

	/**
	 * Get the publish date
	 *
	 * @return	string
	 */
	public function get__publishDate()
	{
		return $this->record_publish_date ? $this->record_publish_date : $this->record_saved;
	}

	/**
	 * Get the record id
	 *
	 * @return	int
	 */
	public function get__id()
	{
		return $this->primary_id_field;
	}
	
	/**
	 * Set value in data store
	 *
	 * @see		\IPS\Patterns\ActiveRecord::save
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		parent::__set( $key, $value );
		
		if ( $key == 'field_' . static::database()->field_title )
		{
			$this->displayTitle = NULL;
		}
		if ( $key == 'field_' . static::database()->field_content )
		{
			$this->displayContent = NULL;
		}
	}

	/**
	 * Get the record title for display
	 *
	 * @return	string
	 */
	public function get__title()
	{
		$field = 'field_' . static::database()->field_title;

		try
		{
			if ( ! $this->displayTitle )
			{
				$class = '\IPS\cms\Fields' .  static::database()->id;
				$this->displayTitle = $class::load( static::database()->field_title )->displayValue( $this->$field );
			}

			return $this->displayTitle;
		}
		catch( \Exception $e )
		{
			return $this->$field;
		}
	}
	
	/**
	 * Get the record content for display
	 *
	 * @return	string
	 */
	public function get__content()
	{
		$field = 'field_' . static::database()->field_content;

		try
		{
			if ( ! $this->displayContent )
			{
				$class = '\IPS\cms\Fields' .  static::database()->id;

				$this->displayContent = $class::load( static::database()->field_content )->displayValue( $this->$field );
			}

			return $this->displayContent;
		}
		catch( \Exception $e )
		{
			return $this->$field;
		}
	}
	
	/**
	 * Return forum sync on or off
	 *
	 * @return	int
	 */
	public function get__forum_record()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_record;
		}
		
		return static::database()->forum_record;
	}
	
	/**
	 * Return forum post on or off
	 *
	 * @return	int
	 */
	public function get__forum_comments()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_comments;
		}
		
		return static::database()->forum_comments;
	}
	
	/**
	 * Return forum sync delete
	 *
	 * @return	int
	 */
	public function get__forum_delete()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_delete;
		}
		
		return static::database()->forum_delete;
	}
	
	/**
	 * Return forum sync forum
	 *
	 * @return	int
	 */
	public function get__forum_forum()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_forum;
		}
		
		return static::database()->forum_forum;
	}
	
	/**
	 * Return forum sync prefix
	 *
	 * @return	int
	 */
	public function get__forum_prefix()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_prefix;
		}
	
		return static::database()->forum_prefix;
	}
	
	/**
	 * Return forum sync suffix
	 *
	 * @return	int
	 */
	public function get__forum_suffix()
	{
		if ( $this->container()->forum_override )
		{
			return $this->container()->forum_suffix;
		}
	
		return static::database()->forum_suffix;
	}

	/**
	 * Return record image thumb
	 *
	 * @return	int
	 */
	public function get__record_image_thumb()
	{
		return $this->record_image_thumb ? $this->record_image_thumb : $this->record_image;
	}

	/**
	 * Get edit line
	 *
	 * @return	string|NULL
	 */
	public function editLine()
	{
		if ( $this->record_edit_time and ( $this->record_edit_show or \IPS\Member::loggedIn()->modPermission('can_view_editlog') ) and \IPS\Settings::i()->edit_log )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->recordEditLine( $this );
		}
		return NULL;
	}

	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( $key === 'title' )
		{
			return $this->_title;
		}
		else if ( $key === 'content' )
		{
			return $this->_content;
		}
		
		if ( isset( static::$databaseColumnMap[ $key ] ) )
		{
			$field = static::$databaseColumnMap[ $key ];
				
			if ( is_array( $field ) )
			{
				$field = array_pop( $field );
			}
				
			return $this->$field;
		}
		return NULL;
	}
	
	/**
	 * Save
	 *
	 * @return void
	 */
	public function save()
	{
		$new = $this->_new;
			
		if ( $new OR static::database()->_comment_bump & \IPS\cms\Databases::BUMP_ON_EDIT )
		{
			$member = \IPS\Member::load( $this->member_id );
	
			/* Set last comment as record so that category listing is correct */
			if ( $this->record_saved > $this->record_last_comment )
			{
				$this->record_last_comment = $this->record_saved;
			}

			if ( $new )
			{
				$this->record_last_comment_by   = $this->member_id;
				$this->record_last_comment_name = $member->name;
				$this->record_last_comment_seo  = \IPS\Http\Url::seoTitle( $member->name );
			}
		}
	
		parent::save();

        if ( $this->category_id )
        {
            unset( static::$multitons[ $this->primary_id_field ] );

            $class = '\IPS\cms\Categories' . static::$customDatabaseId;
            $category = $class::load( $this->category_id );
            $category->setLastComment();
            $category->save();
        }
	}
	
	/**
	 * Resync last comment
	 *
	 * @return	void
	 */
	public function resyncLastComment()
	{
		if ( $this->useForumComments() )
		{
			$topic = $this->topic( FALSE );
			$topic->resyncLastComment();
		}
		
		parent::resyncLastComment();
	}
	
	/**
	 * Utility method to reset the last commenter of a record
	 *
	 * @param   boolean     $setCategory    Check and set the last commenter for a category
	 * @return void
	 */
	public function resetLastComment( $setCategory=false )
	{
		$comment = $this->comments( 1, 0, 'date', 'desc', NULL, FALSE );

		if ( $comment )
		{
			$this->record_last_comment      = $comment->mapped('date');
			$this->record_last_comment_by   = $comment->author()->member_id;
			$this->record_last_comment_name = $comment->author()->name;
			$this->record_last_comment_seo  = \IPS\Http\Url::seoTitle( $comment->author()->name );
			$this->save();

			if ( $setCategory and $this->category_id )
			{
				$class = '\IPS\cms\Categories' . static::$customDatabaseId;
				$class::load( $this->category_id )->setLastComment( NULL );
				$class::load( $this->category_id )->save();
			}
		}
	}

	/**
	 * Resync the comments/unapproved comment counts
	 *
	 * @param	string	$commentClass	Override comment class to use
	 * @return void
	 */
	public function resyncCommentCounts( $commentClass=NULL )
	{
		if ( $this->useForumComments() )
		{
			$topic = $this->topic( FALSE );

			if ( $topic )
			{
				$this->record_comments = $topic->posts - 1;
				$this->record_comments_queued = $topic->topic_queuedposts;
				$this->record_comments_hidden = $topic->topic_hiddenposts;
				$this->save();
			}
		}
		else
		{
			parent::resyncCommentCounts( $commentClass );
		}
	}
	
	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member\NULL	$member	The member to check for or NULL fto not check permission
	 * @return	int
	 */
	public static function supportsComments( \IPS\Member $member = NULL )
	{
		return parent::supportsComments() and static::database()->options['comments'];
	}
	
	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member\NULL	$member	The member to check for or NULL to not check permission
	 * @return	int
	 */
	public static function supportsReviews( \IPS\Member $member = NULL )
	{
		return parent::supportsReviews() and static::database()->options['reviews'];
	}

	/* !IP.Board Integration */
	
	/**
	 * Use forum for comments
	 *
	 * @return boolean
	 */
	public function useForumComments()
	{
		return $this->_forum_record and $this->_forum_comments and $this->record_topicid and \IPS\Application::appIsEnabled('forums');
	}
	
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL )
	{
		parent::modAction( $action, $member, $reason );
		
		if ( $this->useForumComments() and ( $action === 'lock' or $action === 'unlock' ) )
		{
			if ( $topic = $this->topic() )
			{
				$topic->state = ( $action === 'lock' ? 'closed' : 'open' );
				$topic->save();	
			}
		}
	}

	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool|NULL			$bypassCache			Used in cases where comments may have already been loaded i.e. splitting comments on an item.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection='asc', $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL, $bypassCache=FALSE )
	{
		if ( $this->useForumComments() )
		{
			$recordClass = 'IPS\cms\Records\RecordsTopicSync' . static::$customDatabaseId;

			/* If we are pulling in ASC order we want to jump up by 1 to account for the first post, which is not a comment */
			if( mb_strtolower( $orderDirection ) == 'asc' )
			{
				$_pageValue = ( \IPS\Request::i()->page ? intval( \IPS\Request::i()->page ) : 1 );

				if( $_pageValue < 1 )
				{
					$_pageValue = 1;
				}
				
				$offset = ( ( $_pageValue - 1 ) * static::getCommentsPerPage() ) + 1;
			}
			
			return $recordClass::load( $this->record_topicid )->comments( $limit, $offset, $order, $orderDirection, $member, $includeHiddenComments, $cutoff );
		}
		else
		{
			/* Because this is a static property, it may have been overridden by a block on the same page. */
			if ( get_called_class() != 'IPS\cms\Records\RecordsTopicSync' . static::$customDatabaseId )
			{
				static::$commentClass = 'IPS\cms\Records\Comment' . static::$customDatabaseId;
			}
		}

		$where = NULL;
		if( static::$commentClass != 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId )
		{
			$where = array( array( 'comment_database_id=?', static::$customDatabaseId ) );
		}
		
		return parent::comments( $limit, $offset, $order, $orderDirection, $member, $includeHiddenComments, $cutoff, $where );
	}

	/**
	 * Get review page count
	 *
	 * @return	int
	 */
	public function reviewPageCount()
	{
		if ( $this->reviewPageCount === NULL )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			$where[] = array( 'review_database_id=?', static::$customDatabaseId );
			$count = $reviewClass::getItemsWithPermission( $where, NULL, NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );
			$this->reviewPageCount = ceil( $count / static::$reviewsPerPage );

			if( $this->reviewPageCount < 1 )
			{
				$this->reviewPageCount	= 1;
			}
		}
		return $this->reviewPageCount;
	}

	/**
	 * Get reviews
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by (NULL to examine \IPS\Request::i()->sort)
	 * @param	string				$orderDirection			"asc" or "desc" (NULL to examine \IPS\Request::i()->sort)
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenReviews	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function reviews( $limit=NULL, $offset=NULL, $order=NULL, $orderDirection='desc', $member=NULL, $includeHiddenReviews=NULL, $cutoff=NULL, $extraWhereClause=NULL )
	{
		$where = array( array( 'review_database_id=?', static::$customDatabaseId ) );

		return parent::reviews( $limit, $offset, $order, $orderDirection, $member, $includeHiddenReviews, $cutoff, $where );
	}

	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();
		if ( static::database()->options['reviews'] )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get( 'cms_review_count' ), array( $this->mapped('num_reviews') ) );
		}
		if ( static::database()->options['comments'] )
		{
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get( 'cms_comment_count' ), array( $this->mapped( 'num_comments' ) ) );
		}

		return $tabs;
	}

	/**
	 * Get comment/review output
	 *
	 * @param	string	$tab	Active tab
	 * @return	string
	 */
	public function commentReviews( $tab )
	{
		if ( $tab === 'reviews' )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->reviews( $this );
		}
		elseif( $tab === 'comments' )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->comments( $this );
		}

		return '';
	}

	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL )
	{
		return ( static::database()->record_approve and !$member->group['g_avoid_q'] ) or parent::moderateNewItems( $member, $container );
	}

	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member	The member posting
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member )
	{
		return ( static::database()->options['comments_mod'] and !$member->group['g_avoid_q'] ) or parent::moderateNewComments( $member );
	}

	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member	The member posting
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member )
	{
		return ( static::database()->options['reviews_mod'] and !$member->group['g_avoid_q'] ) or parent::moderateNewReviews( $member );

	}

	/**
	 * Create from form
	 *
	 * @param	array					$values				Values from form
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	Send Notification
	 * @return	\IPS\cms\Records
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		$record = parent::createFromForm( $values, $container, $sendNotification );

		if ( \IPS\Application::appIsEnabled('forums') and $record->_forum_record and $record->_forum_forum and ! $record->hidden() and ! $record->record_future_date )
		{
			try
			{
				$record->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		return $record;
	}

	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		if ( \IPS\Application::appIsEnabled('forums') and $this->_forum_record and $this->_forum_forum and ! $this->hidden() and ! $this->record_future_date )
		{
			try
			{
				$this->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		parent::processAfterEdit( $values );
	}
	
	/**
	 * Process the comment form
	 *
	 * @param	array	$values		Array of $form values
	 * @return  \IPS\Content\Comment
	 */
	public function processCommentForm( $values )
	{
		if ( $this->useForumComments() )
		{
			$topic = $this->topic( FALSE );
		
			if ( $topic === NULL )
			{
				$this->syncTopic();
				
				/* Try again */
				$topic = $this->topic( FALSE );
				if ( ! $topic )
				{
					return parent::processCommentForm( $values );
				}
			}
			
			$comment = $values[ static::$formLangPrefix . 'comment' . '_' . $this->_id ];
			$post    = \IPS\forums\Topic\Post::create( $topic, $comment, FALSE, ( isset( $values['guest_name'] ) ? $values['guest_name'] : NULL ) );
			
			$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;
			
			return $commentClass::load( $post->pid );
			
		}
		else
		{
			return parent::processCommentForm( $values );
		}
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		parent::onHide( $member );
		if ( \IPS\Application::appIsEnabled('forums') and $topic = $this->topic() )
		{
			$topic->hide( $member );
		}
	}
	
	/**
	 * Syncing to run when publishing something previously pending publishing
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onPublish( $member )
	{
		parent::onPublish( $member );
		if ( \IPS\Application::appIsEnabled('forums') )
		{
			if ( $topic = $this->topic() )
			{
				if ( $topic->hidden() )
				{
					$topic->unhide( $member );
				}
			}
			else if ( $this->_forum_forum and $this->_forum_comments )
			{
				$this->syncTopic();
			}
		}
	}
	
	/**
	 * Syncing to run when unpublishing an item (making it a future dated entry when it was already published)
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnpublish( $member )
	{
		parent::onUnpublish( $member );
		if ( \IPS\Application::appIsEnabled('forums') AND $topic = $this->topic() )
		{
			$topic->hide( $member );
		}
	}
	
	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool					$approving	If true, is being approved for the first time
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnhide( $approving, $member )
	{
		parent::onUnhide( $approving, $member );
		
		if ( $this->record_expiry_date )
		{
			$this->record_expiry_date = 0;
			$this->save();
		}
		
		if ( \IPS\Application::appIsEnabled('forums') )
		{
			if ( $topic = $this->topic() )
			{ 
				$topic->unhide( $member );
			}
			elseif ( $this->_forum_forum and $this->_forum_comments )
			{
				$this->syncTopic();
			}
		}
	}

	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor )
	{
		parent::changeAuthor( $newAuthor );

		$topic = $this->topic();

		if ( $topic )
		{
			$topic->changeAuthor( $newAuthor );
		}
	}

	/**
	 * Get Topic (checks member's permissions)
	 *
	 * @param	bool	$checkPerms		Should check if the member can read the topic?
	 * @return	\IPS\forums\Topic|NULL
	 */
	public function topic( $checkPerms=TRUE )
	{
		if ( \IPS\Application::appIsEnabled('forums') and $this->record_topicid )
		{
			try
			{
				return $checkPerms ? \IPS\forums\Topic::loadAndCheckPerms( $this->record_topicid ) : \IPS\forums\Topic::load( $this->record_topicid );
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}
	
		return NULL;
	}
	
	/**
	 * Post this record as a forum topic
	 * 
	 * @return void
	 */
	public function syncTopic()
	{
		if ( ! \IPS\Application::appIsEnabled( 'forums' ) )
		{
			throw new \UnexpectedValueException('content_record_no_forum_app_for_topic');
		}

		/* Fetch the forum */
		try
		{
			$forum = \IPS\forums\Forum::load( $this->_forum_forum );
		}
		catch( \OutOfRangeException $ex )
		{
			throw new \UnexpectedValueException('content_record_bad_forum_for_topic');
		}

		/* Existing topic */
		if ( $this->record_topicid )
		{
			/* Get */
			try
			{
				$topic = \IPS\forums\Topic::load( $this->record_topicid );
				if ( !$topic )
				{
					return;
				}
				/* Reset cache */
				$this->displayTitle = NULL;
				$topic->title = $this->_forum_prefix . $this->_title . $this->_forum_suffix;
				if ( \IPS\Settings::i()->tags_enabled )
				{
					$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
				}
				
				if ( $this->hidden() )
				{
					$topic->hide( FALSE );
				}
				else if ( $topic->hidden() )
				{
					$topic->unhide( FALSE );
				}

				$topic->save();
				$firstPost = $topic->comments( 1 );

				$content = \IPS\Theme::i()->getTemplate( 'submit', 'cms', 'front' )->topic( $this );
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

				$firstPost->post = $content;
				$firstPost->save();
			}
			catch ( \OutOfRangeException $e )
			{
				return;
			}
		}
		/* New topic */
		else
		{
			/* Create topic */
			$topic = \IPS\forums\Topic::createItem( $this->author(), \IPS\Request::i()->ipAddress(), \IPS\DateTime::ts( $this->record_publish_date ? $this->record_publish_date : $this->record_saved ), \IPS\forums\Forum::load( $this->_forum_forum ), $this->hidden() );
			$topic->title = $this->_forum_prefix . $this->_title . $this->_forum_suffix;
			$topic->topic_archive_status = \IPS\forums\Topic::ARCHIVE_EXCLUDE;
			$topic->save();

			if ( \IPS\Settings::i()->tags_enabled )
			{
				$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
			}

			/* Create post */
			$content = \IPS\Theme::i()->getTemplate( 'submit', 'cms', 'front' )->topic( $this );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

			$post = \IPS\forums\Topic\Post::create( $topic, $content, TRUE, NULL, NULL, $this->author(), \IPS\DateTime::ts( $this->record_publish_date ? $this->record_publish_date : $this->record_saved ) );
			$post->save();

			$topic->topic_firstpost = $post->pid;
			$topic->save();

			$topic->markRead();

			/* Update file */
			$this->record_topicid = $topic->tid;
			$this->save();
		}
	}

	/**
	 * Sync topic details to the record
	 *
	 * @param   \IPS\forums\Topic   $topic  Forums topic
	 * @return  void
	 */
	public function syncRecordFromTopic( $topic )
	{
		if ( $this->_forum_record and $this->_forum_forum and $this->_forum_comments )
		{
			$this->record_last_comment_by   = $topic->last_poster_id;
			$this->record_last_comment_name = $topic->last_poster_name;
			$this->record_last_comment      = $topic->last_post;
			$this->record_comments_queued   = $topic->topic_queuedposts;
			$this->record_comments_hidden 	= $topic->topic_hiddenposts;
			$this->record_comments          = $topic->posts - 1;
			$this->save();
		}
	}

	/**
	 * Get fields for the topic
	 * 
	 * @return array
	 */
	public function topicFields()
	{
		$fieldsClass = 'IPS\cms\Fields' . static::$customDatabaseId;
		$fieldData   = $fieldsClass::data( 'view', $this->container() );
		$fieldValues = $fieldsClass::display( $this->fieldValues(), 'record', $this->container(), 'id' );

		$fields = array();
		foreach( $fieldData as $id => $data )
		{
			if ( $data->topic_format )
			{
				if ( isset( $fieldValues[ $data->id ] ) )
				{
					$html = str_replace( '{title}'  , $data->_title, $data->topic_format );
					$html = str_replace( '{content}', $fieldValues[ $data->id ], $html );
					$html = str_replace( '{value}'  , $fieldValues[ $data->id ], $html );
				
					$fields[ $data->id ] = $html;
				}
			}
		}

		if ( ! count( $fields ) )
		{
			$fields[ static::database()->field_content ] = $fieldValues['content'];
		}

		return $fields;
	}

	/**
	 * Get comment page count
	 *
	 * @return	int
	 */
	public function commentPageCount( $recache=FALSE )
	{
		if ( $this->useForumComments() )
		{
			try
			{
				$topic = $this->topic();

				if( $topic !== NULL )
				{
					$topic->posts = $topic->posts - 1;

					return $topic->commentPageCount();
				}

				return 0;
			}
			catch( \Exception $e ) { }
		}

		return parent::commentPageCount();
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		$topic        = $this->topic();
		$commentClass = static::$commentClass;
		
		if ( $this->topic() and $this->_forum_delete )
		{
			$topic->delete();
		}
		else if ( $this->topic() )
		{
			/* We have an attached topic, but we don't want to delete the topic so remove commentClass otherwise we'll delete posts */
			static::$commentClass = NULL;
		}
		
		parent::delete();
		
		if ( $this->topic() )
		{
			static::$commentClass = $commentClass;
		}
	}

	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		try
		{
			\IPS\cms\Pages\Page::loadByDatabaseId( static::database()->id );
		}
		catch( \OutOfRangeException $e )
		{
			/* This prevents auto share and notifications being sent out */
			return FALSE;
		}

		return parent::canView( $member );
	}

	/* ! Moderation */
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		if ( ( ( static::database()->options['indefinite_own_edit'] AND \IPS\Member::loggedIn()->member_id === $this->member_id ) OR ( \IPS\Member::loggedIn()->member_id and static::database()->all_editable ) ) AND ! $this->locked() AND ! $this->hidden() )
		{
			return TRUE;
		}

		if ( parent::canEdit( $member ) )
		{
			/* Test against specific perms for this category */
			return $this->container()->can( 'edit', $member );
		}

		return FALSE;
	}

	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		if ( ! static::database()->use_categories )
		{
			return FALSE;
		}
		
		return parent::canMove( $member );
	}

	/**
	 * Can manage revisions?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canManageRevisions( \IPS\Member $member = NULL )
	{
		return static::database()->revisions and static::modPermission( 'content_revisions', $member );
	}

	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canComment( $member=NULL )
	{
		return ( static::database()->options['comments'] and parent::canComment( $member ) );
	}

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canReview( $member=NULL )
	{
		return ( static::database()->options['reviews'] and parent::canReview( $member ) );
	}

	/**
	 * During canCreate() check, verify member can access the module too
	 *
	 * @param	\IPS\Member	$member		The member
	 * @note	The only reason this is abstracted at this time is because Pages creates dynamic 'modules' with its dynamic records class which do not exist
	 * @return	bool
	 */
	protected static function _canAccessModule( \IPS\Member $member )
	{
		/* Can we access the module */
		return $member->canAccessModule( \IPS\Application\Module::get( static::$application, 'database', 'front' ) );
	}

	/**
	 * Already reviewed?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function hasReviewed( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Check cache */
		if( isset( $this->_hasReviewed[ $member->member_id ] ) and $this->_hasReviewed[ $member->member_id ] !== NULL )
		{
			return $this->_hasReviewed[ $member->member_id ];
		}

		$reviewClass = static::$reviewClass;
		$idColumn    = static::$databaseColumnId;

		$this->_hasReviewed[ $member->member_id ] = \IPS\Db::i()->select(
			'COUNT(*)', $reviewClass::$databaseTable, array(
				array(
					$reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=?',
					$member->member_id
				),
				array(
					$reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?',
					$this->$idColumn
				),
				array( $reviewClass::$databasePrefix . 'database_id=?', static::$customDatabaseId )
			)
		)->first();

		return $this->_hasReviewed[ $member->member_id ];
	}

	/* ! Rating */
	
	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		return parent::canRate( $member ) and ( $this->container()->allow_rating );
	}
	
	/* ! Comments */
	/**
	 * Add the comment form elements
	 *
	 * @return	array
	 */
	public function commentFormElements()
	{
		return parent::commentFormElements();
	}

	/**
	 * Add a comment when the filtes changed. If they changed.
	 *
	 * @param   array   $values   Array of new form values
	 * @return  boolean|\IPS\cms\Records\Comment
	 */
	public function addCommentWhenFiltersChanged( $values )
	{
		if ( ! $this->canComment() )
		{
			return FALSE;
		}

		$currentValues = $this->fieldValues();
		$commentClass  = 'IPS\cms\Records\Comment' . static::$customDatabaseId;
		$categoryClass = 'IPS\cms\Categories' . static::$customDatabaseId;
		$fieldsClass   = 'IPS\cms\Fields' . static::$customDatabaseId;
		$newValues     = array();
		$fieldsFields  = $fieldsClass::fields( $values, 'edit', $this->category_id ?  $categoryClass::load( $this->category_id ) : NULL, $fieldsClass::FIELD_DISPLAY_COMMENTFORM );

		foreach( $currentValues as $name => $data )
		{
			$id = mb_substr( $name, 6 );
			if ( $id == static::database()->field_title or $id == static::database()->field_content )
			{
				unset( $currentValues[ $name ] );
			}

			/* Not filterable? */
			if ( ! isset( $fieldsFields[ $id ] ) )
			{
				unset( $currentValues[ $name ] );
			}
		}

		foreach( $fieldsFields as $key => $field )
		{
			$newValues[ 'field_' . $key ] = $field::stringValue( isset( $values[ $field->name ] ) ? $values[  $field->name ] : NULL );
		}

		$diff = array_diff_assoc( $currentValues, $newValues );

		if ( count( $diff ) )
		{
			$show    = array();
			$display = $fieldsClass::display( $newValues, NULL, NULL, 'id' );

			foreach( $diff as $name => $value )
			{
				$id = mb_substr( $name, 6 );

				if ( $display[ $id ] )
				{
					$show[ $name ] = sprintf( \IPS\Member::loggedIn()->language()->get( 'cms_record_field_changed' ), \IPS\Member::loggedIn()->language()->get( 'content_field_' . $id ), $display[ $id ] );
				}
			}

			if ( count( $show ) )
			{
				$post = \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->filtersAddComment( $show );
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $post );
				
				if ( $this->useForumComments() )
				{
					$topic = $this->topic();
					$post  = \IPS\forums\Topic\Post::create( $topic, $post, FALSE );
					
					$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;
					
					$comment = $commentClass::load( $post->pid );
					$this->resyncLastComment();

					return $comment;
				}
				else
				{
					return $commentClass::create( $this, $post, FALSE );
				}
			}
		}

		return TRUE;
	}

	/* ! Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canTag( $member, $container ) and ( static::database()->tags_enabled );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canPrefix( $member, $container ) and ( ! static::database()->tags_noprefixes );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		if ( static::database()->tags_predefined )
		{
			return explode( ',', static::database()->tags_predefined );
		}
	
		return parent::definedTags( $container );
	}

	/**
	 * Use a custom table helper when building content item tables
	 *
	 * @param	\IPS\Helpers\Table	$table	Table object to modify
	 * @return	\IPS\Helpers\Table
	 */
	public function reputationTableCallback( $table, $currentClass )
	{
		return $table;
	}
	
	/* !Notifications */
	
	/**
	 * Send quote and mention notifications
	 *
	 * @param	array	$exclude		An array of member IDs *not* to send notifications to
	 * @return	array	Member IDs sent to
	 */
	protected function sendQuoteAndMentionNotifications( $exclude=array() )
	{
		$data = array( 'quotes' => array(), 'mentions' => array() );
		
		foreach ( call_user_func( array( 'IPS\cms\Fields' .  static::$customDatabaseId, 'data' ) ) as $field )
		{
			if ( $field->type == 'Editor' )
			{
				$key = "field_{$field->id}";
				
				$_data = static::_getQuoteAndMentionIdsFromContent( $this->$key );
				foreach ( $_data as $type => $memberIds )
				{
					$_data[ $type ] = array_filter( $memberIds, function( $memberId ) use ( $field )
					{
						return $field->can( 'view', \IPS\Member::load( $memberId ) );
					} );
				}
				
				$data = array_map( 'array_unique', array_merge_recursive( $data, $_data ) );
			}
		}
		
		return $this->_sendQuoteAndMentionNotifications( $data, $exclude );
	}

    /**
     * Get average review rating
     *
     * @return	int
     */
    public function averageReviewRating()
    {
        if( $this->_averageReviewRating !== NULL )
        {
            return $this->_averageReviewRating;
        }

        $reviewClass = static::$reviewClass;
        $idColumn = static::$databaseColumnId;

        $where = array();
        $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=? AND review_database_id=?', $this->$idColumn, static::$customDatabaseId );
        if ( in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
        {
            if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
            {
                $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
            }
            elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
            {
                $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 0 );
            }
        }

        $this->_averageReviewRating = round( \IPS\Db::i()->select( 'AVG(' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['rating'] . ')', $reviewClass::$databaseTable, $where )->first() );

        return $this->_averageReviewRating;
    }

	/**
	 * If, when making a post, we should merge with an existing comment, this method returns the comment to merge with
	 *
	 * @return	\IPS\Content\Comment|NULL
	 */
	public function mergeConcurrentComment()
	{
		$lastComment = parent::mergeConcurrentComment();

		/* If we sync to the forums, make sure that the "last comment" is not actually the first post */
		if( $this->record_topicid AND $lastComment !== NULL )
		{
			$firstComment = \IPS\forums\Topic::load( $this->record_topicid )->comments( 1, 0, 'date', 'asc' );

			if( $firstComment->pid == $lastComment->pid )
			{
				return NULL;
			}
		}

		return $lastComment;
	}

	/**
	 * Search Index Permissions
	 * If we don't have a page, we don't want to add this to the search index
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		try
		{
			return parent::searchIndexPermissions();
		}
		catch ( \LogicException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Get output for API
	 *
	 * @return	array
	 * @apiresponse	int						id				ID number
	 * @apiresponse	string					title			Title
	 * @apiresponse	\IPS\cms\Categories		category		Category
	 * @apiresponse	object					fields			Field values
	 * @apiresponse	\IPS\Member				author			The member that created the event
	 * @apiresponse	datetime				date			When the record was created
	 * @apiresponse	string					description		Event description
	 * @apiresponse	int						comments		Number of comments
	 * @apiresponse	int						reviews			Number of reviews
	 * @apiresponse	int						views			Number of posts
	 * @apiresponse	string					prefix			The prefix tag, if there is one
	 * @apiresponse	[string]				tags			The tags
	 * @apiresponse	bool					locked			Event is locked
	 * @apiresponse	bool					hidden			Event is hidden
	 * @apiresponse	bool					pinned			Event is pinned
	 * @apiresponse	bool					featured		Event is featured
	 * @apiresponse	string					url				URL
	 */
	public function apiOutput()
	{
		return array(
			'id'			=> $this->primary_id_field,
			'title'			=> $this->_title,
			'category'		=> $this->container() ? $this->container()->apiOutput() : null,
			'fields'		=> $this->fieldValues(),
			'author'		=> $this->author()->apiOutput(),
			'date'			=> \IPS\DateTime::ts( $this->record_saved )->rfc3339(),
			'description'	=> $this->content(),
			'comments'		=> $this->record_comments,
			'reviews'		=> $this->record_reviews,
			'views'			=> $this->record_views,
			'prefix'		=> $this->prefix(),
			'tags'			=> $this->tags(),
			'locked'		=> (bool) $this->locked(),
			'hidden'		=> (bool) $this->hidden(),
			'pinned'		=> (bool) $this->mapped('pinned'),
			'featured'		=> (bool) $this->mapped('featured'),
			'url'			=> (string) $this->url(),
		);
	}
}