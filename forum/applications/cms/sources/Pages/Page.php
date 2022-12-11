<?php
/**
 * @brief		Page Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		15 Jan 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\Pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Page Model
 */
class _Page extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_pages';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'page_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('page_seo_name', 'page_full_path');
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'folder_id';
	
	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'folder_id';
	
	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\cms\Pages\Folder';
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnOrder = 'seo_name';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;
	
	/**
	 * @brief	[Node] Title
	 */
	public static $nodeTitle = 'page';

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 array(
	 'app'		=> 'core',				// The application key which holds the restrictrions
	 'module'	=> 'foo',				// The module key which holds the restrictions
	 'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 'add'			=> 'foo_add',
	 'edit'			=> 'foo_edit',
	 'permissions'	=> 'foo_perms',
	 'delete'		=> 'foo_delete'
	 ),
	 'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
			'app'		=> 'cms',
			'module'	=> 'pages',
			'prefix' 	=> 'page_'
	);
	
	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'cms';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'pages';
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
			'view' => 'view'
	);
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_content_page_';
	
	/**
	 * @brief	[Page] Loaded pages from paths
	 */
	protected static $loadedPagesFromPath = array();
	
	/**
	 * @brief	[Page] Currently loaded page
	 */
	public static $currentPage = NULL;

	/**
	 * @brief	[Page] Default page
	 */
	public static $defaultPage = array();

	/**
	 *
	 */
	/**
	 * @brief	Pre save flag
	 */
	const PRE_SAVE  = 1;
	
	/**
	 * @brief	Post save flag
	 */
	const POST_SAVE = 2;

	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->js_css_ids       = '';
		$this->content          = '';
		$this->meta_keywords    = '';
		$this->meta_description = '';
		$this->template         = '';
		$this->full_path        = '';
		$this->js_css_objects   = '';
	}

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}" as the key
	 */
	public static $titleLangPrefix = 'cms_page_';

	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	\IPS\cms\Pages\Page
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		$qs = array_merge( $url->queryString, $url->getFriendlyUrlData() );
		
		if ( isset( $qs['id'] ) )
		{
			if ( method_exists( get_called_class(), 'loadAndCheckPerms' ) )
			{
				return static::loadAndCheckPerms( $qs['id'] );
			}
			else
			{
				return static::load( $qs['id'] );
			}
		}
		else if ( isset( $qs['path'] ) )
		{
			if ( method_exists( get_called_class(), 'loadAndCheckPerms' ) )
			{
				return static::loadAndCheckPerms( $qs['path'], 'page_full_path' );
			}
			else
			{
				return static::load( $qs['path'], 'page_full_path' );
			}
		}
	
		throw new \InvalidArgumentException;
	}
	
	/**
	 * Get the page based on the database ID
	 * 
	 * @param int $databaseId
	 * @return	\IPS\cms\Pages\Page object
	 * @throws  \OutOfRangeException
	 */
	public static function loadByDatabaseId( $databaseId )
	{
		return static::load( \IPS\cms\Databases::load( $databaseId )->page_id );
	}
	
	/**
	 * Resets a page path
	 *
	 * @param 	int 	$folderId	Folder ID to reset
	 * @return	void
	 */
	public static function resetPath( $folderId )
	{
		try
		{
			$path = \IPS\cms\Pages\Folder::load( $folderId )->path;
		}
		catch ( \OutOfRangeException $ex )
		{
			throw new \OutOfRangeException;
		}
	
		$children = static::getChildren( $folderId );
	
		foreach( $children as $id => $obj )
		{
			$obj->setFullPath( $path );
		}
	}
	
	/**
	 * Get all children of a specific folder.
	 *
	 * @param	INT 	$folderId		Folder ID to fetch children from
	 * @return	array
	 */
	public static function getChildren( $folderId=0 )
	{
		$children = array();
		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, array( 'page_folder_id=?', intval( $folderId ) ), 'page_seo_name ASC' ) as $child )
		{
			$children[ $child[ static::$databasePrefix . static::$databaseColumnId ] ] = static::load( $child[ static::$databasePrefix . static::$databaseColumnId ] );
		}
	
		return $children;
	}

	
	/**
	 * Returns a page object (or NULL) based on the path
	 * 
	 * @param	string	$path	Path /like/this/ok.html
	 * @return	NULL|\IPS\cms\Pages\Page object
	 */
	public static function loadFromPath( $path )
	{
		$path = trim( $path, '/' );
		
		if ( ! array_key_exists( $path, static::$loadedPagesFromPath ) )
		{
			static::$loadedPagesFromPath[ $path ] = NULL;
			
			/* Try the simplest option */
			try
			{
				static::$loadedPagesFromPath[ $path ] =  static::load( $path, 'page_full_path' );
			}
			catch ( \OutOfRangeException $e )
			{
				/* Nope - try a folder */
				try
				{
					$class  = static::$parentNodeClass;
					$folder = $class::load( $path, 'folder_path' );
					
					static::$loadedPagesFromPath[ $path ] = static::getDefaultPage( $folder->id );
				}
				catch ( \OutOfRangeException $e )
				{
					/* May contain a database path */
					if ( \strstr( $path, '/' ) )
					{
						$bits = explode( '/', $path );
						$pathsToTry = array();
						
						while( count( $bits ) )
						{
							$pathsToTry[] = implode( '/', $bits );
							
							array_pop($bits);
						}
						
						try
						{
							static::$loadedPagesFromPath[ $path ] = static::constructFromData( \IPS\Db::i()->select( '*', 'cms_pages', \IPS\Db::i()->in( 'page_full_path', $pathsToTry ), 'page_full_path DESC' )->first() );
						}
						catch( \UnderFlowException $e )
						{
							/* Last chance saloon */
							foreach( \IPS\Db::i()->select( '*', 'cms_pages', array( '? LIKE CONCAT( page_full_path, \'%\')', $path ), 'page_full_path DESC' ) as $page )
							{
								if ( mb_stristr( $page['page_content'], '{database' ) )
								{
									static::$loadedPagesFromPath[ $path ] = static::constructFromData( $page );
									break;
								}
							}
							
							/* Still here? It's possible this is a legacy URL that starts with "page" - last ditch effort */
							if ( static::$loadedPagesFromPath[ $path ] === NULL AND mb_substr( $path, 0, 5 ) === 'page/' )
							{
								$pathWithoutPage = str_replace( 'page/', '', $path );
								
								try
								{
									/* Pass back recursively so we don't have to duplicate all of the checks again */
									static::$loadedPagesFromPath[ $path ] = static::loadFromPath( $pathWithoutPage );
								}
								catch( \OutOfRangeException $e ) {}
							}
						}
					}
				}
			}
		}
		
		if ( static::$loadedPagesFromPath[ $path ] === NULL )
		{
			throw new \OutOfRangeException;
		}

		return static::$loadedPagesFromPath[ $path ];
	}

	/**
	 * Load from path history so we can 301 to the correct record.
	 *
	 * @param	string		$slug			Thing that lives in the garden and eats your plants
	 * @param	string|NULL	$queryString	Any query string to add to the end
	 * @return	\IPS\cms\Pages\Page
	 */
	public static function getUrlFromHistory( $slug, $queryString=NULL )
	{
		$slug = trim( $slug, '/' );
		
		try
		{
			$row = \IPS\Db::i()->select( '*', 'cms_url_store', array( 'store_type=? and store_path=?', 'page', $slug ) )->first();

			return static::load( $row['store_current_id'] )->url();
		}
		catch( \UnderflowException $ex )
		{
			/* Ok, perhaps this is a full URL with the page name at the beginning */
			foreach( \IPS\Db::i()->select( '*', 'cms_url_store', array( 'store_type=? and ? LIKE CONCAT( store_path, \'%\') OR store_path=?', 'page', $slug, $slug ) ) as $item )
			{
				$url = static::load( $item['store_current_id'] )->url();
				
				$url->data['path'] = '/' . trim( str_replace( $item['store_path'], trim( $url->data['path'], '/' ), $slug ), '/' );
				
				if ( $queryString !== NULL )
				{
					$url->data['query'] = $queryString;
				}
				return \IPS\Http\Url::createFromArray( $url->data );
			}
			
			/* Still here? Ok, now we may have changed the folder name at some point, so lets look for that */
			foreach( \IPS\Db::i()->select( '*', 'cms_url_store', array( 'store_type=? and ? LIKE CONCAT( store_path, \'%\') OR store_path=?', 'folder', $slug, $slug ) ) as $item )
			{
				try
				{
					$folder = \IPS\cms\Pages\Folder::load( $item['store_current_id'] );

					/* Attempt to build the new path */
					$newPath = str_replace( $item['store_path'], $folder->path, $slug );

					/* Do we have a page with this path? */
					try
					{
						return static::load( $newPath, 'page_full_path' );
					}
					catch( \OutOfRangeException $ex )
					{
						/* This is not the path you are looking for */
					}

				}
				catch( \OutOfRangeException $ex )
				{
					/* This also is not the path you are looking for */
				}
			}
		}

		/* Still here? Consistent with AR pattern */
		throw new \OutOfRangeException();
	}
	
	/**
	 * Return the default page for this folder
	 *
	 * @param	INT 	$folderId		Folder ID to fetch children from
	 * @return	\IPS\cms\Pages\Page
	 */
	public static function getDefaultPage( $folderId=0 )
	{
		if ( ! isset( static::$defaultPage[ $folderId ] ) )
		{
			/* Try the easiest method first */
			try
			{
				static::$defaultPage[ $folderId ] = \IPS\cms\Pages\Page::load( \IPS\Db::i()->select( 'page_id', static::$databaseTable, array( 'page_default=? AND page_folder_id=?', 1, intval( $folderId ) ) )->first() );
			}
			catch( \Exception $ex )
			{
				throw new \OutOfRangeException;
			}

			/* Got a page called index? */
			if ( ! isset( static::$defaultPage[ $folderId ] ) )
			{
				foreach( static::getChildren( $folderId ) as $id => $obj )
				{
					if ( \mb_substr( $obj->seo_name, 0, 5 ) === 'index' )
					{
						return $obj;
					}
				}

				reset( $children );

				/* Just return the first, then */
				static::$defaultPage[ $folderId ] = array_shift( $children );
			}
		}

		return ( isset( static::$defaultPage[ $folderId ] ) ) ? static::$defaultPage[ $folderId ] : NULL;
	}
	
	/**
	 * Delete compiled versions
	 *
	 * @param 	int|array 	$ids	Integer ID or Array IDs to remove
	 * @return void
	 */
	public static function deleteCompiled( $ids )
	{
		if ( is_numeric( $ids ) )
		{
			$ids = array( $ids );
		}
	
		foreach( $ids as $id )
		{
			$functionName = 'content_pages_' .  $id;
			if ( isset( \IPS\Data\Store::i()->$functionName ) )
			{
				unset( \IPS\Data\Store::i()->$functionName );
			}
		}
	}

	/**
	 * Removes all include objects from all pages
	 *
	 * @param   boolean     $url     The URL to find and remove
	 * @return void
	 */
	static public function deleteCachedIncludes( $url=NULL )
	{
		/* Remove them all */
		if ( $url === NULL )
		{
            /* Remove from DB */
            if ( \IPS\Db::i()->checkForTable( 'cms_pages' ) )
            {
                \IPS\Db::i()->update( 'cms_pages', array( 'page_js_css_objects' => NULL ) );
            }
			/* Remove from file system */
			\IPS\File::getClass( 'cms_Pages')->deleteContainer('page_objects');
		}
		else
		{
			$bits = explode( '/', (string ) $url );
			$name = array_pop( $bits );

			/* Remove selectively */
			foreach( \IPS\Db::i()->select( '*', 'cms_pages', array( "page_js_css_objects LIKE '%" . \IPS\Db::i()->escape_string( $name ) . "%'" ) ) as $row )
			{
				\IPS\Db::i()->update( 'cms_pages', array( 'page_js_css_objects' => NULL ), array( 'page_id=?', $row['page_id'] ) );
			}
		}
	}

	/**
	 * Show a custom error page
	 *
	 * @param   string  $title          Title of the page
	 * @param	string	$message		language key for error message
	 * @param	mixed	$code			Error code
	 * @param	int		$httpStatusCode	HTTP Status Code
	 * @param	array	$httpHeaders	Additional HTTP Headers
	 * @return  void
	 */
	static public function errorPage( $title, $message, $code, $httpStatusCode, $httpHeaders )
	{
		try
		{

			$page = static::load( \IPS\Settings::i()->cms_error_page );
			$page->content = str_replace( '{error_message}', $message, $page->content );
			$page->content = str_replace( '{error_code}', $code, $page->content );

			$page->output( $title, $httpStatusCode, $httpHeaders );
		}
		catch( \Exception $ex )
		{
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( $title, \IPS\Theme::i()->getTemplate( 'global', 'core' )->error( $title, $message, $code, NULL, \IPS\Member::loggedIn() ), array( 'app' => \IPS\Dispatcher::i()->application ? \IPS\Dispatcher::i()->application->directory : NULL, 'module' => \IPS\Dispatcher::i()->module ? \IPS\Dispatcher::i()->module->key : NULL, 'controller' => \IPS\Dispatcher::i()->controller ) ), $httpStatusCode, 'text/html', $httpHeaders, FALSE, FALSE );
		}
	}

	/**
	 * Form elements
	 *
	 * @param	object|null		$item	Page object or NULL
	 * @return	array
	 */
	static public function formElements( $item=NULL )
	{
		$return   = array();
		$pageType = isset( \IPS\Request::i()->page_type ) ? \IPS\Request::i()->page_type : ( $item ? $item->type : 'html' );
		$return['tab_details'] = array( 'content_page_form_tab__details' );

		$return['page_name'] = new \IPS\Helpers\Form\Translatable( 'page_name', NULL, TRUE, array( 'app' => 'cms', 'key' => ( $item and $item->id ) ? "cms_page_" . $item->id : NULL, 'maxLength' => 64 ), function( $val )
		{
			if ( empty( $val ) )
			{
				throw new \DomainException('form_required');
			}		
		}, NULL, NULL, 'page_name' );
		
		$return['page_seo_name'] = new \IPS\Helpers\Form\Text( 'page_seo_name', $item ? $item->seo_name : '', FALSE, array( 'maxLength' => 255 ), function( $val )
		{
			if ( empty( $val ) )
			{
				$val = \IPS\Http\Url::seoTitle( $val );
			}
	
			try
			{
				$test = \IPS\cms\Pages\Page::load( $val, 'page_seo_name' );

				if ( isset( \IPS\Request::i()->id ) )
				{
					if ( $test->id == \IPS\Request::i()->id )
					{
						/* Just us.. */
						return TRUE;
					}
				}

				/* Not us */
				if ( intval( \IPS\Request::i()->page_folder_id ) == $test->folder_id )
				{
					throw new \InvalidArgumentException( 'content_page_file_name_in_use' );
				}

			}
			catch ( \OutOfRangeException $e )
			{
				/* If we hit here, we don't have an existing name so that's good */
				if ( ! \IPS\Request::i()->page_folder_id  and \IPS\cms\Pages\Page::isFurlCollision( $val ) )
				{
					throw new \InvalidArgumentException('content_folder_name_furl_collision');
				}
			}
		}, NULL, NULL, 'page_seo_name' );

		$return['page_folder_id'] = new \IPS\Helpers\Form\Node( 'page_folder_id', ( $item ? intval( $item->folder_id ) : ( ( isset( \IPS\Request::i()->parent ) and \IPS\Request::i()->parent ) ? \IPS\Request::i()->parent : 0 ) ), FALSE, array(
				'class'         => 'IPS\cms\Pages\Folder',
				'zeroVal'       => 'node_no_parent',
				'subnodes'		=> false
		), NULL, NULL, NULL, 'page_folder_id' );
	

		$return['page_ipb_wrapper'] = new \IPS\Helpers\Form\YesNo( 'page_ipb_wrapper', $item AND $item->id ? $item->ipb_wrapper : 1, TRUE, array(
				'togglesOn' => array( 'page_show_sidebar' ),
		        'togglesOff' => array( 'page_wrapper_template' )
		), NULL, NULL, NULL, 'page_ipb_wrapper' );

		$return['page_show_sidebar'] = new \IPS\Helpers\Form\YesNo( 'page_show_sidebar', $item ? $item->show_sidebar : TRUE, FALSE, array(), NULL, NULL, NULL, 'page_show_sidebar' );

		$wrapperTemplates = array( '_none_' => \IPS\Member::loggedIn()->language()->addToStack('cms_page_wrapper_template_none') );
		foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_PAGE + \IPS\cms\Templates::RETURN_DATABASE_AND_IN_DEV ) as $id => $obj )
		{
			if ( $obj->isSuitableForCustomWrapper() )
			{
				$wrapperTemplates[ \IPS\cms\Templates::readableGroupName( $obj->group ) ][ $obj->group . '__' . $obj->title . '__' . $obj->key ] = \IPS\cms\Templates::readableGroupName( $obj->title );
			}
		}

		/* List of templates */
		$return['page_wrapper_template'] = new \IPS\Helpers\Form\Select( 'page_wrapper_template', ( $item ? $item->wrapper_template : NULL ), FALSE, array(
			         'options' => $wrapperTemplates
		), NULL, NULL, \IPS\Theme::i()->getTemplate( 'pages', 'cms', 'admin' )->previewTemplateLink(), 'page_wrapper_template' );

		if ( count( \IPS\Theme::themes() ) > 1 )
		{
			$themes = array( 0 => 'cms_page_theme_id_default' );
			foreach ( \IPS\Theme::themes() as $theme )
			{
				$themes[ $theme->id ] = $theme->_title;
			}

			$return['page_theme'] = new \IPS\Helpers\Form\Select( 'page_theme', $item ? $item->theme : 0, FALSE, array( 'options' => $themes ), NULL, NULL, NULL, 'page_theme' );
		}

		$builderTemplates = array();
		foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_PAGE + \IPS\cms\Templates::RETURN_DATABASE_AND_IN_DEV ) as $id => $obj )
		{
			if ( $obj->isSuitableForBuilderWrapper() )
			{
				$builderTemplates[ \IPS\cms\Templates::readableGroupName( $obj->group ) ][ $obj->group . '__' . $obj->title . '__' . $obj->key ] = \IPS\cms\Templates::readableGroupName( $obj->title );
			}
		}

		$return['page_template'] = new \IPS\Helpers\Form\Select( 'page_template', ( $item and $item->template ) ? $item->template : FALSE, FALSE, array( 'options' => $builderTemplates ), NULL, NULL, NULL, 'page_template' );

		/* Page CSS and JS */
		$js  = \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_ONLY_JS + \IPS\cms\Templates::RETURN_DATABASE_ONLY );
		$css = \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_ONLY_CSS + \IPS\cms\Templates::RETURN_DATABASE_ONLY );

		if ( count( $js ) OR count( $css ) )
		{
			$return['tab_js_css'] = array( 'content_page_form_tab__includes' );
			$return['msg_js_css'] = array( 'cms_page_includes_message', 'ipsMessage ipsMessage_info ipsCmsIncludesMessage' );

			if ( count( $js ) )
			{
				$jsincludes = array();
				foreach( $js as $obj )
				{
					$jsincludes[ $obj->key ] = \IPS\cms\Templates::readableGroupName( $obj->group ) . '/' . \IPS\cms\Templates::readableGroupName( $obj->title );
				}

				$return['page_includes_js'] = new \IPS\Helpers\Form\Select( 'page_includes_js', $item ? $item->js_includes : FALSE, FALSE, array( 'options' => $jsincludes, 'multiple' => true ), NULL, NULL, NULL, 'page_includes_js' );
			}

			if ( count( $css ) )
			{
				$cssincludes = array();
				foreach( $css as $obj )
				{
					$cssincludes[ $obj->key ] = \IPS\cms\Templates::readableGroupName( $obj->group ) . '/' . \IPS\cms\Templates::readableGroupName( $obj->title );
				}

				$return['page_includes_css'] = new \IPS\Helpers\Form\Select( 'page_includes_css', $item ? $item->css_includes : FALSE, FALSE, array( 'options' => $cssincludes, 'multiple' => true ), NULL, NULL, NULL, 'page_includes_css' );
			}
		}

		if ( $pageType === 'html' )
		{
			$return['tab_content'] = array( 'content_page_form_tab__content', NULL, NULL, 'ipsForm_vertical' );
		
			$tags = array();

			if ( $item and $item->id == \IPS\Settings::i()->cms_error_page )
			{
				$tags['cms_error_page']['{error_message}'] = 'cms_error_page_message';
				$tags['cms_error_page']['{error_code}']    = 'cms_error_page_code';
			}

			foreach( \IPS\cms\Databases::roots( NULL, NULL ) as $id => $db )
			{
				if ( ! $db->page_id )
				{
					$tags['cms_tag_databases']['{database="' . $db->key . '"}'] = $db->_title;
				}
			}
			
			foreach( \IPS\cms\Blocks\Block::roots( NULL, NULL ) as $id => $block )
			{
				$tags['cms_tag_blocks']['{block="' . $block->key . '"}'] = $block->_title;
			}

			foreach( \IPS\Db::i()->select( '*', 'cms_pages', NULL, 'page_full_path ASC', array( 0, 50 ) ) as $page )
			{
				$tags['cms_tag_pages']['{pageurl="' . $page['page_full_path'] . '"}' ] = \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $page['page_id'] );
			}

			$return['page_content'] = new \IPS\Helpers\Form\Codemirror( 'page_content', $item ? htmlentities( $item->content, \IPS\HTMLENTITIES, 'UTF-8', TRUE ) : NULL, FALSE, array( 'tags' => $tags, 'height' => 600 ), function( $val )
			{
				/* Test */
				try
				{
					\IPS\Theme::checkTemplateSyntax( $val );
				}
				catch( \LogicException $e )
				{
					throw new \LogicException('cms_page_error_bad_syntax');
				}
				
				/* New page? quick check to see if we added a DB tag, and if so, make sure it's not being used on another page. */
				if ( ! isset( \IPS\Request::i()->id ) )
				{
					preg_match( '#{database="([^"]+?)"#', $val, $matches );
					if ( isset( $matches[1] ) )
					{
						try
						{
							if ( is_numeric( $matches[1] ) )
							{
								$database = \IPS\cms\Databases::load( intval( $matches[1] ) );
							}
							else
							{
								$database = \IPS\cms\Databases::load( $matches[1], 'database_key' );
							}
							
							if ( $database->page_id )
							{
								throw new \LogicException('cms_err_db_in_use_other_page');
							}
						}
						catch( \OutOfRangeException $ex )
						{
							throw new \LogicException('cms_err_db_does_not_exist');
						}
					}
				}
			}, NULL, NULL, 'page_content' );
		}
	
		$return['tab_meta'] = array( 'content_page_form_tab__meta' );
		$return['page_title'] = new \IPS\Helpers\Form\Text( 'page_title', $item ? $item->title : '', FALSE, array( 'maxLength' => 64 ), NULL, NULL, NULL, 'page_title' );
		$return['page_meta_keywords'] = new \IPS\Helpers\Form\TextArea( 'page_meta_keywords', $item ? $item->meta_keywords : '', FALSE, array(), NULL, NULL, NULL, 'page_meta_keywords' );
		$return['page_meta_description'] = new \IPS\Helpers\Form\TextArea( 'page_meta_description', $item ? $item->meta_description : '', FALSE, array(), NULL, NULL, NULL, 'page_meta_description' );

		\IPS\Output::i()->globalControllers[]  = 'cms.admin.pages.form';
		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_pages.js', 'cms' ) );
		
		return $return;
	}
	
	/**
	 * Create a new page from a form. Pretty much what the function says.
	 * 
	 * @param	array		 $values	Array of form values
	 * @param	string		 $pageType	Type of page. 'html', 'builder' or 'editor'
	 * @return	\IPS\cms\Pages\Page object
	 */
	static public function createFromForm( $values, $pageType=NULL )
	{
		$page = new self;
		$page->type = $pageType;
		$page->save();

		$page->saveForm( $page->formatFormValues( $values ), $pageType );

		/* Set permissions */
		\IPS\Db::i()->update( 'core_permission_index', array( 'perm_view' => '*' ), array( 'app=? and perm_type=? and perm_type_id=?', 'cms', 'pages', $page->id ) );
		
		return $page;
	}

	/**
	 * Ensure there aren't any collision issues when the CMS is the default app and folders such as "forums" are created when
	 * the forums app is installed.
	 *
	 * @param   string  $path   Path to check
	 * @return  boolean
	 */
	static public function isFurlCollision( $path )
	{
		$path   = trim( $path , '/');
		$bits   = explode( '/', $path );
		$folder = $bits[0];

		$defaultApplication = \IPS\Db::i()->select( 'app_directory', 'core_applications', 'app_default=1' )->first();

		foreach( \IPS\Application::applications() as $key => $app )
		{
			if ( $app->directory === 'cms' )
			{
				continue;
			}

			$furlDefinitionFile = \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json";
			if ( file_exists( $furlDefinitionFile ) )
			{
				$furlDefinition = json_decode( preg_replace( '/\/\*.+?\*\//s', '', file_get_contents( $furlDefinitionFile ) ), TRUE );

				if ( isset( $furlDefinition['topLevel'] ) )
				{
					if ( $furlDefinition['topLevel'] == $folder )
					{
						return TRUE;
					}

					if ( isset( $furlDefinition['pages'] ) )
					{
						foreach( $furlDefinition['pages'] as $name => $data )
						{
							if ( isset( $data['friendly'] ) )
							{
								$furlBits = explode( '/', $data['friendly'] );

								if ( $furlBits[0] == $folder )
								{
									return TRUE;
								}
							}
						}
					}
				}
			}
		}

		return FALSE;
	}
	
	/**
	 * Delete all stored includes so they can be rebuilt on demand.
	 *
	 * @return	void
	 */
	public static function deleteCompiledIncludes()
	{
		\IPS\Db::i()->update( 'cms_pages', array( 'page_js_css_objects' => NULL ) );
		\IPS\cms\Templates::deleteCompiledFiles();
	}
	
	/**
	 * Create a datastore object of page IDs and URLs.
	 *
	 * @return void
	 */
	public static function buildPageUrlStore()
	{
		$store = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_pages' ), 'IPS\cms\Pages\Page' ) as $page )
		{
			$perms = $page->permissions();
			$store[ $page->id ] = array( 'url' => (string) $page->url(), 'perm' => $perms['perm_view'] );
		}
		
		\IPS\Data\Store::i()->pages_page_urls = $store;
	}
	
	/**
	 * Returns (and builds if required) the pages id => url datastore
	 *
	 * @return array
	 */
	public static function getPageUrlStore()
	{
		if ( ! isset( \IPS\Data\Store::i()->pages_page_urls ) )
		{
			static::buildPageUrlStore();
		}
		
		return \IPS\Data\Store::i()->pages_page_urls;
	}
		
		
	/**
	 * Set JS/CSS include keys
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set__js_css_ids( $value )
	{
		$this->_data['js_css_ids'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}

	/**
	 * Get JS/CSS include keys
	 *
	 * @return	array|null
	 */
	protected function get__js_css_ids()
	{
		if ( ! is_array( $this->_data['js_css_ids'] ) )
		{
			$this->_data['js_css_ids'] = json_decode( $this->_data['js_css_ids'], true );
		}

		return ( is_array( $this->_data['js_css_ids'] ) ) ? $this->_data['js_css_ids'] : array();
	}

	/**
	 * Get JS include keys
	 *
	 * @return	array
	 */
	protected function get_js_includes()
	{
		/* Makes sure js_css_ids is unpacked if required */
		$this->_js_css_ids;

		if ( isset( $this->_data['js_css_ids']['js'] ) )
		{
			return $this->_data['js_css_ids']['js'];
		}

		return array();
	}

	/**
	 * Get CSS include keys
	 *
	 * @return	array
	 */
	protected function get_css_includes()
	{
		/* Makes sure js_css_ids is unpacked if required */
		$this->_js_css_ids;

		if ( isset( $this->_data['js_css_ids']['css'] ) )
		{
			return $this->_data['js_css_ids']['css'];
		}

		return array();
	}

	/**
	 *  Get JS/CSS Objects
	 *
	 * @return array
	 */
	public function getIncludes()
	{
		$return = array( 'css' => NULL, 'js' => NULL );

		/* Empty? Lets take a look and see if we need to compile anything */
		if ( empty( $this->_data['js_css_objects'] ) )
		{
			if ( count( $this->js_includes ) )
			{
				/* Build a file object for each JS */
				foreach( $this->js_includes as $key )
				{
					try
					{
						$template = \IPS\cms\Templates::load( $key );
						$object   = $template->_file_object;

						$return['js'][ $key ] = $object;
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}
				}
			}

			if ( count( $this->css_includes ) )
			{
				/* Build a file object for each JS */
				foreach( $this->css_includes as $key )
				{
					try
					{
						$template = \IPS\cms\Templates::load( $key );
						$object   = $template->_file_object;

						$return['css'][ $key ] = $object;
					}
					catch( \Exception $e )
					{
						continue;
					}
				}
			}

			/* Save this to prevent it looking for includes on every page refresh */
			$this->js_css_objects = json_encode( $return );
			$this->save();
		}
		else
		{
			$return = json_decode( $this->_data['js_css_objects'], TRUE );
		}

		foreach( $return as $type => $data )
		{
			if ( is_array( $data ) )
			{
				foreach( $data as $key => $object )
				{
					$return[ $type ][ $key ] = (string) \IPS\File::get( 'cms_Pages', $object )->url;
				}
			}
		}

		return $return;
	}

	/**
	 * Get the content type of this page. Calculates based on page extension
	 *
	 * @return string
	 */
	public function getContentType()
	{
		$map  = array(
			'js'   => 'text/javascript',
			'css'  => 'text/css',
			'txt'  => 'text/plain',
			'xml'  => 'text/xml',
			'rss'  => 'text/xml',
			'html' => 'text/html',
			'json' => 'application/json'
		);

		$extension = mb_substr( $this->seo_name, ( mb_strrpos( $this->seo_name, '.' ) + 1 ) );

		if ( in_array( $extension, array_keys( $map ) ) )
		{
			return $map[ $extension ];
		}

		return 'text/html';
	}

	/**
	 * Return the title for the publicly viewable HTML page
	 * 
	 * @return string	Title to use between <title> tags
	 */
	public function getHtmlTitle()
	{
		if ( $this->title )
		{
			return $this->title;
		}
		
		if ( $this->_title )
		{
			return $this->_title;
		}
		
		return $this->name;
	}
	
	/**
	 * Return the content for the publicly viewable HTML page
	 * 
	 * @return	string	HTML to use on the page
	 */
	public function getHtmlContent()
	{
		$functionName = 'content_pages_' .  $this->id;
		
		if ( ! isset( \IPS\Data\Store::i()->$functionName ) )
		{ 
			\IPS\Data\Store::i()->$functionName = \IPS\Theme::compileTemplate( $this->content, $functionName, null, true );
		}
		
		\IPS\Theme::runProcessFunction( \IPS\Data\Store::i()->$functionName, $functionName );
		
		return call_user_func( 'IPS\\Theme\\'. $functionName );
	}

	/**
	 * @brief	Cached widgets
	 */
	protected $cachedWidgets = NULL;

	/**
	 * Return the blocks for this page
	 *
	 * @return	array
	 */
	public function getWidgets()
	{
		if( $this->cachedWidgets !== NULL )
		{
			return $this->cachedWidgets;
		}

		$this->cachedWidgets = array();

		$widgets	= array();
		$dbWidgets	= array();
		$areas		= array();
		
		foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=?', $this->id ) ) as $k => $widgetMain )
		{
			$widgets[ $widgetMain['area_area'] ] = json_decode( $widgetMain['area_widgets'], TRUE );
			$areas[ $widgetMain['area_area'] ]   = $widgetMain;

			/* We need to execute database widgets first as this sets up the Database dispatcher correctly */
			foreach( $widgets[ $widgetMain['area_area'] ] as $widget )
			{
				/* Do not attempt to re-parse database widgets if we already have */
				if ( ! \IPS\cms\Databases\Dispatcher::i()->databaseId AND $widget['key'] === 'Database' )
				{
					$orientation = ( ( $widgetMain['area_area'] == 'sidebar' ) ? 'vertical' : ( ( $widgetMain['area_area'] === 'header' OR $widgetMain['area_area'] === 'footer' ) ? 'horizontal' : $widgetMain['area_orientation'] ) );
					$dbWidgets[ $widget['unique'] ] = \IPS\Widget::load( \IPS\Application::load( $widget['app'] ), $widget['key'], $widget['unique'], ( isset( $widget['configuration'] ) ) ? $widget['configuration'] : array(), ( isset( $widget['restrict'] ) ? $widget['restrict'] : null ), $orientation );
					$dbWidgets[ $widget['unique'] ]->render();
				}
			}
		}
		
		if( count( $widgets ) )
		{
			foreach ( $widgets as $areaKey => $area )
			{
				foreach ( $area as $widget )
				{
					try
					{
						if ( $widget['key'] == 'Database' and array_key_exists( $widget['unique'], $dbWidgets ) )
						{
							$_widget = $dbWidgets[ $widget['unique'] ];
						}
						else
						{
							$orientation = ( ( $areaKey == 'sidebar' ) ? 'vertical' : ( ( $areaKey === 'header' OR $areaKey === 'footer' ) ? 'horizontal' : $areas[ $areaKey ]['area_orientation'] ) );
							
							if ( isset( $widget['app'] ) and $widget['app'] )
							{
								$appOrPlugin = \IPS\Application::load( $widget['app'] );
							}
							else
							{
								$appOrPlugin = \IPS\Plugin::load( $widget['plugin'] );
							}
							
							$_widget = \IPS\Widget::load( $appOrPlugin, $widget['key'], $widget['unique'], ( isset( $widget['configuration'] ) ) ? $widget['configuration'] : array(), ( isset( $widget['restrict'] ) ? $widget['restrict'] : null ), $orientation );
						}
						
						if ( in_array( $areaKey, array('header', 'footer', 'sidebar' ) ) )
						{
							\IPS\Output::i()->sidebar['widgets'][ $areaKey ][] = $_widget;
						}
							
						$this->cachedWidgets[ $areaKey ][] = $_widget;
					}
					catch ( \Exception $e )
					{
						\IPS\Log::log( $e, 'pages_widgets' );
					}
				}
			}
		}

		return $this->cachedWidgets;
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );
		$return  = array();
		
		if ( isset( $buttons['add'] ) )
		{
			unset( $buttons['add'] );
		}
		
		if ( $this->type === 'builder' and isset( $buttons['edit'] ) )
		{
			$return['builder'] = array(
					'icon'	   => 'magic',
					'title'    => 'content_launch_page_builder',
					'link'	   => $this->url()->setQueryString( array( '_blockManager' => 1 ) ),
					'target'   => '_blank'
			);
		}
		else
		{
			$return['view'] = array(
					'icon'	   => 'search',
					'title'    => 'content_launch_page_view',
					'link'	   => $this->url(),
					'target'   => '_blank'
			);
		}
		
		if ( isset( $buttons['edit'] ) )
		{
			$buttons['edit']['title'] = \IPS\Member::loggedIn()->language()->addToStack('content_edit_page');
			$buttons['edit']['data']  = null;
		}
	
		/* Re-arrange */
		if ( isset( $buttons['edit'] ) )
		{
			$return['edit'] = $buttons['edit'];
		}
		
		if ( isset( $buttons['edit_content'] ) )
		{
			$return['edit_content'] = $buttons['edit_content'];
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'pages', 'page_edit' ) )
		{
			$return['default'] = array(
				'icon'	=> $this->default ? 'star' : 'star-o',
				'title'	=> 'content_default_page',
				'link'	=> $url->setQueryString( array( 'id' => $this->id, 'subnode' => 1, 'do' => 'setAsDefault' ) )
			);
		}

		$return['view'] = array(
			'icon'	   => 'search',
			'title'    => 'content_launch_page',
			'link'	   => $this->url(),
			'target'   => '_blank'
		);
		
		if ( isset( $buttons['permissions'] ) )
		{
			$return['permissions'] = $buttons['permissions'];
		}
		
		if ( isset( $buttons['delete'] ) )
		{
			$return['delete'] = $buttons['delete'];
		}
		
		return $return;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		/* Build form */
		if ( ! $this->id )
		{
			$form->hiddenValues['page_type'] = $pageType = \IPS\Request::i()->page_type;
			 
		}
		else
		{
			$pageType = $this->type;
		}

		foreach( static::formElements( $this ) as $name => $field )
		{
			if ( $pageType !== 'html' and in_array( $name, array( 'page_ipb_wrapper', 'page_show_sidebar', 'page_wrapper_template' ) ) )
			{
				continue;
			}

			if ( $pageType === 'html' and in_array( $name, array( 'page_template' ) ) )
			{
				continue;
			}

			if ( is_array( $field ) )
			{
				if ( mb_substr( $name, 0, 4 ) === 'tab_' )
				{
					call_user_func_array( array( $form, 'addTab' ), $field );
				}
				else if ( mb_substr( $name, 0, 4 ) === 'msg_' )
				{
					call_user_func_array( array( $form, 'addMessage' ), $field );
				}
			}
			else
			{
				$form->add( $field );
			}
		}

		if ( ! $this->id )
		{
			$form->addTab( 'content_page_form_tab__menu' );
			$toggles    = array( 'menu_manager_access_type', 'menu_parent' );
			$formFields = array();
			
			foreach( \IPS\cms\extensions\core\FrontNavigation\Pages::configuration( array() ) as $field )
			{
				if ( $field->name !== 'menu_content_page' )
				{
					$toggles[] = $field->name;
					$formFields[ $field->name ] = $field;
				}
			}
			$form->add( new \IPS\Helpers\Form\YesNo( 'page_add_to_menu', FALSE, FALSE, array( 'togglesOn' => $toggles ) ) );
			
			$roots = array();
			foreach ( \IPS\core\FrontNavigation::i()->roots( FALSE ) as $item )
			{
				$roots[ $item->id ] = $item->title();
			}
			$form->add( new \IPS\Helpers\Form\Select( 'menu_parent', '*', NULL, array( 'options' => $roots ), NULL, NULL, NULL, 'menu_parent' ) );

			
			foreach( $formFields as $name => $field )
			{
				$form->add( $field );
			}
			
			$groups = array();
			foreach ( \IPS\Member\Group::groups() as $group )
			{
				$groups[ $group->g_id ] = $group->name;
			}
			$form->add( new \IPS\Helpers\Form\Radio( 'menu_manager_access_type', 0, TRUE, array(
				'options'	=> array( 0 => 'menu_manager_access_type_inherit', 1 => 'menu_manager_access_type_override' ),
				'toggles'	=> array( 1 => array( 'menu_manager_access' ) )
			), NULL, NULL, NULL, 'menu_manager_access_type' ) );
			$form->add( new \IPS\Helpers\Form\Select( 'menu_manager_access', '*', NULL, array( 'multiple' => TRUE, 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'everyone' ), NULL, NULL, NULL, 'menu_manager_access' ) );
		}

		if ( $pageType === 'builder' )
		{
			if ( $this->id )
			{
				\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack('content_acp_page_builder_msg_edit', TRUE, array( 'sprintf' => array( $this->url() ) ) ), 'information', NULL, FALSE );
			}
			else
			{
				\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack('content_acp_page_builder_msg_new' ), 'information', NULL, FALSE );
			}
		}

		$form->addButton( 'save_and_reload', 'submit', null, 'ipsButton ipsButton_primary', array( 'name' => 'save_and_reload', 'value' => 1 ) );
		
		\IPS\Output::i()->title  = $this->id ? \IPS\Member::loggedIn()->language()->addToStack('content_editing_page', NULL, array( 'sprintf' => array( $this->_title ) ) ) : \IPS\Member::loggedIn()->language()->addToStack('content_add_page');
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		$isNew = $this->_new;

		if ( ! $this->id )
		{
			$this->type = \IPS\Request::i()->page_type;
			$this->save();

			if ( $this->type === 'editor' )
			{
				\IPS\File::claimAttachments( 'page-content/pages-' . $this->id, $this->id );
			}
		}

		if( isset( $values['page_name'] ) )
		{
			$_copied	= $values['page_name'];
			$values['page_seo_name'] = empty( $values['page_seo_name'] ) ? ( is_array( $_copied ) ? array_shift( $_copied ) : $_copied ) : $values['page_seo_name'];

			$bits = explode( '.', $values['page_seo_name'] );
			foreach( $bits as $i => $v )
			{
				$bits[ $i ] = \IPS\Http\Url::seoTitle( $v );
			}

			$values['page_seo_name'] = implode( '.', $bits );

			\IPS\Lang::saveCustom( 'cms', "cms_page_" . $this->id, $values['page_name'] );
		}
		
		if ( isset( $values['page_folder_id'] ) AND ( ! empty( $values['page_folder_id'] ) OR $values['page_folder_id'] === 0 ) )
		{
			$values['page_folder_id'] = ( $values['page_folder_id'] === 0 ) ? 0 : $values['page_folder_id']->id;
		}

		if ( isset( $values['page_includes_js'] ) OR isset( $values['page_includes_css'] ) )
		{
			$includes = array();
			if ( isset( $values['page_includes_js'] ) )
			{
				$includes['js'] = $values['page_includes_js'];
			}

			if ( isset( $values['page_includes_css'] ) )
			{
				$includes['css'] = $values['page_includes_css'];
			}

			$this->_js_css_ids = $includes;

			/* Trash file objects to be sure */
			$this->js_css_objects = NULL;
			$values['js_css_objects'] = NULL;

			unset( $values['page_includes_js'], $values['page_includes_css'] );
		}

		try
		{
			$this->processContent( static::PRE_SAVE );
		}
		catch( \LogicException $ex )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('content_err_page_save_exception', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->addToStack( $ex->getMessage() ) ) ) );
		}

		/* Page filename changed? */
		if ( ! $isNew and $values['page_seo_name'] !== $this->seo_name )
		{
			$this->storeUrl();
		}

		/* Menu stuffs */
		if ( isset( $values['page_add_to_menu'] ) )
		{
			if( $values['page_add_to_menu'] )
			{
				$permission = $values['menu_manager_access'] == '*' ? '*' : implode( ',', $values['menu_manager_access'] );
				
				if ( $values['menu_manager_access_type'] === 0 )
				{
					$permission = '';
				}

				$save = array(
					'app'			=> 'cms',
					'extension'		=> 'Pages',
					'config'		=> '',
					'parent'		=> $values['menu_parent'],
					'permissions'   => $permission
				);
				
				try
				{
					$save['position'] = \IPS\Db::i()->select( 'MAX(position)', 'core_menu', array( 'parent=?', \IPS\Request::i()->parent ) )->first() + 1;
				}
				catch ( \UnderflowException $e )
				{
					$save['position'] = 1;
				}
				
				$id = \IPS\Db::i()->insert( 'core_menu', $save );
				
				$values = \IPS\cms\extensions\core\FrontNavigation\Pages::parseConfiguration( $values, $id );
				$config = array( 'menu_content_page' => $this->id );
				
				foreach( array( 'menu_title_page_type', 'menu_title_page' ) as $field )
				{
					if ( isset( $values[ $field ] ) )
					{
						$config[ $field ] = $values[ $field ];
					}
				}
				
				\IPS\Db::i()->update( 'core_menu', array( 'config' => json_encode( $config ) ), array( 'id=?', $id ) );

				unset( \IPS\Data\Store::i()->frontNavigation );
			}

			unset( $values['page_add_to_menu'], $values['menu_title_page_type'], $values['menu_title_page'], $values['menu_parent'], $values['menu_manager_access'], $values['menu_manager_access_type'] );
		}

		return $values;
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		$this->setFullPath( ( $this->folder_id ? \IPS\cms\Pages\Folder::load( $this->folder_id )->path : '' ) );
		$this->save();

		try
		{
			$this->processContent( static::POST_SAVE );
		}
		catch( \LogicException $ex )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('content_err_page_save_exception', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->addToStack( $ex->getMessage() ) ) ) );
		}
	}

	/**
	 * Stores the URL so when its changed, the old can 301 to the new location
	 *
	 * @return void
	 */
	public function storeUrl()
	{
		\IPS\Db::i()->insert( 'cms_url_store', array(
			'store_path'       => $this->full_path,
			'store_current_id' => $this->_id,
			'store_type'       => 'page'
		) );
	}

	/**
	 * Get the Database ID from the page
	 *
	 * @return null|int
	 */
	public function getDatabase()
	{
		try
		{
			return \IPS\cms\Databases::load( $this->id, 'database_page_id' );
		}
		catch( \OutOfRangeException $e ) { }
	
		return null;
	}

	/**
	 * Get the database ID from the page content
	 *
	 * @return  int
	 */
	public function getDatabaseIdFromHtml()
	{
		if ( $this->type !== 'html' )
		{
			throw new \LogicException('cms_page_not_html');
		}

		preg_match( '#{database="([^"]+?)"#', $this->content, $matches );

		if ( isset( $matches[1] ) )
		{
			if ( is_numeric( $matches[1] ) )
			{
				return intval( $matches[1] );
			}
			else
			{
				try
				{
					$database = \IPS\cms\Databases::load( $matches[1], 'database_key' );
					return $database->id;
				}
				catch( \OutOfRangeException $ex )
				{
					return NULL;
				}
			}
		}

		return NULL;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 * 
	 * @return \IPS\Http\Url object
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			if ( \IPS\Application::load('cms')->default AND $this->default AND ! $this->folder_id )
			{
				$this->_url = \IPS\Http\Url::internal( '', 'front' );
			}
			else
			{
				$this->_url = \IPS\Http\Url::internal( 'app=cms&module=pages&controller=page&path=' . $this->full_path, 'front', 'content_page_path', array( $this->full_path ) );
			}
		}

		return $this->_url;
	}
	
	/**
	 * Process the content to see if there are any tags and so on that need action
	 * 
	 * @param	integer		$flag		Pre or post save flag
	 * @return 	void
	 * @throws	\LogicException
	 */
	public function processContent( $flag )
	{
		if ( $this->type === 'html' )
		{
			$seen = array();
			preg_match_all( '/\{([a-z]+?=([\'"]).+?\\2 ?+)}/', $this->content, $matches, PREG_SET_ORDER );
			
			/* Work out the plugin and the values to pass */
			foreach( $matches as $index => $array )
			{
				preg_match_all( '/(.+?)='.$array[2].'(.+?)'.$array[2].'\s?/', $array[1], $submatches );
				
				$plugin  = array_shift( $submatches[1] );
				$pluginClass = 'IPS\\Output\\Plugin\\' . ucfirst( $plugin );
				
				$value   = array_shift( $submatches[2] );
				$options = array();
				
				foreach ( $submatches[1] as $k => $v )
				{
					$options[ $v ] = $submatches[2][ $k ];
				}

				$seen[ mb_strtolower( $plugin ) ] = array( 'value' => $value, 'options' => $options );

				/* Work out if this plugin belongs to an application, and if so, include it */
				if( !class_exists( $pluginClass ) )
				{
					foreach ( \IPS\Application::applications() as $app )
					{
						if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/extensions/core/OutputPlugins/" . ucfirst( $plugin ) . ".php" ) )
						{
							$pluginClass = 'IPS\\' . $app->directory . '\\extensions\\core\\OutputPlugins\\' . ucfirst( $plugin );
						}
					}
				}
				
				$method = ( $flag === static::PRE_SAVE ) ? 'preSaveProcess' : 'postSaveProcess';
				
				if ( method_exists( $pluginClass, $method ) )
				{
					try
					{
						call_user_func( array( $pluginClass, $method ), $value, $options, $this );
					}
					catch( \Exception $ex )
					{
						throw new \LogicException( $ex->getMessage() );
					}
				}
			}

			/* Check to see if we're expecting a database to be here */
			$database = $this->getDatabase();

			if ( $database !== NULL )
			{
				/* We're expecting a database tag, is there one? */
				if ( isset( $seen['database'] ) )
				{
					/* Yep, is it the same ID? */
					if ( $seen['database']['value'] != $database->id )
					{
						/* There's been a change.. */
						try
						{
							$this->removeDatabaseMap();
						}
						catch( \LogicException $e ) { }

						$this->mapToDatabase( intval( $database->id ) );
					}
				}
				else
				{
					/* Nope, not database tag spotted, assume it has been removed */
					try
					{
						$this->removeDatabaseMap();
					}
					catch( \LogicException $e ) { }
				}
			}
		}
	}
	
	/**
	 * Set Theme
	 *
	 * @return	void
	 */
	public function setTheme()
	{
		if ( $this->theme )
		{
			try
			{
				\IPS\Theme::switchTheme( $this->theme );
			}
			catch ( \Exception $e ) { }
		}
	}

	/**
	 * Once widget ordering has ocurred, post process if required
	 *
	 * @return void
	 */
	public function postWidgetOrderSave()
	{
		/* Check for database changes and update mapping if required */
		$databaseUsed = NULL;

		foreach ( \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=?', $this->id ) ) as $item )
		{
			$pageBlocks   = json_decode( $item['area_widgets'], TRUE );
			$resaveBlock  = NULL;
			foreach( $pageBlocks as $id => $pageBlock )
			{
				if( isset( $pageBlock['app'] ) and $pageBlock['app'] == 'cms' AND $pageBlock['key'] == 'Database' AND ! empty( $pageBlock['configuration']['database'] ) )
				{
					if ( $databaseUsed === NULL )
					{
						$databaseUsed = $pageBlock['configuration']['database'];
					}
					else
					{
						/* Already got a database, so remove this one */
						$resaveBlock = $pageBlocks;
						unset( $resaveBlock[ $id ] );
					}
				}
			}

			if ( $resaveBlock !== NULL )
			{
				\IPS\Db::i()->update( 'cms_page_widget_areas', array( 'area_widgets' => json_encode( $resaveBlock ) ), array( 'area_page_id=? and area_area=?', $this->id, $item['area_area'] ) );
			}
		}

		if ( $databaseUsed === NULL and $this->type === 'html' )
		{
			$databaseUsed = $this->getDatabaseIdFromHtml();
		}

		if ( $databaseUsed !== NULL )
		{
			$this->mapToDatabase( intval( $databaseUsed ) );
		}
		else
		{
			try
			{
				$this->removeDatabaseMap();
			}
			catch( \LogicException $e ) { }
		}
	}

	/**
	 * Map this database to a specific page
	 *
	 * @param   int $databaseId Page ID
	 * @return  boolean
	 * @throws  \LogicException
	 */
	public function mapToDatabase( $databaseId )
	{
		try
		{
			/* is this page already in use */
			$database = \IPS\cms\Databases::load( $this->id, 'database_page_id' );

			if ( $database->id === $databaseId )
			{
				/* Nothing to update as this page is mapped to this database */
				return TRUE;
			}
			else
			{
				/* We're using another DB on this page */
				throw new \LogicException('cms_err_db_already_on_page');
			}
		}
		catch( \OutOfRangeException $e )
		{
			/* We didn't load a database based on this page, so make sure the database we want isn't being used elsewhere */
			$database = \IPS\cms\Databases::load( $databaseId );

			if ( $database->page_id > 0 )
			{
				/* We're using another DB on this page */
				throw new \LogicException('cms_err_db_in_use_other_page');
			}
			else
			{
				/* Ok here as this DB is not in use, and this page doesn't have a DB in use */
				$database->page_id = $this->id;
				$database->save();

				/* Restore content in the search index */
				\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\cms\Records' . $database->id ) );
			}

			return TRUE;
		}
	}

	/**
	 * Removes all mapped DBs for this page
	 *
	 * @return void
	 */
	public function removeDatabaseMap()
	{
		try
		{
			$database = \IPS\cms\Databases::load( $this->id, 'database_page_id' );
			$database->page_id = 0;
			$database->save();

			/* Remove from search */
			\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\cms\Records' . $database->id );
		}
		catch( \OutOfRangeException $ex )
		{
			/* Page was never mapped */
			throw new \LogicException('cms_err_db_page_never_used');
		}
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		try
		{
			$this->removeDatabaseMap();
		}
		catch( \LogicException $e )
		{

		}
		
		$delete = $this->getMenuItemIds();
		
		if ( count( $delete ) )
		{
			\IPS\Db::i()->delete( 'core_menu', \IPS\Db::i()->in( 'id', $delete ) );
		}
		
		unset( \IPS\Data\Store::i()->frontNavigation );

		return parent::delete();
	}
	
	/**
	 * Returns core_menu ids for all menu items associated with this page
	 *
	 * @return array
	 */
	public function getMenuItemIds()
	{
		$items = array();
		foreach( \IPS\Db::i()->select( '*', 'core_menu', array( 'app=? AND extension=?', 'cms', 'Pages' ) ) as $item )
		{
			$json = json_decode( $item['config'], TRUE );
			
			if ( isset( $json['menu_content_page'] ) )
			{
				if ( $json['menu_content_page'] == $this->id )
				{
					$items[] = $item['id'];
				}
			}
		}
		
		return $items;
	}

	/**
	 * Set the permission index permissions
	 *
	 * @param	array	$insert	Permission data to insert
	 * @param	object	\IPS\Helpers\Form\Matrix
	 * @return  void
	 */
	public function setPermissions( $insert, \IPS\Helpers\Form\Matrix $matrix )
	{
		parent::setPermissions( $insert, $matrix );
		static::buildPageUrlStore();
		
		/* Update perms if we have a child database */
		try
		{
			$database = \IPS\cms\Databases::load( $this->id, 'database_page_id' );

			foreach( \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_database_id=?', $database->id ) ) as $cat )
			{
				$class    = '\IPS\cms\Categories' . $database->id;
				$category = $class::constructFromData( $cat );

				\IPS\Content\Search\Index::i()->massUpdate( 'IPS\cms\Records' . $database->id, $category->_id, NULL, $category->readPermissionMergeWithPage() );
				\IPS\Content\Search\Index::i()->massUpdate( 'IPS\cms\Records\Comment' . $database->id, $category->_id, NULL, $category->readPermissionMergeWithPage() );
				\IPS\Content\Search\Index::i()->massUpdate( 'IPS\cms\Records\Review' . $database->id, $category->_id, NULL, $category->readPermissionMergeWithPage() );
			}
		}
		catch( \Exception $e ) { }
	}

	/**
	 * Save data
	 *
	 * @return void
	 */
	public function save()
	{
		if ( $this->id )
		{
			static::deleteCompiled( $this->id );
		}
		
		parent::save();
		
		static::buildPageUrlStore();
	}
		
	/**
	 * Get sortable name
	 *
	 * @return	string
	 */
	public function getSortableName()
	{
		return $this->seo_name;
	}

	/**
	 * Set default
	 *
	 * @return void
	 */
	public function setAsDefault()
	{
		\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 0 ), array( 'page_folder_id=?', $this->folder_id ) );
		\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 1 ), array( 'page_id=?', $this->id ) );
		
		static::buildPageUrlStore();
	}
	
	/**
	 * Resets a folder path
	 *
	 * @param	string	$path	Path to reset
	 * @return	void
	 */
	public function setFullPath( $path )
	{
		$this->full_path = trim( $path . '/' . $this->seo_name, '/' );
		$this->save();
	}

	/**
	 * Displays a page
	 *
	 * @param	int		$httpStatusCode	HTTP Status Code
	 * @param	array	$httpHeaders	Additional HTTP Headers
	 * @throws \ErrorException
	 * @return  void
	 */
	public function output( $title=NULL, $httpStatusCode=NULL, $httpHeaders=NULL )
	{
		$includes = $this->getIncludes();

		if ( isset( $includes['js'] ) and is_array( $includes['js'] ) )
		{
			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, array_values( $includes['js'] ) );
		}

		/* Display */
		if ( $this->ipb_wrapper or $this->type === 'builder' )
		{
			$this->setTheme();
			$nav = array();

			\IPS\Output::i()->title  = $this->getHtmlTitle();

			/* This has to be done after setTheme(), otherwise \IPS\Theme::switchTheme() can wipe out CSS includes */
			if ( isset( $includes['css'] ) and is_array( $includes['css'] ) )
			{
				\IPS\Output::i()->cssFiles  = array_merge( \IPS\Output::i()->cssFiles, array_values( $includes['css'] ) );
			}

			if ( $this->type === 'builder' )
			{
				list( $group, $name, $key ) = explode( '__', $this->template );
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('pages')->globalWrap( $nav, \IPS\cms\Theme::i()->getTemplate($group, 'cms', 'page')->$name( $this, $this->getWidgets() ), $this );
			}
			else
			{
				/* Populate \IPS\Output::i()->sidebar['widgets'] sidebar/header/footer widgets */
				$this->getWidgets();

				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'pages', 'cms' )->globalWrap( $nav, $this->getHtmlContent(), $this );
			}

			\IPS\Output::i()->sidebar['enabled'] = true;

			/* Set the meta tags, but do not reset them if they are already set - articles can define custom meta tags and this code
				overwrites the ones set by articles if we don't verify they aren't set first */
			if ( $this->meta_keywords AND ( !isset( \IPS\Output::i()->metaTags['keywords'] ) OR !\IPS\Output::i()->metaTags['keywords'] ) )
			{
				\IPS\Output::i()->metaTags['keywords'] = $this->meta_keywords;
			}

			if ( $this->meta_description AND ( !isset( \IPS\Output::i()->metaTags['description'] ) OR !\IPS\Output::i()->metaTags['description'] ) )
			{
				\IPS\Output::i()->metaTags['description'] = $this->meta_description;
			}

			/* Can only disable sidebar if HTML page */
			if ( ! $this->show_sidebar and $this->type === 'html' )
			{
				\IPS\Output::i()->sidebar['enabled'] = false;
			}

			if ( isset( \IPS\Settings::i()->cms_error_page ) and \IPS\Settings::i()->cms_error_page and \IPS\Settings::i()->cms_error_page == $this->id )
			{
				\IPS\Output::i()->sidebar['enabled'] = false;
			}

			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'pages/page.css', 'cms', 'front' ) );

			if ( ! ( \IPS\Application::load('cms')->default AND ! $this->folder_id AND $this->default ) )
			{
				\IPS\Output::i()->breadcrumb['module'] = array( $this->url(), $this->_title );
			}

			if ( isset( \IPS\Settings::i()->cms_error_page ) and \IPS\Settings::i()->cms_error_page and \IPS\Settings::i()->cms_error_page == $this->id )
			{
				/* Set the title */
				\IPS\Output::i()->title = ( $title ) ? $title : $this->getHtmlTitle();
				\IPS\Output::i()->output = $this->getHtmlContent();
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( \IPS\Output::i()->title );

				/* Send straight to the output engine */
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), ( $httpStatusCode ? $httpStatusCode : 200 ), 'text/html', ( $httpHeaders ? $httpHeaders : \IPS\Output::i()->httpHeaders ) );
			}
			else
			{
				\IPS\Output::i()->allowDefaultWidgets = FALSE;
				
				/* Let the dispatcher finish off and show page */
				return;
			}
		}
		else
		{
			if ( isset( $includes['css'] ) and is_array( $includes['css'] ) )
			{
				\IPS\Output::i()->cssFiles  = array_merge( \IPS\Output::i()->cssFiles, array_values( $includes['css'] ) );
			}
			
			if ( $this->meta_keywords AND ( !isset( \IPS\Output::i()->metaTags['keywords'] ) OR !\IPS\Output::i()->metaTags['keywords'] ) )
			{
				\IPS\Output::i()->metaTags['keywords'] = $this->meta_keywords;
			}

			if ( $this->meta_description AND ( !isset( \IPS\Output::i()->metaTags['description'] ) OR !\IPS\Output::i()->metaTags['description'] ) )
			{
				\IPS\Output::i()->metaTags['description'] = $this->meta_description;
			}
			
			/* Meta tags */
			\IPS\Output::i()->buildMetaTags();
			
			if ( $this->wrapper_template and $this->wrapper_template !== '_none_' and ! \IPS\Request::i()->isAjax() )
			{
				try
				{
					list( $group, $name, $key ) = explode( '__', $this->wrapper_template );
					\IPS\Output::i()->sendOutput( \IPS\cms\Theme::i()->getTemplate($group, 'cms', 'page')->$name( $this->getHtmlContent(), $this->getHtmlTitle() ), 200, $this->getContentType(), \IPS\Output::i()->httpHeaders );
				}
				catch( \OutOfRangeException $e )
				{

				}
			}

			/* Set the title */
			\IPS\Output::i()->title = ( $title ) ? $title : $this->getHtmlTitle();
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( \IPS\Output::i()->title );

			/* Send straight to the output engine */
			\IPS\Output::i()->sendOutput( $this->getHtmlContent(), ( $httpStatusCode ? $httpStatusCode : 200 ), $this->getContentType(), ( $httpHeaders ? $httpHeaders : \IPS\Output::i()->httpHeaders ) );
		}
	}
}