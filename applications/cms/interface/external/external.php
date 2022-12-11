<?php
/**
 * @brief		Pages External Block Gateway
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		30 Jun 2015
 * @version		SVN_VERSION_NUMBER
 *
 */

require_once str_replace( 'applications/cms/interface/external/external.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\Front::i();

$id = \IPS\Request::i()->blockid;
$k = \IPS\Request::i()->widgetid;
$block = \IPS\cms\Blocks\Block::display( $id );

\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_external.js', 'cms', 'front' ) );
\IPS\Output::i()->globalControllers[] = 'cms.front.external.communication';
\IPS\Output::i()->sendOutput( \IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $block ) ), 200, 'text/html' );