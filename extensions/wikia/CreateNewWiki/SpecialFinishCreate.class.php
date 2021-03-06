<?php

class SpecialFinishCreate extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'FinishCreate', 'finishcreate' );
	}

	public function execute( $par ) {
		global $wgScriptPath;
		wfProfileIn( __METHOD__ );

		$out = $this->getOutput();

		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			wfProfileOut( __METHOD__ );
			return;
		}

		$editToken = $this->getRequest()->getText( 'editToken', '' );
		$user = $this->getUser();

		if ( !$user->isAllowed( 'finishcreate' ) || !$user->matchEditToken( $editToken ) ) {
			$this->displayRestrictionError();
			wfProfileOut( __METHOD__ );
			return;
		}

		F::app()->sendRequest( 'FinishCreateWiki', 'FinishCreate' );

		/**
		 * Mainpage under $wgSitename may not be ready yet
		 * CreateNewWikiTask::postCreationSetup is run asynchronously on task machine
		 *
		 * MediaWiki will handle redirect to the main page and keep the URL parameter
		 *
		 * @see SUS-1167
		 */
		$out->enableClientCache( false );
		$out->redirect( "$wgScriptPath/?wiki-welcome=1" );

		wfProfileOut( __METHOD__ );
	}

}
