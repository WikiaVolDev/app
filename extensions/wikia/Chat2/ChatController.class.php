<?php
class ChatController extends WikiaController {

	const CHAT_WORDMARK_WIDTH = 115;
	const CHAT_WORDMARK_HEIGHT = 30;
	const CHAT_AVATAR_DIMENSION = 41;

	public function executeIndex() {
		ChatHelper::info( __METHOD__ . ': Method called' );

		// String replacement logic taken from includes/Skin.php
		$this->wgFavicon = str_replace( 'images.wikia.com', 'images1.wikia.nocookie.net', $this->wg->Favicon );

		$this->mainPageURL = Title::newMainPage()->getLocalURL();

		// add messages (fetch them using <script> tag)
		JSMessages::enqueuePackage( 'Chat', JSMessages::EXTERNAL ); // package defined in Chat_setup.php

		$this->jsMessagePackagesUrl = JSMessages::getExternalPackagesUrl();
		// Variables for this user
		$this->username = $this->wg->User->getName();
		$this->avatarUrl = AvatarService::getAvatarUrl( $this->username, ChatController::CHAT_AVATAR_DIMENSION );

		// Find the chat for this wiki (or create it, if it isn't there yet).
		$this->roomId = (int) NodeApiClient::getDefaultRoomId();

		// we overwrite here data from redis since it causes a bug DAR-1532
		$pageTitle = new WikiaHtmlTitle();
		$pageTitle->setParts( [ wfMessage( 'chat' ) ] );
		$this->pageTitle = $pageTitle->getTitle();

		$this->chatkey = Chat::echoCookies();
		// Set the hostname of the node server that the page will connect to.

		$chathost = ChatHelper::getChatConfig( 'ChatHost' );

		$server = explode( ":", $chathost );
		$this->nodeHostname = $server[0];
		$this->nodePort = $server[1];

		$chatmain = ChatHelper::getServer( 'Main' );
		$this->nodeInstance = $chatmain['serverId'];

		// Some building block for URLs that the UI needs.
		$this->pathToProfilePage = Title::makeTitle( !empty( $this->wg->EnableWallExt ) ? NS_USER_WALL : NS_USER_TALK, '$1' )->getFullURL();
		$this->pathToContribsPage = SpecialPage::getTitleFor( 'Contributions', '$1' )->getFullURL();

		$this->bodyClasses = "";
		if ( $this->wg->User->isAllowed( 'chatmoderator' ) ) {
			$this->isChatMod = 1;
			$this->bodyClasses .= ' chat-mod ';
		} else {
			$this->isChatMod = 0;
		}

		// Adding chatmoderator group for other users. CSS classes added to body tag to hide/show option in menu.
		$userChangeableGroups = $this->wg->User->changeableGroups();
		if ( in_array( 'chatmoderator', $userChangeableGroups['add'] ) ) {
			$this->bodyClasses .= ' can-give-chat-mod ';
		}

		// VOLDEV-84: Full interwiki link support in Chat
		$iw = Interwiki::getAllPrefixes();
		$prefixes = $urls = [];
		foreach ( $iw as $prefix ) {
			$prefixes[] = $prefix['iw_prefix'];
			$urls[] = $prefix['iw_url'];
		}

		// set up global js variables just for the chat page
		$this->wg->Out->addJsConfigVars( [
			'roomId' => $this->roomId,
			'wgChatMod' => $this->isChatMod,
			'WIKIA_NODE_HOST' => $this->nodeHostname,
			'WIKIA_NODE_INSTANCE' => $this->nodeInstance,
			'WIKIA_NODE_PORT' => $this->nodePort,
			'WEB_SOCKET_SWF_LOCATION' => $this->wg->ExtensionsPath . '/wikia/Chat/swf/WebSocketMainInsecure.swf?' . $this->wg->StyleVersion,
			'EMOTICONS' => wfMessage( 'emoticons' )->inContentLanguage()->plain(),
	
			'pathToProfilePage' => $this->pathToProfilePage,
			'pathToContribsPage' => $this->pathToContribsPage,
			'wgAvatarUrl' => $this->avatarUrl,
			'wgChatKey' => $this->chatkey,
			'wgLangtMonthAbbreviation' => $this->wg->Lang->getMonthAbbreviationsArray(),

			// VOLDEV-84: Full interwiki link support in Chat
			'wgInterwikiPrefixes' => $prefixes,
			'wgInterwikiUrls' => $urls,
		] );

		$ret = implode( "\n", [
			$this->wg->Out->getHeadLinks( null, true ),
			$this->wg->Out->buildCssLinks(),
			$this->wg->Out->getHeadScripts(),
			$this->wg->Out->getHeadItems()
		] );

		$this->globalVariablesScript = $ret;

		// Theme Designer stuff
		$themeSettingObj = new ThemeSettings();
		$themeSettings = $themeSettingObj->getSettings();
		$this->themeSettings = $themeSettings;
		$this->wordmarkThumbnailUrl = '';
		if ( $themeSettings['wordmark-type'] = 'graphic' ) {
			$title = Title::newFromText( $themeSettings['wordmark-image-name'], NS_FILE );
			if ( $title ) {
				$image = wfFindFile( $title );
				if ( $image ) {
					$this->wordmarkThumbnailUrl = $image->createThumb( self::CHAT_WORDMARK_WIDTH, self::CHAT_WORDMARK_HEIGHT );
				}
			}
			if ( empty( $this->wordmarkThumbnailUrl ) ) {
				$this->wordmarkThumbnailUrl = WikiFactory::getLocalEnvURL( $themeSettings['wordmark-image-url'] );
			}
		}

		// CONN-436: Invalidate Varnish cache for ChatRail:GetUsers
		ChatRailController::purgeMethod( 'GetUsers', [ 'format' => 'json' ] );
		
	}
}
