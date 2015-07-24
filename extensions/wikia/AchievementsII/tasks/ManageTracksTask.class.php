<?php

use Wikia\Tasks\Tasks\BaseTask;
class ManageTracksTask extends BaseTask {

	const ACHIEVEMENTS_TRACK_PREFIX = 'AchievementsII::Track::';
	const ACHIEVEMENTS_TRACK_DB = 'AchTrack';

	/**
	 * @var string
	 */
	private $categoryName;
	/**
	 * @var string
	 */
	private $trackName;

	/**
	 * @var Database
	 */
	private $db;

	/**
	 * @param string $categoryName category title, without namespace prefix
	 */
	public function __construct( $categoryName, $trackName = null ) {
		$this->categoryName = $categoryName;
		$this->trackName = self::ACHIEVEMENTS_TRACK_PREFIX . ( $trackName == null ? $categoryName : $trackName );
		$this->db = wfGetDB( DB_MASTER );
	}

	/**
	 * Adds an achievements track to the current category and its subcategories
	 * Called when a new track is created, or a new category is categorized
	 * into a category that already has an edit track.
	 */
	public function addTrack() {
		$this->debug( 'Adding achievements track for category ' .  $this->categoryName );
		$targets = $this->getSubCategoriesRecursive( $this->categoryName );

		foreach ( $targets as $pageId ) {
			//$this->call( 'setPageProp', $pageId );
			$this->setTrackForCategory( $pageId );
		}
	}

	/**
	 * Removes an achievements track from the wiki.
	 */
	public function removeTrack() {
		$this->debug( 'Removing achievements track ' . $this->trackName );
		( new WikiaSQL() )
			->DELETE( 'page_props' )
			->WHERE( 'pp_propname' )
			->EQUAL_TO( $this->trackName )
			->run( $this->db );

		// purge cache for subcategories
		$subcats = $this->getSubCategoriesRecursive( $this->categoryName );
		$cache = new \Wikia\Cache\AsyncCache();
		foreach ( $subcats as $catId ) {
			$cache->purge( AchAwardingService::getEditTrackCacheKeyForPage( $catId ) );
		}
	}

	/**
	 * Marks the category given by the id as part of the edit track being added
	 * @param int $pageId article id of the category
	 */
	public function setTrackForCategory( $pageId ) {
		( new WikiaSQL() )
			->INSERT( 'page_props' )
			->SET( 'pp_page', $pageId )
			->SET( 'pp_propname', $this->trackName )
			->SET( 'pp_value', self::ACHIEVEMENTS_TRACK_DB )
			->run( $this->db );
	}

	/**
	 * Recursively fetches all page IDs for a given category and its subcategories
	 * @param string $categoryName
	 * @return array Array of page IDs
	 */
	private function getSubCategoriesRecursive( $categoryName ) {
		$category = Category::newFromName( $categoryName );
		$members = $category->getMembers();
		$targetPages = [ $category->getTitle()->getArticleId() ];

		while ( $members->valid() ) {
			$title = $members->current;
			if ( $title->getNamespace() == NS_CATEGORY ) {
				$targetPages = array_merge( $targetPages, $this->getSubCategoriesRecursive( $title->getText() ) );
			}
			$members->next();
		}

		return $targetPages;
	}
}
