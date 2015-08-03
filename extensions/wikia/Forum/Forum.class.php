<?php

/**
 * Forum
 * @author Kyle Florence, Saipetch Kongkatong, Tomasz Odrobny
 */
class Forum extends Walls {

	const ACTIVE_DAYS = 7;
	const BOARD_MAX_NUMBER = 50;

	/**
	 * @var string Name of the Wikia bot used to perform Board autocreates
	 */
	const AUTOCREATE_USER = 'Wikia';

	/**
	 * @var string
	 * The autocreate user should use an internal IP
	 * Otherwise, the automated edits will clutter CheckUser queries
	 */
	const AUTOCREATE_USER_IP = '127.0.0.1';
	//controlling from outside if use can edit/create/delete board page
	static $allowToEditBoard = false;

	/**
	 * @desc Min and max lengths of fields
	 * @var array
	 */
	private $fieldsLengths = [
		'title' => [ 'min' => 4, 'max' => 40 ],
		'desc' => [ 'min' => 4, 'max' => 255 ],
	];

	const LEN_OK = 0;
	const LEN_TOO_BIG_ERR = -1;
	const LEN_TOO_SMALL_ERR = -2;

	public function getBoardList($db = DB_SLAVE) {
		$boardTitles = $this->getListTitles( $db, NS_WIKIA_FORUM_BOARD );
		$titlesBatch = new TitleBatch($boardTitles);
		$orderIndexes = $titlesBatch->getWikiaProperties(WPP_WALL_ORDER_INDEX,$db);

		$boards = array();
		/** @var $title Title */
		foreach($boardTitles as $title) {
			/** @var $board ForumBoard */
			$board = ForumBoard::newFromTitle($title);
			$title = $board->getTitle();
			$id = $title->getArticleID();

			$boardInfo = $board->getBoardInfo();
			$boardInfo['id'] = $title->getArticleID();
			$boardInfo['name'] = $title->getText();
			$boardInfo['description'] = $board->getDescriptionWithoutTemplates();
			$boardInfo['url'] = $title->getFullURL();
			$orderIndex = $orderIndexes[$id];
			$boards[$orderIndex] = $boardInfo;
		}

		krsort($boards);

		return $boards;
	}

	/**
	 * get count of boards
	 * @return int board count
	 */
	public function getBoardCount( $db = DB_SLAVE ) {

		$dbw = wfGetDB( $db );

		// get board list
		$result = (int)$dbw->selectField(
			array( 'page' ),
			array( 'count(*) as cnt' ),
			array( 'page_namespace' => NS_WIKIA_FORUM_BOARD ),
			__METHOD__,
			array()
		);

		return $result['cnt'];
	}

	/**
	 * get total threads excluding deleted and removed threads
	 * @param integer $days
	 * @return integer $totalThreads
	 */
	public function getTotalThreads( $days = 0 ) {

		$memKey = $this->getMemKeyTotalThreads( $days );
		$totalThreads = $this->wg->Memc->get( $memKey );
		if ( $totalThreads === false ) {
			$db = wfGetDB( DB_SLAVE );

			$sqlWhere = array(
				'parent_comment_id' => 0,
				'archived' => 0,
				'deleted' => 0,
				'removed' => 0,
				'page_namespace' => NS_WIKIA_FORUM_BOARD_THREAD
			);

			// active threads
			if ( !empty( $days ) ) {
				$sqlWhere[] = "last_touched > curdate() - interval $days day";
			}

			$row = $db->selectRow(
				array( 'comments_index', 'page' ),
				array( 'count(*) cnt' ),
				$sqlWhere,
				__METHOD__,
				array(),
				array( 'page' => array( 'LEFT JOIN', array( 'page_id=comment_id' ) ) )
			);

			$totalThreads = 0;
			if ( $row ) {
				$totalThreads = intval( $row->cnt );
			}
			$this->wg->Memc->set( $memKey, $totalThreads, 60 * 60 * 12 );
		}


		return $totalThreads;
	}

	// get the number of threads that have had a new post/reply in the last 7 days
	public function getTotalActiveThreads() {
		return $this->getTotalThreads( self::ACTIVE_DAYS );
	}

	/**
	 * get memcache key for total threads
	 * @param integer $days
	 * @return string
	 */
	protected function getMemKeyTotalThreads( $days ) {
		return wfMemcKey( 'forum_total_threads_' . $days );
	}

	// clear cache for total threads
	public function clearCacheTotalThreads( $days = 0 ) {
		$memKey = $this->getMemKeyTotalThreads( $days );
		$this->wg->Memc->delete( $memKey );
	}

	// clear cache for total active threads
	public function clearCacheTotalActiveThreads() {
		$this->clearCacheTotalThreads( self::ACTIVE_DAYS );
	}

	public function hasAtLeast( $ns, $count ) {

		$out = WikiaDataAccess::cache( wfMemcKey( 'Forum_hasAtLeast', $ns, $count ), 24 * 60 * 60/* one day */, function() use ( $ns, $count ) {
			$db = wfGetDB( DB_MASTER );
			// check if there is more then 5 forum pages (5 is number of forum pages from starter)
			// limit 6 is faster solution then count(*) and the compare in php
			$result = $db->select( array( 'page' ), array( 'page_id' ), array( 'page_namespace' => $ns ), __METHOD__, array( 'LIMIT' => $count + 1 ) );

			$rowCount = $db->numRows( $result );
			//string value is a work around for false value problem in memc
			if ( $rowCount > $count ) {
				return "YES";
			} else {
				return "NO";
			}
		} );

		return $out == "YES";
	}

	public function haveOldForums() {
		return $this->hasAtLeast( NS_FORUM, 5 );
	}

	public function swapBoards( $boardId1, $boardId2 ) {
		$orderId1 = wfGetWikiaPageProp( WPP_WALL_ORDER_INDEX, $boardId1 );
		$orderId2 = wfGetWikiaPageProp( WPP_WALL_ORDER_INDEX, $boardId2 );

		if ( empty( $orderId1 ) || empty( $orderId2 ) ) {
			return false;
		}

		wfSetWikiaPageProp( WPP_WALL_ORDER_INDEX, $boardId1, $orderId2 );
		wfSetWikiaPageProp( WPP_WALL_ORDER_INDEX, $boardId2, $orderId1 );
	}

	/**
	 * Backward compatibility for forums created before the order functionality
	 */

	public function createOrders() {
		$dbw = wfGetDB( DB_MASTER );

		// get board list
		$result = $dbw->select(
			array( 'page' ),
			array( 'page_id, page_title' ),
			array( 'page_namespace' => NS_WIKIA_FORUM_BOARD ),
			__METHOD__, array( 'ORDER BY' => 'page_title' )
		);

		while ( $row = $dbw->fetchObject( $result ) ) {
			wfSetWikiaPageProp( WPP_WALL_ORDER_INDEX, $row->page_id, $row->page_id );
		}
	}

	/**
	 * Creates a board titled $titletext
	 *
	 * @param string $titletext Title of the board to be created
	 * @param string $body Board description
	 * @param bool $bot Whether to perform the edit as bot
	 * @return Status status object indicating edit success
	 */
	public function createBoard( $titletext, $body, $bot = false ) {
		return $this->createOrEditBoard( null, $titletext, $body, $bot );
	}

	/**
	 * Edits an existing forum board
	 *
	 * @param ForumBoard $board ForumBoard instance being edited
	 * @param string $titletext Board title
	 * @param string $body Board description
	 * @param bool $bot Whether to perform the edit as bot
	 * @return Status status object indicating edit success
	 */
	public function editBoard( ForumBoard $board, $titletext, $body, $bot = false ) {
		return $this->createOrEditBoard( $board, $titletext, $body, $bot );
	}

	/**
	 * Create or edit board, if $board = null then we are creating new one
	 * @param ForumBoard|null $board ForumBoard instance being edited or null if creating one
	 * @param string $titletext Board title
	 * @param string $body Board description
	 * @param bool $bot Whether to perform the edit as bot
	 * @return Status status object indicating edit success
	 */
	protected function createOrEditBoard( $board, $titletext, $body, $bot = false ) {
		$id = null;
		if ( !empty( $board ) ) {
			$id = $board->getId();
		}

		if (
			self::LEN_OK !== $this->validateLength( $titletext, 'title' ) ||
			self::LEN_OK !== $this->validateLength( $body, 'desc' )
		) {
			return false;
		}

		Forum::$allowToEditBoard = true;

		if ( $id == null ) {
			$title = Title::newFromText( $titletext, NS_WIKIA_FORUM_BOARD );
		} else {
			$title = Title::newFromId( $id, Title::GAID_FOR_UPDATE );
			$nt = Title::newFromText( $titletext, NS_WIKIA_FORUM_BOARD );
			$title->moveTo( $nt, true, '', false );
			$title = $nt;
		}

		$page = new WikiPage( $title );
		$status = null;
		if ( $bot ) {
			// The edit should be performed by the bot - Board autocreation
			// Set internal IP for bot user to avoid cluttering CU
			$this->wg->Request->setIP( Forum::AUTOCREATE_USER_IP );
			$status = $page->doEdit(
				$body,
				wfMessage( 'forum-board-edit-summary' )->inContentLanguage()->text(),
				EDIT_FORCE_BOT | EDIT_MINOR | EDIT_SUPPRESS_RC,
				false,
				User::newFromName( Forum::AUTOCREATE_USER )
			);
		} else {
			// The action should not be performed by the bot
			// This means an existing board is being edited
			$status = $page->doEdit(
				$body,
				wfMessage( 'forum-board-edit-summary' )->inContentLanguage()->text()
			);
		}

		if ( $id == null ) {
			$title = Title::newFromText( $titletext, NS_WIKIA_FORUM_BOARD );
			if ( !empty( $title ) ) {
				wfSetWikiaPageProp( WPP_WALL_ORDER_INDEX, $title->getArticleId(), $title->getArticleId() );
			}
		}

		Forum::$allowToEditBoard = false;

		return $status;
	}

	/**
	 * @desc Returns length limit of a field; if not set in Forum::$fieldsLengths returns 0
	 *
	 * @param String $type one of: 'min' or 'max'
	 * @param String $field fields defined in Forum::$fieldsLengths
	 *
	 * @return int
	 */
	public function getLengthLimits( $type, $field ) {
		return ( isset( $this->fieldsLengths[$field] ) && isset( $this->fieldsLengths[$field][$type] ) ) ?
			(int) $this->fieldsLengths[$field][$type] :
			0;
	}

	/**
	 * @desc Returns Forum::LEN_OK, Forum::LEN_TOO_SMALL_ERR or Forum::LEN_TOO_BIG_ERR depends if the length is valid
	 *
	 * @param String $input data to be validated
	 * @param String $field field with defined length's limits in Forum::$fieldsLengths array
	 *
	 * @return string
	 */
	public function validateLength( $input, $field ) {
		$min = $this->getLengthLimits( 'min', $field );
		$max = $this->getLengthLimits( 'max', $field );
		$out = self::LEN_OK;

		if( mb_strlen( $input ) < $min ) {
			$out = self::LEN_TOO_SMALL_ERR;
		} else if( mb_strlen( $input ) > $max ) {
			$out = self::LEN_TOO_BIG_ERR;
		}

		return $out;
	}

	/**
	 * Deletes an existing forum board
	 * @param ForumBoard $board ForumBoard instance to be deleted
	 */
	public function deleteBoard( ForumBoard $board ) {
		Forum::$allowToEditBoard = true;

		$page = new WikiPage( $board->getTitle() );
		$page->doDeleteArticle( '', true );

		Forum::$allowToEditBoard = false;
	}

	public function createDefaultBoard() {
		if ( !$this->hasAtLeast( NS_WIKIA_FORUM_BOARD, 0 ) ) {
			WikiaDataAccess::cachePurge( wfMemcKey( 'Forum_hasAtLeast', NS_WIKIA_FORUM_BOARD, 0 ) );
			for ( $i = 1; $i <= 5; $i++ ) {
				$body = wfMessage( 'forum-autoboard-body-' . $i, $this->wg->Sitename )->inContentLanguage()->text();
				$title = wfMessage( 'forum-autoboard-title-' . $i, $this->wg->Sitename )->inContentLanguage()->text();

				$this->createBoard( $title, $body, true );
			}
			return true;
		}

		return false;
	}

}
