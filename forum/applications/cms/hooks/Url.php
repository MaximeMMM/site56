//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Url extends _HOOK_CLASS_
{

	protected static $gatewayDir = NULL;

	/**
	 * Set URL
	 *
	 * @param	string	$url		The URL
	 * @param	bool	$internal	Is internal? (NULL to auto-detect)
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	protected function setUrl( $url, $internal=NULL )
	{
		parent::setUrl( $url, $internal );

		/* Check our root URL if necessary to determine if this is an internal/friendly URL */
		if ( $internal === NULL AND $this->isInternal === FALSE AND \IPS\Settings::i()->cms_root_page_url )
		{
			$this->isInternal = ( mb_substr( str_replace( array( 'https://', 'http://' ), '//', $this->url ), 0, mb_strlen( str_replace( array( 'https://', 'http://' ), '//', \IPS\Settings::i()->cms_root_page_url ) ) ) === str_replace( array( 'https://', 'http://' ), '//', \IPS\Settings::i()->cms_root_page_url ) );

			/* Then recheck if it is a friendly URL */
			if ( $this->isInternal and ( mb_strpos( $url, 'index.php' ) === FALSE or mb_substr( str_replace( 'https://', 'http://', $this->url ), str_replace( 'https://', 'http://', mb_strlen( \IPS\Settings::i()->cms_root_page_url ) ), 11 ) === 'index.php?/' ) )
			{	
				$this->isFriendly = TRUE;
			}
		}
	}

	/**
	 * Get friendly URL data
	 *
	 * @param	bool	$verify		If TRUE, will check URL uses correct SEO title
	 * @return	array	Parameters
	 * @throws	\OutOfRangeException	Invalid URL
	 * @throws	\DomainException		URL does not have correct SEO title (exception message will contain correct URL)
	 */
	public function getFriendlyUrlData( $verify=false )
	{
		try
		{
			return call_user_func_array( 'parent::getFriendlyUrlData', func_get_args() );
		}
		catch( \OutOfRangeException $ex )
		{
			/* Pass to IP.Content to handle */
			$baseUrl = parse_url( \IPS\Settings::i()->base_url );
			
			if ( \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				$query = ( isset( $this->data['path'] ) ? $this->data['path'] : '' );
			}
			else
			{
				if ( isset( $this->data['path'] ) and mb_strpos( $this->data['path'], 'index.php?' ) )
				{
					$query = ( isset( $this->data['query'] ) ? ltrim( $this->data['query'], '/' )  : '' );
				}		
				else
				{
					$query = '';
				}	
			}	

			/* Because pages can have "nothing" in the URL for SEO reasons, we need to capture query strings first before sending to the parent class */
			$queryString	= '';
			if( mb_strpos( $query, '&' ) !== FALSE )
			{
				$queryString	= ltrim( mb_substr( $query, mb_strpos( $query, '&' ) ), '&' );
				$query			= mb_substr( $query, 0, mb_strpos( $query, '&' ) );
			}

			$set = array();
		
			/* Need to remember the template we use for FURL verification */
			$usedTemplate	= NULL;

			/* Get furl definition */
			$furlDefinition = array('cms_page' => array(
				'friendly' => '{@path}',
				'real'     => 'app=cms&module=pages&controller=page'
			) );

			$query = preg_replace( '#^(' . preg_quote( rtrim( $baseUrl['path'], '/' ), '#' ) . ')/(index.php)?(?:(?:\?/|\?))?(.+?)?$#', '$3', $query );		
			
			$this->examineFurl( $furlDefinition, $query, $set, $usedTemplate );

			/* Now set the query string back */
			if( $queryString )
			{
				$queryString = explode( '&', $queryString );

				foreach( $queryString as $k => $v )
				{
					$set[ $k ] = $v;
				}
			}

			if ( count( $set ) )
			{
				if ( ! \IPS\Settings::i()->htaccess_mod_rewrite and isset( $set['path'] ) )
				{
					/* Is the path URL encoded?
					   @note this only affects when not using mod_rewrite. We specifically do not urldecode the query string in \IPS\Request::i()->url()
					   for other reasons, presumaly, hence this code here as it only affects Pages */
					if ( str_replace( '%2F', '/', urlencode( urldecode( $set['path'] ) ) ) === $set['path'] )
					{
						$set['path'] = urldecode( $set['path'] );
					} 
				}
				return $set;
			}
			else
			{			
				throw new \OutOfRangeException();
			}
		}
	}

	/**
	 * Make friendly
	 *
	 * @param	    string|array	        $seoTemplate        	The key for making this a friendly URL; or a manual FURL definition
	 * @param       string|array            $seoTitles              The title(s) needed for the friendly URL
	 * @param       bool                    $forceHttps             Force HTTPS
	 * @return      parent
	 */
	public function makeFriendly( $seoTemplate, $seoTitles, $forceHttps=FALSE )
	{
		$called = $seoTemplate;
		if ( $seoTemplate === 'content_page_path' )
		{
			$seoTemplate = array(
				'friendly' => '{@path}',
				'real'     => 'app=cms&module=pages&controller=page'
			);
		}

		parent::makeFriendly( $seoTemplate, $seoTitles, $forceHttps );
		
		if ( $called === 'content_page_path' and \IPS\Settings::i()->cms_use_different_gateway )
		{
			if ( static::$gatewayDir === NULL )
			{
				static::$gatewayDir = preg_replace( '#^/?(.+?)/?$#', '\1', str_replace( \IPS\Settings::i()->cms_root_page_url, '', \IPS\Settings::i()->base_url ) );
			}
			
			/* If $gatewayDir is empty, we do not want to do this as it will remove all slashes from the entire path */
			if ( !empty( static::$gatewayDir ) )
			{
				$this->data['path'] = str_replace( '/' . static::$gatewayDir, '', $this->data['path'] );
			}
			
			$this->url = str_replace( static::baseUrl(), \IPS\Settings::i()->cms_root_page_url, $this->url );
		}
	}
}