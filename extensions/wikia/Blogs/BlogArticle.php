<?php
/**
 * blog listing for user, something similar to CategoryPage
 *
 * @author Krzysztof Krzyżaniak <eloy@wikia-inc.com>
 * @author Adrtian Wieczorek <adi@wkia-inc.com>
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is MediaWiki extension.\n";
	exit( 1 ) ;
}

class BlogArticle extends Article {

	// Used when constructing memcached keys.  Up the version when the format of the data changes
	const CACHE_VERSION = 3;

	// Cache results for an hour
	const CACHE_TTL = 3600;

	public $mProps;

	/**
	 * how many entries on listing
	 */
	private $mCount = 5;

	/**
	 * setup function called on initialization
	 * Create a Category:BlogListingPage so that we can purge by category when new blogs are posted
	 * moved other setup code to ::ArticleFromTitle instead of hooking that twice [owen]
	 *
	 * @access public
	 * @static
	 */
	public static function createCategory() {
		// make sure page "Category:BlogListingTag" exists
		$title = Title::newFromText( 'Category:BlogListingPage' );

		if ( !$title->exists() && F::app()->wg->User->isAllowed( 'edit' ) ) {
			$page = new WikiPage( $title );
			$page->doEdit(
				"__HIDDENCAT__", $title, EDIT_NEW | EDIT_FORCE_BOT | EDIT_SUPPRESS_RC
			);
		}
	}

	/**
	 * overwritten Article::view function
	 */
	public function view() {
		$feed = $this->getContext()->getRequest()->getText( "feed", false );
		if ( $feed && in_array( $feed, [ "rss", "atom" ] ) ) {
			$this->showFeed( $feed );
		} elseif ( $this->getContext()->getTitle()->isSubpage() ) {
			/**
			 * blog article, show if exists
			 */
			$oldPrefixedText = $this->mTitle->mPrefixedText;
			list( $author, $prefixedText ) = explode( '/', $this->mTitle->getPrefixedText(), 2 );
			if ( isset( $prefixedText ) && !empty( $prefixedText ) ) {
				$this->mTitle->mPrefixedText = $prefixedText;
			}
			$this->mTitle->mPrefixedText = $oldPrefixedText;
			$this->mProps = self::getProps( $this->mTitle->getArticleID() );
			Article::view();
		} else {
			/**
			 * blog listing
			 */
			$this->getContext()->getOutput()->setHTMLTitle( $this->mTitle->getPrefixedText() );
			$this->showBlogListing();
		}
	}

	/**
	 * take data from blog tag extension and display it
	 *
	 * @access private
	 */
	private function showBlogListing() {
		$wg = F::app()->wg;
		$out = $this->getContext()->getOutput();
		$request = $this->getContext()->getRequest();
		$out->setSyndicated( true );

		$owner = $this->getBlogOwner();
		$listing = false;
		$page = $request->getVal( "page", 0 );
		$blogPostCount = null;

		$memc = $wg->Memc;
		$memKey = $this->blogListingMemcacheKey( $owner, $page );

		// Use cache unless action=purge was used
		if ( $request->getVal( 'action' ) != 'purge' ) {
			$cachedValue = $memc->get( $memKey );

			if ( $cachedValue && isset( $cachedValue['listing'] ) ) {
				$listing = $cachedValue['listing'];
				if ( isset( $cachedValue['blogPostCount'] ) ) {
					$blogPostCount = $cachedValue['blogPostCount'];
				}
			}
		}

		if ( !$listing ) {
			$offset = $page * $this->mCount;
			$text = "
				<bloglist
					count=$this->mCount
					summary=true
					summarylength=750
					type=plain
					title=Blogs
					offset=$offset>
					<author>$owner</author>
				</bloglist>";
			$parserOutput = $wg->Parser->parse( $text, $this->mTitle, new ParserOptions() );
			$listing = $parserOutput->getText();
			$blogPostCount = $parserOutput->getProperty( "blogPostCount" );

			$memc->set( $memKey,
				[
					'listing' => $listing,
					'blogPostCount' => $blogPostCount
				],
				self::CACHE_TTL );
		}

		// Link rel=next/prev for SEO
		$lastPage = ceil( $blogPostCount / $this->mCount ) - 1;
		if ( $page > 0 && $page <= $lastPage ) {
			// All pages but the first
			$prevUrl = sprintf( '?page=%d', $page - 1 );
			$link = Html::element( 'link', [ 'rel' => 'prev', 'href' => $prevUrl ] );
			$out->addHeadItem( 'Pagination - prev', "\t" . $link . PHP_EOL );
		}
		if ( $page >= 0 && $page < $lastPage ) {
			// All pages but the last
			$nextUrl = sprintf( '?page=%d', $page + 1 );
			$link = Html::element( 'link', [ 'rel' => 'next', 'href' => $nextUrl ] );
			$out->addHeadItem( 'Pagination - next', "\t" . $link . PHP_EOL );
		}

		if ( isset( $blogPostCount ) && $blogPostCount == 0 ) {
			// bugid: PLA-844
			$out->setRobotPolicy( "noindex,nofollow" );
		}

		$out->addHTML( $listing );
	}

	/**
	 * clear data from memcache and purge any pages in Category:BlogListingPage
	 *
	 * @access public
	 */
	public function clearBlogListing() {
		$memc = F::app()->wg->Memc;

		// Clear Oasis rail module
		$mcKey = $this->blogListingOasisMemcacheKey();
		$memc->delete( $mcKey );

		$count = $this->getBlogListingPageCount();
		foreach ( range( 0, $count - 1 ) as $page ) {
			$mcKey = $this->blogListingMemcacheKey( $this->getBlogOwner(), $page );
			$memc->delete( $mcKey );
		}

		/** @var BlogArticle|WikiPage $this */
		$this->doPurge();

		$title = Title::newFromText( 'Category:BlogListingPage' );
		$title->touchLinks();
	}

	private function getBlogListingPageCount() {
		$owner = $this->getBlogOwner();
		$text = "<bloglist type='count'><author>$owner</author></bloglist>";
		$parserOutput = F::app()->wg->Parser->parse( $text, $this->mTitle, new ParserOptions() );
		$listing = $parserOutput->getText();

		return ceil( ( (int)trim( $listing ) ) / $this->mCount );
	}

	/**
	 * @param int $userKey - user's DB key
	 * @param int $pageNum - page no
	 *
	 * @return String - memcache key
	 */
	public function blogListingMemcacheKey( $userKey, $pageNum ) {
		return wfMemcKey( 'blog', 'listing', 'v' . self::CACHE_VERSION, $userKey, $pageNum );
	}

	/**
	 * Return a key used for caching the oasis rail module
	 *
	 * @return String
	 */
	public function blogListingOasisMemcacheKey() {
		return wfMemcKey( "OasisPopularBlogPosts", 'v' . self::CACHE_VERSION, F::app()->wg->Lang->getCode() );
	}

	/**
	 * @param string $userKey - user's DB key
	 * @param int $offset - offset into paged results
	 *
	 * @return String
	 */
	public function blogFeedMemcacheKey( $userKey, $offset ) {
		return wfMemcKey( 'blog', 'feed', 'v' . self::CACHE_VERSION, $userKey, $offset );
	}

	/**
	 * generate xml feed from returned data
	 */
	private function showFeed( $format ) {
		$wg = F::app()->wg;

		$user = $this->mTitle->getBaseText();
		$listing = false;
		$purge = $this->getContext()->getRequest()->getVal( 'action' ) == 'purge';
		$offset = 0;

		$memKey = $this->blogFeedMemcacheKey( $this->getBlogOwner(), $offset );

		if ( !$purge ) {
			$listing = $wg->Memc->get( $memKey );
		}

		if ( !$listing ) {
			$params = [
				"count" => 50,
				"summary" => true,
				"summarylength" => 750,
				"type" => "array",
				"title" => "Blogs",
				"offset" => $offset
			];

			$listing = BlogTemplateClass::parseTag( "<author>$user</author>", $params, $wg->Parser );
			$wg->Memc->set( $memKey, $listing, self::CACHE_TTL );
		}

		/** @var ChannelFeed $feed */
		$feed = new $wg->FeedClasses[$format](
			$this->getContext()->msg( "blog-userblog", $user )->escaped(),
			$this->getContext()->msg( "blog-fromsitename", $wg->Sitename )->escaped(),
			$this->getContext()->getTitle()->getFullURL()
		);

		$feed->outHeader();
		if ( is_array( $listing ) ) {
			foreach ( $listing as $item ) {
				$title = Title::newFromText( $item["title"], NS_BLOG_ARTICLE );
				$item = new FeedItem(
					$title->getSubpageText(),
					$item["description"],
					$item["url"],
					$item["timestamp"],
					$item["author"]
				);
				$feed->outItem( $item );
			}
		}
		$feed->outFooter();
	}

	/**
	 * static entry point for hook
	 *
	 * @static
	 * @access public
	 */
	static public function ArticleFromTitle( Title &$Title, &$Article ) {
		// macbre: check namespace (RT #16832)
		if ( !in_array( $Title->getNamespace(), [ NS_BLOG_ARTICLE, NS_BLOG_ARTICLE_TALK, NS_BLOG_LISTING, NS_BLOG_LISTING_TALK ] ) ) {
			return true;
		}

		if ( $Title->getNamespace() == NS_BLOG_ARTICLE ) {
			$Article = new BlogArticle( $Title );
		}

		return true;
	}

	/**
	 * return list of props
	 *
	 * @access public
	 * @static
	 *
	 */
	static public function getPropsList() {
		$replace = [ 'voting' => WPP_BLOGS_VOTING, 'commenting' => WPP_BLOGS_COMMENTING ];
		return $replace;
	}

	/**
	 * save article extra properties to page_props table
	 *
	 * @access public
	 * @static
	 *
	 * @param array $props array of properties to save (prop name => prop value)
	 */
	static public function setProps( $page_id, Array $props ) {
		$dbw = wfGetDB( DB_MASTER );

		$replace = self::getPropsList();
		foreach ( $props as $sPropName => $sPropValue ) {
			wfSetWikiaPageProp( $replace[$sPropName], $page_id, $sPropValue );
		}

		$dbw->commit(); # --- for ajax
	}

	/**
	 * get properties for page, maybe it should be cached?
	 *
	 * @access public
	 * @static
	 *
	 * @return array
	 */
	static public function getProps( $page_id ) {
		$return = [ ];
		$types = self::getPropsList();
		foreach ( $types as $key => $value ) {
			$return[$key] = (int)wfGetWikiaPageProp( $value, $page_id );
		}

		
		wfDebug( __METHOD__ . ": getting props for $page_id\n" );

		return $return;
	}

	/** static methods used in Hooks */

	/**
	 * @param CategoryViewer $catView
	 * @param string $output
	 * @return bool true	 *
	 */
	static public function getOtherSection( CategoryViewer &$catView, &$output ) {
		if ( !isset( $catView->blogs ) ) {
			return true;
		}

		$ti = $catView->title->getText();
		$r = '';
		$cat = $catView->getCat();

		$dbcnt = self::blogsInCategory( $cat );
		$rescnt = count( $catView->blogs );
		$countmsg = self::getCountMessage( $catView, $rescnt, $dbcnt, 'article' );

		if ( $rescnt > 0 ) {
			$r = "<div id=\"mw-pages\">\n";
			$r .= '<h2>' . $catView->msg( "blog-header", $ti )->escaped() . "</h2>\n";
			$r .= $countmsg;
			$r .= $catView->getSectionPagingLinksExt( 'page' );
			$r .= $catView->formatList( array_values( $catView->blogs ), $catView->blogs_start_char );
			$r .= $catView->getSectionPagingLinksExt( 'page' );
			$r .= "\n</div>";
		}
		$output = $r;

		
		return true;
	}

	/**
	 * @param Category $cat
	 * @return int|Mixed
	 * @throws DBUnexpectedError
	 */
	static public function blogsInCategory( Category $cat ) {
		$memc = F::app()->wg->Memc;
		$titleText = $cat->getTitle()->getDBkey();
		$memKey = self::getCountKey( $titleText );

		$count = $memc->get( $memKey );

		if ( empty( $count ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				[ 'page', 'categorylinks' ],
				'count(*) as count',
				[
					'page_id = cl_from',
					'page_namespace' => [ NS_BLOG_ARTICLE, NS_BLOG_LISTING ],
					'cl_to' => $titleText,
				],
				__METHOD__
			);

			$count = 0;
			if ( $res->numRows() > 0 ) {
				while ( $row = $res->fetchObject() ) {
					$count = $row->count;
				}
				$dbr->freeResult( $res );
			}

			$memc->set( $memKey, $count );
		}

		return $count;
	}

	/**
	 * Hook - AfterCategoriesUpdate
	 */
	static public function clearCountCache( $categoryInserts, $categoryDeletes, $title ) {
		$memc = F::app()->wg->Memc;

		// Clear the count cache for inserts
		foreach ( $categoryInserts as $catName => $prefix ) {
			$memKey = self::getCountKey( $catName );
			$memc->delete( $memKey );
		}

		// Clear the count cache for deletes
		foreach ( $categoryDeletes as $catName => $prefix ) {
			$memKey = self::getCountKey( $catName );
			$memc->delete( $memKey );
		}

		return true;
	}

	static public function getCountKey( $catName ) {
		return wfMemcKey( 'blog', 'category', 'count', $catName );
	}

	/**
	 * static method to get number of pages in category
	 *
	 * @param $catView
	 * @param $rescnt
	 * @param $dbcnt
	 * @param $type
	 *
	 * @return String
	 */
	static public function getCountMessage( CategoryViewer &$catView, $rescnt, $dbcnt, $type ) {
		$language = $catView->getLanguage();
		# See CategoryPage->getCountMessage() function
		$totalrescnt = count( $catView->blogs ) + count( $catView->children ) + ( $catView->showGallery ? $catView->gallery->count() : 0 );
		if ( $dbcnt == $rescnt || ( ( $totalrescnt == $catView->limit || $catView->from || $catView->until ) && $dbcnt > $rescnt ) ) {
			# Case 1: seems sane.
			$totalcnt = $dbcnt;
		} elseif ( $totalrescnt < $catView->limit && !$catView->from && !$catView->until ) {
			# Case 2: not sane, but salvageable.
			$totalcnt = $rescnt;
		} else {
			# Case 3: hopeless.  Don't give a total count at all.
			return $catView->msg( 'blog-subheader', $language->formatNum( $rescnt ) )->parse();
		}
		return $catView->msg( 'blog-subheader-all', $language->formatNum( $rescnt ), $language->formatNum( $totalcnt ) )->parse();
	}

	/**
	 * Hook
	 *
	 * @param $catView
	 * @param Title $title
	 * @param $row
	 * @param $sortkey
	 *
	 * @return bool
	 * @internal param $CategoryViewer
	 */
	static public function addCategoryPage( CategoryViewer &$catView, &$title, &$row, $sortkey ) {
		if ( in_array( $row->page_namespace, [ NS_BLOG_ARTICLE, NS_BLOG_LISTING ] ) ) {
			/**
			 * initialize CategoryView->blogs array
			 */
			if ( !isset( $catView->blogs ) ) {
				$catView->blogs = [ ];
			}

			// If request comes from wikiamobile or from MercuryApi return not-parsed output
			if ( !empty( $catView->isJSON ) ) {
				$catView->blogs[] = [
					'name' => $title->getText(),
					'url' => $title->getLocalURL(),
				];

				return false;
			}

			/**
			 * initialize CategoryView->blogs_start_char array
			 */
			if ( !isset( $catView->blogs_start_char ) ) {
				$catView->blogs_start_char = [ ];
			}

			// remove user blog:foo from displayed titles (requested by Angie)
			// "User blog:Homersimpson89/Best Simpsons episode..." -> "Best Simpsons episode..."
			$text = $title->getSubpageText();
			$userName = $title->getBaseText();
			$link = Linker::link( $title, htmlspecialchars( $userName . " - " . $text ) );

			$catView->blogs[] = $row->page_is_redirect
				? '<span class="redirect-in-category">' . $link . '</span>'
				: $link;

			// The blog entries should be sorted on the category page
			// just like other pages
			$catView->blogs_start_char[] = $catView->collation->getFirstLetter( $sortkey );

			/**
			 * when we return false it won't be displayed as normal category but
			 * in "other" categories
			 */
			return false;
		}
		return true;
	}

	/**
	 * hook, add link to toolbar
	 *
	 * @param $skin
	 * @param $tabs
	 *
	 * @return bool
	 */
	static public function skinTemplateTabs( Skin $skin, &$tabs ) {
		$wg = F::app()->wg;
		$title = $skin->getTitle();

		if ( !in_array( $title->getNamespace(), [ NS_BLOG_ARTICLE, NS_BLOG_LISTING, NS_BLOG_ARTICLE_TALK ] ) ) {
			return true;
		}

		if ( ( $title->getNamespace() == NS_BLOG_ARTICLE_TALK ) && ( empty( $wg->EnableBlogCommentEdit ) ) ) {
			return true;
		}

		$row = [ ];
		switch ( $title->getNamespace() ) {
			case NS_BLOG_ARTICLE:
				if ( !$title->isSubpage() ) {
					$allowedTabs = [ ];
					$tabs = [ ];
					break;
				}
			case NS_BLOG_LISTING:
				if ( empty( $wg->EnableSemanticMediaWikiExt ) ) {
					$row["listing-refresh-tab"] = [
						"class" => "",
						"text" => $skin->msg( "blog-refresh-label" )->text(),
						"icon" => "refresh",
						"href" => $title->getLocalURL( "action=purge" )
					];
					$tabs += $row;
				}
				break;
			case NS_BLOG_ARTICLE_TALK: {
				$allowedTabs = [ 'viewsource', 'edit', 'delete', 'history' ];
				foreach ( $tabs as $key => $tab ) {
					if ( !in_array( $key, $allowedTabs ) ) {
						unset( $tabs[$key] );
					}
				}
				break;
			}
		}


		return true;
	}

	/**
	 * write additinonal checkboxes on editpage
	 *
	 * @param $EditPage
	 * @param $checkboxes
	 *
	 * @return bool
	 */
	static public function editPageCheckboxes( EditPage &$EditPage, &$checkboxes ) {
		if ( $EditPage->mTitle->getNamespace() != NS_BLOG_ARTICLE ) {
			return true;
		}
		
		Wikia::log( __METHOD__ );

		$output = [ ];
		if ( $EditPage->mTitle->mArticleID ) {
			$props = self::getProps( $EditPage->mTitle->mArticleID );
			$output["voting"] = Xml::checkLabel(
				wfMessage( "blog-voting-label" )->text(),
				"wpVoting",
				"wpVoting",
				isset( $props["voting"] ) && $props["voting"] == 1
			);
			$output["commenting"] = Xml::checkLabel(
				wfMessage( "blog-comments-label" )->text(),
				"wpCommenting",
				"wpCommenting",
				isset( $props["commenting"] ) && $props["commenting"] == 1
			);
		}
		$checkboxes += $output;
		
		return true;
	}

	/**
	 * store properties for updated article
	 *
	 * @param $LinksUpdate
	 *
	 * @return bool
	 */
	static public function linksUpdate( &$LinksUpdate ) {
		$namespace = $LinksUpdate->mTitle->getNamespace();
		if ( !in_array( $namespace, [ NS_BLOG_ARTICLE, NS_BLOG_ARTICLE_TALK ] ) ) {
			return true;
		}

		
		$request = F::app()->wg->Request;

		/**
		 * restore/change properties for blog article
		 */
		$pageId = $LinksUpdate->mTitle->getArticleId();
		$keep = [ ];

		if ( $request->wasPosted() ) {
			$keep["voting"] = $request->getVal( "wpVoting", 0 );
			$keep["commenting"] = $request->getVal( "wpCommenting", 0 );
		} else {
			/**
			 * read current values from database
			 */
			$props = self::getProps( $pageId );
			switch ( $namespace ) {
				case NS_BLOG_ARTICLE:
					$keep["voting"] = isset( $props["voting"] ) ? $props["voting"] : 0;
					$keep["commenting"] = isset( $props["commenting"] ) ? $props["commenting"] : 0;
					break;

				case NS_BLOG_ARTICLE_TALK:
					$keep["hiddencomm"] = isset( $props["hiddencomm"] ) ? $props["hiddencomm"] : 0;
					break;
			}
		}

		if ( $pageId ) {
			$LinksUpdate->mProperties += $keep;
		}

		return true;
	}

	/**
	 * An instance version of getOwner that assumes the owner of the current BlogArticle
	 * is what's wanted.
	 *
	 * @return String
	 */
	public function getBlogOwner() {
		return self::getOwner( $this->mTitle );
	}

	/**
	 * Guess Owner of blog from title
	 *
	 * @param $title
	 *
	 * @return String -- guessed name
	 */
	static public function getOwner( $title ) {
		if ( $title instanceof Title ) {
			$title = $title->getBaseText();
		}
		if ( strpos( $title, "/" ) !== false ) {
			list( $title, $rest ) = explode( "/", $title, 2 );
		}
		

		return $title;
	}

	/**
	 * guess Owner of blog from title and return Title instead of string
	 *
	 * @param $title
	 *
	 * @return String -- guessed name
	 * @throws MWException
	 */
	static public function getOwnerTitle( $title ) {
		$owner = false;

		$text = '';
		if ( $title instanceof Title ) {
			$text = $title->getBaseText();
		}
		if ( strpos( $text, "/" ) !== false ) {
			list( $owner, $rest ) = explode( "/", $text, 2 );
		}
		

		return ( $owner ) ? Title::newFromText( $owner, NS_BLOG_ARTICLE ) : false;
	}

	/**
	 * wfMaintenance -- wiki factory maintenance
	 *
	 * @static
	 */
	static public function wfMaintenance() {
		$results = [ ];

		// VOLDEV-96: Do not credit edits to localhost
		$wikiaUser = User::newFromName( 'Wikia' );

		/**
		 * create Blog:Recent posts page if not exists
		 */
		$recentPosts = wfMessage( 'create-blog-post-recent-listing' )->text();
		if ( $recentPosts ) {
			$recentPostsKey = "Creating {$recentPosts}";
			$oTitle = Title::newFromText( $recentPosts, NS_BLOG_LISTING );
			if ( $oTitle ) {
				$page = new WikiPage( $oTitle );
				if ( !$page->exists() ) {
					$page->doEdit(
						'<bloglist summary="true" count=50><title>'
						. wfMessage( 'create-blog-post-recent-listing-title ' )->text()
						. '</title><type>plain</type><order>date</order></bloglist>',
						wfMessage( 'create-blog-post-recent-listing-log' )->text(),
						EDIT_NEW | EDIT_MINOR | EDIT_FORCE_BOT,  # flags
						false,
						$wikiaUser
					);
					$results[$recentPostsKey] = 'done';
				} else {
					$results[$recentPostsKey] = 'already exists';
				}
			}
		}

		/**
		 * create Category:Blog page if not exists
		 */
		$catName = wfMessage( 'create-blog-post-category' )->text();
		if ( $catName && $catName !== "-" ) {
			$catNameKey = "Creating {$catName}";
			$oTitle = Title::newFromText( $catName, NS_CATEGORY );
			if ( $oTitle ) {
				$page = new WikiPage( $oTitle );
				if ( !$page->exists() ) {
					$page->doEdit(
						wfMessage( 'create-blog-post-category-body' )->text(),
						wfMessage( 'create-blog-post-category-log' )->text(),
						EDIT_NEW | EDIT_MINOR | EDIT_FORCE_BOT,  # flags
						false,
						$wikiaUser
					);
					$results[$catNameKey] = 'done';
				} else {
					$results[$catNameKey] = 'already exists';
				}
			}
		}

		return $results;
	}

	/**
	 * auto-unwatch all comments if blog post was unwatched
	 *
	 * @param $oUser
	 * @param $oArticle
	 *
	 * @return bool
	 */
	static public function UnwatchBlogComments( $oUser, $oArticle ) {

		if ( wfReadOnly() ) {
			return true;
		}

		/* @var $oUser User */
		if ( !$oUser instanceof User ) {
			return true;
		}

		/* @var $oArticle WikiPage */
		if ( !$oArticle instanceof Article ) {
			return true;
		}

		/* @var $oTitle Title */
		$oTitle = $oArticle->getTitle();
		if ( !$oTitle instanceof Title ) {
			return true;
		}

		$list = [ ];
		$dbr = wfGetDB( DB_SLAVE );
		$like = $dbr->buildLike( sprintf( "%s/", $oTitle->getDBkey() ), $dbr->anyString() );
		$res = $dbr->select(
			'watchlist',
			'*',
			[
				'wl_user' => $oUser->getId(),
				'wl_namespace' => NS_BLOG_ARTICLE_TALK,
				"wl_title $like",
			],
			__METHOD__
		);
		if ( $res->numRows() > 0 ) {
			while ( $row = $res->fetchObject() ) {
				$oCommentTitle = Title::makeTitleSafe( $row->wl_namespace, $row->wl_title );
				if ( $oCommentTitle instanceof Title )
					$list[] = $oCommentTitle;
			}
			$dbr->freeResult( $res );
		}

		if ( !empty( $list ) ) {
			foreach ( $list as $oCommentTitle ) {
				$oWItem = WatchedItem::fromUserTitle( $oUser, $oCommentTitle );
				$oWItem->removeWatch();
			}
			$oUser->invalidateCache();
		}

		
		return true;
	}

	/**
	 * Hook used to redirect to custom edit page
	 *
	 * @param EditPage $oEditPage
	 *
	 * @return bool
	 * @throws MWException
	 */
	public static function alternateEditHook( EditPage $oEditPage ) {
		$wg = F::app()->wg;
		$oTitle = $oEditPage->mTitle;
		if ( $oTitle->getNamespace() == NS_BLOG_LISTING ) {
			$oSpecialPageTitle = Title::newFromText( 'CreateBlogListingPage', NS_SPECIAL );
			$wg->Out->redirect( $oSpecialPageTitle->getFullURL( "article=" . urlencode( $oTitle->getText() ) ) );
		}
		if ( $oTitle->getNamespace() == NS_BLOG_ARTICLE && $oTitle->isSubpage() && empty( $oEditPage->isCreateBlogPage ) ) {
			$oSpecialPageTitle = Title::newFromText( 'CreateBlogPage', NS_SPECIAL );
			if ( $wg->Request->getVal( 'oldid' ) ) {
				$url = $oSpecialPageTitle->getFullURL( "pageId=" . $oTitle->getArticleID() . "&oldid=" . $wg->Request->getVal( 'oldid' ) );
			} else if ( $wg->Request->getVal( 'undoafter' ) && $wg->Request->getVal( 'undo' ) ) {
				$url = $oSpecialPageTitle->getFullURL( "pageId=" . $oTitle->getArticleID() . "&undoafter=" . $wg->Request->getVal( 'undoafter' ) . "&undo=" . $wg->Request->getVal( 'undo' ) );
			} else {
				$url = $oSpecialPageTitle->getFullURL( "pageId=" . $oTitle->getArticleID() );
			}
			$wg->Out->redirect( $url );

		}
		return true;
	}
}
