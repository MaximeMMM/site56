<?php
/**
 * @brief		badges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		10 Mar 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * badges
 */
class _badges extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'overview_manage' );
		parent::execute();
	}

	/**
	 * Points earned activity chart
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$chart	= new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( 'app=core&module=stats&controller=badges' ), 'core_member_badges', 'datetime', '', array(
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		), 'AreaChart', 'daily' );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_badges_title');
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->enableHourly	= FALSE;

		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('stats_member_badges'), 'number', 'COUNT(*)', TRUE );

		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_badges_title');
		$chart->availableTypes	= array( 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->showIntervals	= TRUE;

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_badges');
		\IPS\Output::i()->output	= (string) $chart;
	}
}