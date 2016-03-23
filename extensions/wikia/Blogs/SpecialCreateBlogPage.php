<?php

class CreateBlogPage extends SpecialCustomEditPage {

	const FIELD_IS_COMMENTING_ENABLED = 'wpIsCommentingEnabled';
	const STATUS_BLOG_PERMISSION_DENIED = -101;
	protected $mFormData = array();
	protected $titleNS = NS_BLOG_ARTICLE;

	public function __construct() {
		// TODO create some abstract metod to force user to get CreateBlogPage
		parent::__construct( 'CreateBlogPage' );
	}

	protected function initializeEditPage() {
		$editPage = parent::initializeEditPage();
		$editPage->isCreateBlogPage = true;
		return $editPage;
	}


	public function execute( $par ) {
		$user = $this->getUser();
		if ( !$user->isLoggedIn() ) {
			$this->getOutput()->showErrorPage( 'create-blog-no-login', 'create-blog-login-required', [ wfGetReturntoParam() ] );
			return;
		}

		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->mBlock );
		}

		if ( wfReadOnly() ) {
			$this->getOutput()->readOnlyPage();
			return;
		}

		parent::execute( $par );

		/* bugId::34933 Actions::getActionName() assumes every Special page is a view.  Forcing a wgAction override for this page */
		$this->getOutput()->addJsConfigVars( 'wgAction', 'edit' );
	}

	protected function afterArticleInitialize( $mode, $title, $article ) {
		wfRunHooks( 'BlogArticleInitialized', [ $this, $mode ] );

		if ( $mode == self::MODE_EDIT ) {
			$aPageProps = BlogArticle::getProps( $article->getId() );
			$this->mFormData['isCommentingEnabled'] = empty( $aPageProps['commenting'] ) ? 0 : $aPageProps['commenting'];

			$isAllowed = $this->getUser()->isAllowed( "blog-articles-edit" );
			if ( ( strtolower( $this->getUser()->getName() ) != strtolower( BlogArticle::getOwner( $title ) ) ) && !$isAllowed ) {
				$this->titleStatus = self::STATUS_BLOG_PERMISSION_DENIED;
				$this->addEditNotice(  $this->msg( 'create-blog-permission-denied' )->text() );
			}
		} else {
			$this->mFormData['isCommentingEnabled'] = true;
		}
	}

	/**
	 * Return wikitext for generating preview / diff / to be saved
	 */
	public function getWikitextFromRequest() {
		$wikitext = parent::getWikitextFromRequest();

		if ( $this->mode == self::MODE_NEW ) {
			$catName = $this->msg( "create-blog-post-category" )->inContentLanguage()->text();
			$sCategoryNSName = $this->contLang->getFormattedNsText( NS_CATEGORY );
			$wikitext .= "\n[[" . $sCategoryNSName . ":" . $catName . "]]";
		}

		return $wikitext;
	}


	protected function getTitlePrefix() {
		return $this->getUser()->getName() . '/';
	}

	/**
	 * add some default values
	 */
	public function beforeSave() {
		if ( empty( $this->mEditPage->summary ) ) {
			$this->mEditPage->summary = $this->msg( 'create-blog-updated' )->text();
		}
		$this->mEditPage->recreate = true;
	}

	/**
	 * Perform additional checks when saving an article
	 */
	protected function processSubmit() {
		// used to set some default values */

		if ( $this->mode != self::MODE_NEW_SETUP ) {
			if ( $this->contentStatus == EditPage::AS_BLANK_ARTICLE ) {
				$this->addEditNotice( $this->msg( 'plb-create-empty-body-error' )->escaped() );
			}

			switch ( $this->titleStatus ) {
				case self::STATUS_EMPTY:
					$this->addEditNotice( $this->msg( 'create-blog-empty-title-error' )->escaped() );
					break;
				case self::STATUS_INVALID:
					$this->addEditNotice( $this->msg( 'create-blog-invalid-title-error' )->escaped() );
					break;
				case self::STATUS_ALREADY_EXISTS:
					$this->addEditNotice( $this->msg( 'create-blog-article-already-exists' )->escaped() );
					break;
			}
		}
	}
	public function getPageTitle() {
		if ( $this->mode == self::MODE_EDIT ) {
			return $this->msg( 'create-blog-post-title-edit' )->text();
		} else {
			return $this->msg( 'create-blog-post-title' )->text();
		}
	}

	public function renderHeader( $par ) {
		$this->forceUserToProvideTitle( 'create-blog-form-post-title' );
		$this->addCustomCheckbox( self::FIELD_IS_COMMENTING_ENABLED, $this->msg( 'blog-comments-label' )->escaped(), $this->mFormData['isCommentingEnabled'] );
	}

	protected function afterSave( Status $status ) {
		switch ( $status->value ) {
			case EditPage::AS_SUCCESS_UPDATE:
			case EditPage::AS_SUCCESS_NEW_ARTICLE:

				/** @var WikiPage|BlogArticle $article */
				$article = $this->getEditedArticle();
				$articleId = $article->getID();

				$aPageProps = [];
				$aPageProps['commenting'] = 0;
				if ( $this->getField( self::FIELD_IS_COMMENTING_ENABLED ) != "" ) {
					$aPageProps['commenting'] = 1;
				}

				if ( count( $aPageProps ) ) {
					BlogArticle::setProps( $articleId, $aPageProps );
				}

				$this->invalidateCacheConnected( $article );
				$this->createListingPage();

				// BugID:25123 purge the main blog listing pages cache
				global $wgMemc;
				$user = $article->getTitle()->getBaseText();
				$ns = $article->getTitle()->getNsText();
				foreach ( range( 0, 5 ) as $page ) {
					$wgMemc->delete( wfMemcKey( 'blog', 'listing', $ns, $user, $page ) );
				}

				$this->getOutput()->redirect( $article->getTitle()->getFullURL() );
				break;

			default:
				Wikia\Logger\WikiaLogger::instance()->error( __METHOD__ . '-' . 'editPage', [ 'statusValue' => $status->value ] );
				$english = Language::factory( 'en' );
				if ( $status->value == EditPage::AS_READ_ONLY_PAGE_LOGGED ) {
					$sMsg = wfMessage( 'create-blog-cant-edit' )->inLanguage( $english )->text();
				}
				else {
					$sMsg = wfMessage( 'create-blog-spam' )->inLanguage( $english )->text();
				}
				Wikia\Logger\WikiaLogger::instance()->error( __METHOD__ . '-' . 'saveError', [ 'message' => $sMsg ] );
				break;
		}
	}

	/**
	 * purge cache for connected articles
	 *
	 * @access public
	 * @author Krzysztof Krzyżaniak <eloy@wikia-inc.com>
	 *
	 */
	public function invalidateCacheConnected( BlogArticle $article ) {
		$title = $article->getTitle();
		$title->invalidateCache();
		/**
		 * this should be subpage, invalidate page as well
		 */
		list( $page, $subpage ) = explode( "/", $title->getDBkey() );
		$title = Title::newFromDBkey( $page );
		$title->invalidateCache();
		$article->clearBlogListing();
	}

	/**
	 * createListingPage -- create listing article if not exists
	 *
	 * @access private
	 * @author Krzysztof Krzyżaniak <eloy@wikia-inc.com>
	 */
	private function createListingPage() {
		
		$oTitle = Title::newFromText( $this->getUser()->getName(), NS_BLOG_ARTICLE );
		$oArticle = new WikiPage( $oTitle, 0 );
		if ( !$oArticle->exists( ) ) {
			/**
			 * add empty article for newlycreated blog
			 */
			$oArticle->doEdit(
				$this->msg( "create-blog-empty-article" )->inContentLanguage()->text(),     # body
				$this->msg( "create-blog-empty-article-log" )->inContentLanguage()->text(), # summary
				EDIT_NEW | EDIT_MINOR | EDIT_FORCE_BOT, # flags
				false,                                  # baseRevId
				$this->getUser(),                                   # user
				true                                    # forcePatrolled
			);
		}
	}


	protected function setEditedTitle( Title $title ) {
		$this->setEditedArticle( new BlogArticle( $title ) );
	}
}
