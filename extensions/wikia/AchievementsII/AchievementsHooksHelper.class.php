<?php

class AchievementsHooksHelper {
	/**
	 * Hook: ArticleUpdateCategoryCounts
	 *
	 * Checks the categories added/removed in the edit. Purges edit track cache for content pages,
	 * and adds/removes the appropriate tracks for categories
	 * @param WikiPage $page
	 * @param array $added
	 * @param array $deleted
	 * @return bool true because it's a hook
	 * @throws MWException
	 */
	public static function onArticleUpdateCategoryCounts( WikiPage &$page, array $added, array $deleted ) {
		// Only do anything if the categories were touched
		if ( count( $added ) || count( $deleted ) ) {
			$title = $page->getTitle();
			$id = $title->getArticleID();

			// if it's a content page, we only have to invalidate the edit track cache for it
			// less expensive than querying if the tracks actually changed
			if ( $title->isContentPage() ) {
				( new \Wikia\Cache\AsyncCache() )
					->purge( AchAwardingService::getEditTrackCacheKeyForPage( $id ) );
				return true;
			}

			// If it's a category, we need to check what edit tracks
			// do the newly added/removed categories add or remove
			if ( $title->getNamespace() == NS_CATEGORY ) {
				// fetch current tracks from cache
				$cache = new \Wikia\Cache\AsyncCache();
				$currentTracks = $cache
					->key( AchAwardingService::getEditTrackCacheKeyForPage( $id ) )
					->ttl( 0 )
					->callback( 'AchAwardingService::getEditTracksForCategory' )
					->callbackParams( [ $id ] )
					->value();

				// If one of the new categories contains new tracks, add them.
				foreach ( $added as $cat ) {
					$catTitle = Title::newFromText( $cat, NS_CATEGORY );
					$catTracks = $cache
						->key( AchAwardingService::getEditTrackCacheKeyForPage( $catTitle->getArticleID() ) )
						->ttl( 0 )
						->callback( 'AchAwardingService::getEditTracksForCategory' )
						->callbackParams( [ $catTitle->getArticleID() ] )
						->value();
					$newTracks = array_diff( $catTracks, $currentTracks );
					foreach ( $newTracks as $track ) {
						//$task = ( new ManageTracksTask( $title->getText(), $track ) )->wikiId( F::app()->wg->CityId );
						$task = ( new ManageTracksTask( $title->getText(), $track ) );
						/*$task->call( 'addTrack' );
						$task->queue();*/
						$task->addTrack();
					}
				}

				// If one of the removed categories contained edit tracks, remove them
				foreach ( $deleted as $cat ) {
					$catTitle = Title::newFromText( $cat, NS_CATEGORY );
					$catTracks = $cache
						->key( AchAwardingService::getEditTrackCacheKeyForPage( $catTitle->getArticleID() ) )
						->ttl( 0 )
						->callback( 'AchAwardingService::getEditTracksForCategory' )
						->callbackParams( [ $catTitle->getArticleID() ] )
						->value();
					foreach ( $catTracks as $track ) {
						//$task = ( new ManageTracksTask( $title->getText(), $track ) )->wikiId( F::app()->wg->CityId );
						$task = ( new ManageTracksTask( $title->getText(), $track ) );
						/*$task->call( 'removeTrack' );
						$task->queue();*/
						$task->removeTrack();
					}
				}
			}
		}

		return true;
	}
}