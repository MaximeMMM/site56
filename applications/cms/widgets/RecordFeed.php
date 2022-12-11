<?php
/**
 * @brief		RecordFeed Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		24 Nov 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * RecordFeed Widget
 */
class _RecordFeed extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'RecordFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Class
	 */
	protected static $class = 'IPS\cms\Records';

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
 		if ( $form === null )
		{
	 		$form = new \IPS\Helpers\Form;
 		}

		$databases = array();
		$database  = NULL;
		foreach( \IPS\cms\Databases::databases() as $obj )
		{
			if ( $obj->page_id )
			{
				$databases[ $obj->_id ] = $obj->_title;
			}
			
			if ( ( isset( \IPS\Request::i()->cms_rf_database ) AND $obj->id == \IPS\Request::i()->cms_rf_database ) OR ( ( isset( $this->configuration['cms_rf_database'] ) AND $obj->id == $this->configuration['cms_rf_database'] ) ) )
			{
				$database = $obj;
				static::$class = '\IPS\cms\Records' . $database->id;
			}
		}

		if ( isset( $this->configuration['cms_rf_database'] ) )
		{
			$form->addDummy( 'cms_rf_database', $database->_title, \IPS\Member::loggedIn()->language()->addToStack( 'cms_rf_database_description' ) );
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\Select( 'cms_rf_database', 0, FALSE, array(
				'disabled' => false,
				'options'  => $databases
			) ) );
		}

		$form = parent::configuration( $form );

		/* Tags */
		if ( $database and $database->tags_enabled )
		{
			$options = array( 'autocomplete' => array( 'unique' => TRUE, 'source' => NULL, 'freeChoice' => TRUE ) );

			if ( \IPS\Settings::i()->tags_force_lower )
			{
				$options['autocomplete']['forceLower'] = TRUE;
			}

			if ( \IPS\Settings::i()->tags_clean )
			{
				$options['autocomplete']['filterProfanity'] = TRUE;
			}

			$options['autocomplete']['prefix'] = FALSE;

			$form->add( new \IPS\Helpers\Form\Text( 'widget_feed_tags', ( isset( $this->configuration['widget_feed_tags'] ) ? $this->configuration['widget_feed_tags'] : array( 'tags' => NULL ) ), FALSE, $options ) );
		}
		
		if ( $database )
		{
			\IPS\Member::loggedIn()->language()->words['widget_feed_container_content_db_lang_su_' . $database->id ] = \IPS\Member::loggedIn()->language()->addToStack('widget_feed_container_cms');
		}
		
		return $form;
 	} 
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	static::$class = '\IPS\cms\Records' . $values['cms_rf_database'];
	 	return parent::preConfig( $values );
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if( isset( $this->configuration['cms_rf_database'] ) )
		{
			try
			{
				$database = \IPS\cms\Databases::load($this->configuration['cms_rf_database']);
				static::$class = '\IPS\cms\Records' . $database->id;
				
				if ( ! $database->page_id )
				{
					throw new \OutOfRangeException;
				}
			}
			catch ( \OutOfRangeException $e )
			{
				return '';
			}
		}
		else
		{
			return '';
		}

		return parent::render();
	}
}