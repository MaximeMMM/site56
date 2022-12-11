<?php
/**
 * @brief		Poll Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 Jan 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Poll Model
 */
class _Poll extends \IPS\Patterns\ActiveRecord implements \SplSubject
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_polls';
	
	/**
	 * @brief	Database ID Column
	 */
	public static $databaseColumnId = 'pid';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Display template
	 */
	public $displayTemplate;
	
	/**
	 * @brief	URL to use instead of \IPS\Request::i()->url()
	 */
	public $url;
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->start_date = new \IPS\DateTime;
		$this->choices = array();
	}
	
	/**
	 * Set start date
	 *
	 * @param	\IPS\DateTime	$value	Value
	 * @return	void
	 */
	public function set_start_date( \IPS\DateTime $value )
	{
		$this->_data['start_date'] = $value->getTimestamp();
	}
	
	/**
	 * Get start date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_start_date()
	{
		return \IPS\DateTime::ts( $this->_data['start_date'] );
	}
	
	/**
	 * Set choices
	 *
	 * @param	array	$value	Value
	 * @return	void
	 */
	public function set_choices( array $value )
	{
		$this->_data['choices'] = json_encode( $value );
	}
	
	/**
	 * Get choices
	 *
	 * @return	array
	 */
	public function get_choices()
	{
		return json_decode( $this->_data['choices'], TRUE );
	}
	
	/**
	 * Get author
	 *
	 * @return	\IPS\Member
	 */
	public function author()
	{
		return \IPS\Member::load( $this->starter_id );
	}
	
	/**
	 * Set Choices
	 *
	 * @param	array	$data			Values from form
	 * @param	bool	$allowPollOnly	Allow poll-only?
	 * @return	void
	 */
	public function setDataFromForm( $data, $allowPollOnly )
	{
		if ( $data['title'] )
		{
			$this->poll_question = $data['title'];
		}
		else
		{
			/* If no title specified, just use the one from the first title */
			$questions = $data['questions'];
			$firstQuestion = array_shift( $questions );
			$this->poll_question = $firstQuestion['title'];
		}
		
		$this->poll_only = ( \IPS\Settings::i()->ipb_poll_only and $allowPollOnly and isset( $data['poll_only'] ) );
		$this->poll_view_voters = ( \IPS\Settings::i()->poll_allow_public and isset( $data['public'] ) );
		
		$this->votes = 0;
		$choices = array();
		$existing = $this->choices;
		foreach ( $data['questions'] as $k => $questionData )
		{
			if ( $questionData['title'] )
			{
				$choices[ $k ] = array(
					'question'	=> $questionData['title'],
					'multi'		=> intval( isset( $questionData['multichoice'] ) ),
					'choice'	=> array(),
					'votes'		=> array(),
				);
				
				foreach ( $questionData['answers'] as $answerId => $answerData )
				{
					$answerData['value'] = strip_tags( \IPS\Text\Parser::parseStatic( $answerData['value'], true, null, null, true, true, true, function( $config ) {
							$config->set( 'HTML.AllowedElements', 'a,img' );
					} ), '<a><img>' );

					/* AllowedElements strips <___base_url___> */
					$answerData['value'] = preg_replace( '#(<img\s+?src=[\'"])___base_url___/#', '\1<___base_url___>/', $answerData['value'] );

					$count = isset( $existing[ $k ]['votes'][ $answerId ] ) ? $existing[ $k ]['votes'][ $answerId ] : 0;
					if ( trim( $answerData['value'] ) !== "" )
					{
						$choices[ $k ]['choice'][ $answerId ] = $answerData['value'];

						if ( isset( $answerData['count'] ) and \IPS\Member::loggedIn()->modPermission('can_edit_poll_votes') )
						{
							$count = ( $answerData['count'] > 0 ? intval( $answerData['count'] ) : 0 );
						}
					}

					$choices[ $k ]['votes'][ $answerId ] = $count;
				}
			}
		}
		$this->choices = $choices;

		/* Set the number of voters */
		$this->recountVotes();
	}
	
	/**
	 * Member can vote?
	 *
	 * @param	\IPS\Member|NULL	$member	Member or NULL for currently logged in member
	 * @return	void
	 */
	public function canVote( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		if ( !$member->group['g_vote_polls'] )
		{
			return FALSE;
		}
		
		if ( !\IPS\Settings::i()->allow_creator_vote and $member == $this->author() )
		{
			return FALSE;
		}
		
		if ( !\IPS\Settings::i()->poll_allow_vdelete and $this->getVote( $member ) )
		{
			return FALSE;
		}
		
		if ( $this->poll_closed )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Member can see voters?
	 *
	 * @param	\IPS\Member|NULL	$member	Member or NULL for currently logged in member
	 * @return	void
	 */
	public function canSeeVoters( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_see_poll_voters') or ( \IPS\Settings::i()->poll_allow_public and $this->poll_view_voters );
	}

	/**
	 * Add Vote
	 *
	 * @param	\IPS\Poll\Vote	$vote	Vote
	 * @return	void
	 */
	public function addVote( \IPS\Poll\Vote $vote, \IPS\Member $member = NULL )
	{
		/* Delete existing vote */
		if ( $existingVote = $this->getVote( $member ) )
		{
			$existingVote->delete();
		}

		/* Add new vote */
		$vote->poll = $this;
		$vote->save();
		$this->notify();

		/* Log */
		if ( $vote->member_choices !== NULL )
		{
			$this->votes = 0;

			$pollChoices = $this->choices;
			foreach ( $vote->member_choices as $key => $value )
			{
				if ( is_array( $value ) )
				{
					foreach ( $value as $k => $v )
					{
						$pollChoices[ $key ]['votes'][ $v ]++;
					}
				}
				else
				{
					if( $value )
					{
						$pollChoices[ $key ]['votes'][$value]++;
					}
				}
			}
			$this->choices = $pollChoices;
			$this->recountVotes();
			$this->save();
		}
	}

	/**
	 * Recount the total votes for the poll.
	 * A vote is a single "submit" regardless of how many questions there are.
	 *
	 * @note	This requires that $this->choices have the votes stored in it
	 * @note	This method does not call save()...be sure you do that yourself
	 * @return	void
	 */
	public function recountVotes()
	{
		/* Reset the cached count */
		$this->votes = \IPS\Db::i()->select( 'COUNT(*)', 'core_voters', array( 'poll=?', $this->pid ) )->first();
	}
	
	/**
	 * Get Votes
	 *
	 * @param	int|NULL	$question	If you only want to retreive votes where users voted a particular answer for a particular question, provide the question ID
	 * @param	int|NULL	$option		If you only want to retreive votes where users voted a particular answer for a particular question, provide the option ID
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function getVotes( $question=NULL, $option=NULL )
	{
		$iterator = \IPS\Db::i()->select( '*', 'core_voters', array( 'poll=?', $this->pid ) );
		$iterator = new \IPS\Patterns\ActiveRecordIterator( $iterator, 'IPS\Poll\Vote' );
		
		if ( $question !== NULL )
		{
			$iterator = new \IPS\Poll\Iterator( $iterator, $question, $option );
		}
		
		return $iterator;
	}
	
	/**
	 * Get Vote
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Poll\Vote|NULL
	 */
	public function getVote( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		try
		{
			return \IPS\Poll\Vote::load( $member->member_id, 'member_id', array( 'poll=?', $this->pid ) );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Show Poll
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			/* Pre IPS4 data can be bad */
            if ( !count( $this->choices ) )
            {
                return '';
            }

            foreach( $this->choices as $id => $question )
			{
				if ( ! isset( $question['votes'] ) or ! isset( $question['choice'] ) )
				{
					return '';
				}
			}
			
			if ( ! $this->displayTemplate )
			{
				$this->displayTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' ), 'poll' );
			}
	
			$output	= call_user_func( $this->displayTemplate, $this, ( $this->url ?: \IPS\Request::i()->url() ) );

			if( \IPS\Request::i()->isAjax() && \IPS\Request::i()->fetchPoll )
			{
				/* If a vote was submitted but we're returning HTML, that means there was an error (probably a choice not selected for
					a question) so we return a 500 error code to make the form submit properly rather than showing "Your vote has been saved" */
				\IPS\Output::i()->sendOutput( $output, ( $this->buildForm() and ! isset( \IPS\Request::i()->viewResults ) ) ? 500 : 200, 'text/html' );
			}

			return $output;
		}
		catch( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
	
	/**
	 * Can view results
	 *
	 * @return boolean
	 */
	public function canViewResults()
	{
		if ( \IPS\Settings::i()->allow_result_view )
		{
			return TRUE;
		}
		else if ( isset( \IPS\Request::i()->nullVote ) and \IPS\Member::loggedIn()->member_id )
		{
			if ( ! $this->getVote() )
			{
				$this->addVote( \IPS\Poll\Vote::fromForm( NULL ) );
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Build Form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function buildForm()
	{
		if ( !$this->canVote() )
		{
			return '';
		}
		
		$form = new \IPS\Helpers\Form('poll', 'save_vote');
		foreach ( $this->choices as $k => $data )
		{
			$class = ( isset( $data['multi'] ) AND $data['multi'] ) ? 'IPS\Helpers\Form\CheckboxSet' : 'IPS\Helpers\Form\Radio';
			$input = new $class( $k, ( isset( $data['multi'] ) AND $data['multi'] ) ? array() : NULL, TRUE, array( 'options' => $data['choice'], 'noDefault' => TRUE ) );

			$input->label = $data['question'];
			$form->add( $input );
		}
		
		if ( $values = $form->values() )
		{
			$this->addVote( \IPS\Poll\Vote::fromForm( $values ) );
			return '';
		}
		
		return $form;
		
	}
	
	/* !SplSubject */
	
	/**
	 * @brief	Observers
	 */
	protected $observers = array();
	
	/**
	 * Attach Observer
	 *
	 * @param	\SplObserver	$observer
	 * @return	void
	 */
	public function attach( \SplObserver $observer )
	{
		$this->observers[] = $observer;
	}
	
	/**
	 * Attach Observer
	 *
	 * @param	\SplObserver	$observer
	 * @return	void
	 */
	public function detach( \SplObserver $observer )
	{
		foreach ( $this->observers as $k => $v )
		{
			if ( $v === $observer )
			{
				unset( $this->observers[ $k ] );
			}
		}
	}
	
	/**
	 * Notify
	 *
	 * @return	void
	 */
	public function notify()
	{
		foreach ( $this->observers as $k => $v )
		{
			$v->update( $this );
		}
	}
	
	/**
	 * Get output for API
	 *
	 * @return	array
	 * @apiresponse	int						id			ID number
	 * @apiresponse	string					title		Title
	 * @apiresponse	int						votes		Number of votes
	 * @apiresponse	[\IPS\Poll\Question]	questions	The questions
	 */
	public function apiOutput()
	{
		$questions = array();
		foreach ( $this->choices as $choice )
		{
			$questions[] = ( new \IPS\Poll\Question( $choice ) )->apiOutput();
		}
		
		return array(
			'id'		=> $this->pid,
			'title'		=> $this->poll_question,
			'votes'		=> $this->votes,
			'questions'	=> $questions
		);
	}
}