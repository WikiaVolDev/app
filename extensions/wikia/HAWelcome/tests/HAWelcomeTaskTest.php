<?php

class HAWelcomeTaskTest extends WikiaBaseTest {

	public function testNormalizeInstanceParameters() {
		$userId    = 1;
		$username  = 'foo';
		$timestamp = 999;

		$task = new HAWelcomeTask();

		$params = array(
			'iUserId'    => $userId,
			'sUserName'  => $username,
			'iTimestamp' => $timestamp,
		);

		$task->normalizeInstanceParameters( $params );
		$this->assertEquals( $userId, $task->getRecipientId() );
		$this->assertEquals( $username, $task->getRecipientUserName() );
		$this->assertEquals( $timestamp, $task->getTimestamp() );
	}

	public function testSendMessageWithWall() {
		$task = $this->getMock( '\HAWelcomeTask', ['getMessageWallExtensionEnabled', 'postWallMessageToRecipient', 'setMessage'], [], '', false );

		$task->expects( $this->atLeastOnce() )
			->method( 'getMessageWallExtensionEnabled' )
			->will( $this->returnValue( true ) );

		$task->expects( $this->atLeastOnce() )
			->method( 'postWallMessageToRecipient' )
			->will( $this->returnValue(null) );

		$task->expects( $this->atLeastOnce() )
			->method( 'setMessage' )
			->will( $this->returnValue(null) );

		$task->sendMessage();
	}

	public function testSendMessageWithTalkPage() {
		$task = $this->getMock( '\HAWelcomeTask', ['getMessageWallExtensionEnabled', 'postTalkPageMessageToRecipient', 'setMessage'], [], '', false );

		$task->expects( $this->atLeastOnce() )
			->method( 'getMessageWallExtensionEnabled' )
			->will( $this->returnValue( false ) );

		$task->expects( $this->atLeastOnce() )
			->method( 'postTalkPageMessageToRecipient' )
			->will( $this->returnValue(null) );

		$task->expects( $this->atLeastOnce() )
			->method( 'setMessage' )
			->will( $this->returnValue(null) );

		$task->sendMessage();
	}

	public function testPostTalkPageToRecipientWhenExists() {
		$talkPage = $this->getMock( '\Article', ['exists', 'getContent', 'doEdit'], [], '', false );

		$senderObject = $this->getMock( '\User', ['getName'] );
		$senderObject->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'sender name' ) );

		$recipientObject = $this->getMock( '\User', ['getName'] );
		$recipientObject->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'recipient name' ) );

		$talkPage->expects( $this->atLeastOnce() )
			->method( 'exists' )
			->will( $this->returnValue( true ) );

		$talkPageContent = 'foo';
		$talkPage->expects( $this->atLeastOnce() )
			->method( 'getContent' )
			->will( $this->returnValue( $talkPageContent ) );

		$talkPage->expects( $this->atLeastOnce() )
			->method( 'doEdit' )
			->will( $this->returnValue( $talkPageContent ) );

		$task = $this->getMock( '\HAWelcomeTask', ['getRecipientTalkPage', 'getTextVersionOfMessage'], [], '', false );
		$task->setSenderObject( $senderObject );
		$task->setRecipientObject( $recipientObject );

		$task->expects( $this->exactly( 3 ) )
			->method( 'getRecipientTalkPage' )
			->will( $this->returnValue( $talkPage ) );

		$task->expects( $this->once() )
			->method( 'getTextVersionOfMessage' )
			->with( 'welcome-message-log' )
			->will( $this->returnValue( 'a-message' ) );

		$task->postTalkPageMessageToRecipient();
	}

	public function testPostTalkPageToRecipientWhenNotExists() {
		$sender = $this->getMock( '\User', ['getName'] );
		$recipient = $this->getMock( '\User', ['getName'] );

		$talkPage = $this->getMock( '\Article', ['exists', 'getContent', 'doEdit'], [], '', false );

		$talkPage->expects( $this->atLeastOnce() )
			->method( 'exists' )
			->will( $this->returnValue( false ) );

		$talkPageContent = 'foo';
		$talkPage->expects( $this->exactly( 0 ) )
			->method( 'getContent' )
			->will( $this->returnValue( $talkPageContent ) );

		$task = $this->getMock( '\HAWelcomeTask', ['getRecipientTalkPage', 'getTextVersionOfMessage'], [], '', false );

		$task->expects( $this->exactly( 2 ) )
			->method( 'getRecipientTalkPage' )
			->will( $this->returnValue( $talkPage ) );

		$textMessage = 'a-message';
		$task->expects( $this->once() )
			->method( 'getTextVersionOfMessage' )
			->will( $this->returnValue( $textMessage ) );

		$sender->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'sender' ) );

		$recipient->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'recipient' ) );

		$talkPage->expects( $this->once() )
			->method( 'doEdit' )
			->with( null, $textMessage, 0, false, $sender )
			->will( $this->returnValue( null ) );

		$task->setSenderObject( $sender );
		$task->setRecipientObject( $recipient );

		$task->postTalkPageMessageToRecipient();
	}

	public function testCreateUserProfilePage() {
		$sender = $this->getMock( '\User', ['getName'] );
		$recipient = $this->getMock( '\User', ['getName'] );

		$profilePage = $this->getMock( '\Article', ['exists', 'doEdit'], [], '', false );
		$task = $this->getMock( '\HAWelcomeTask', [
			'getRecipientProfilePage',
			'getWelcomePageTemplateForRecipient',
			] );

		$profilePage->expects( $this->once() )
			->method( 'exists' )
			->will( $this->returnValue( false ) );

		$welcomePageTemplate = 'any';

		$task->expects( $this->once() )
			->method( 'getRecipientProfilePage' )
			->will( $this->returnValue( $profilePage ) );

		$task->expects( $this->once() )
			->method( 'getWelcomePageTemplateForRecipient' )
			->will( $this->returnValue( $welcomePageTemplate ) );

		$sender->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'sender' ) );

		$recipient->expects( $this->once() )
			->method( 'getName' )
			->will( $this->returnValue( 'recipient' ) );

		$profilePage->expects( $this->once() )
			->method( 'doEdit' )
			->with( $welcomePageTemplate, false, 0, false, $sender )
			->will( $this->returnValue( null ) );

		$task->setSenderObject( $sender );
		$task->setRecipientObject( $recipient );
		$task->createUserProfilePage();
	}


	public function testMergeFeatureFlagsFromUserMessageAnon() {
		$task = $this->getMock( '\HAWelcomeTask', ['getUserFeatureFlags'], [], '', false );

		$task->expects( $this->any() )
			->method( 'getUserFeatureFlags' )
			->will( $this->returnValue( 'message-anon' ) );

		$task->mergeFeatureFlagsFromUserSettings();
		$this->assertTrue( $task->isFeatureFlagEnabled( 'message-anon' ) );
		$this->assertFalse( $task->isFeatureFlagEnabled( 'page-user' ) );

		$this->assertFalse( $task->isFeatureFlagEnabled( 'key-does-not-exist' ) );
	}

	public function testMergeFeatureFlagsFromUserUser() {
		$task = $this->getMock( '\HAWelcomeTask', ['getUserFeatureFlags'], [], '', false );

		$task->expects( $this->any() )
			->method( 'getUserFeatureFlags' )
			->will( $this->returnValue( 'page-user message-user' ) );

		$task->mergeFeatureFlagsFromUserSettings();
		$this->assertFalse( $task->isFeatureFlagEnabled( 'message-anon' ) );
		$this->assertTrue( $task->isFeatureFlagEnabled( 'page-user' ) );
		$this->assertTrue( $task->isFeatureFlagEnabled( 'message-user' ) );
	}

	public function testSendWelcomMessageAnonymousWallExtensionANDEmptyWall() {
		$userId    = 0;
		$username  = 'foo';
		$timestamp = 999;

		$params = array(
			'iUserId'    => $userId,
			'sUserName'  => $username,
			'iTimestamp' => $timestamp,
		);

		$task = $this->getMock( '\HAWelcomeTask',
			[
			'isFeatureFlagEnabled',
			'getMessageWallExtensionEnabled',
			'recipientWallIsEmpty',
			'getTextVersionOfMessage',
			'setSender',
			'sendMessage'
			], [], '', false );

		$task->expects( $this->once() )
			->method( 'isFeatureFlagEnabled' )
			->with( 'message-anon' )
			->will( $this->returnValue( true ) );

		$task->expects( $this->exactly( 2 ) )
			->method( 'getMessageWallExtensionEnabled' )
			->will( $this->returnValue( true ) );

		$task->expects( $this->once() )
			->method( 'recipientWallIsEmpty' )
			->will( $this->returnValue( true ) );

		$task->expects( $this->once() )
			->method( 'setSender' )
			->will( $this->returnValue( null ) );

		$senderObject = $this->getMock( '\User' );
		$senderObject->expects( $this->once() )
			->method( 'isAllowed' )
			->with( 'bot' )
			->will( $this->returnValue( true ) );
		$task->setSenderObject( $senderObject );

		$task->expects( $this->once() )
			->method( 'sendMessage' )
			->will( $this->returnValue( null ) );

		$task->expects( $this->once( ) )
			->method( 'getTextVersionOfMessage' )
			->with( 'welcome-enabled' )
			->will( $this->returnValue( "message-anon message-user page-user" ) );

		$task->sendWelcomeMessage( $params );
	}

	public function testSendWelcomMessageAnonymousWallExtensionDisabledANDNoTalkPage() {
		$userId    = 0;
		$username  = 'foo';
		$timestamp = 999;

		$params = array(
			'iUserId'    => $userId,
			'sUserName'  => $username,
			'iTimestamp' => $timestamp,
		);

		$task = $this->getMock( '\HAWelcomeTask',
			[
			'isFeatureFlagEnabled',
			'getMessageWallExtensionEnabled',
			'getTextVersionOfMessage',
			'sendMessage',
			'setSender',
			'getRecipientTalkPage',
			], [], '', false );

		$talkPage = $this->getMock( '\Article', ['exists'], [], '', false );

		$talkPage->expects( $this->atLeastOnce() )
			->method( 'exists' )
			->will( $this->returnValue( false ) );

		$task->expects( $this->once() )
			->method( 'getRecipientTalkPage' )
			->will( $this->returnValue( $talkPage ) );

		$task->expects( $this->once() )
			->method( 'isFeatureFlagEnabled' )
			->with( 'message-anon' )
			->will( $this->returnValue( true ) );

		$task->expects( $this->exactly( 2 ) )
			->method( 'getMessageWallExtensionEnabled' )
			->will( $this->returnValue( false ) );

		$task->expects( $this->once( ) )
			->method( 'getTextVersionOfMessage' )
			->with( 'welcome-enabled' )
			->will( $this->returnValue( "message-anon message-user page-user" ) );

		$senderObject = $this->getMock( '\User' );
		$senderObject->expects( $this->once() )
			->method( 'isAllowed' )
			->with( 'bot' )
			->will( $this->returnValue( true ) );
		$task->setSenderObject( $senderObject );

		$task->expects( $this->once() )
			->method( 'setSender' )
			->will( $this->returnValue( null ) );

		$task->expects( $this->once() )
			->method( 'sendMessage' )
			->will( $this->returnValue( null ) );

		$task->sendWelcomeMessage( $params );
	}

	public function testSendWelcomMessageRegistered() {
		$userId    = 1;
		$username  = 'foo';
		$timestamp = 999;

		$params = array(
			'iUserId'    => $userId,
			'sUserName'  => $username,
			'iTimestamp' => $timestamp,
		);

		$task = $this->getMock( '\HAWelcomeTask',
			[
			'isFeatureFlagEnabled',
			'sendMessage',
			'createUserProfilePage',
			'getTextVersionOfMessage',
			'setSender'
			], [], '', false );

		$task->expects( $this->exactly( 2 ) )
			->method( 'isFeatureFlagEnabled' )
			->with( $this->logicalOr( 'message-user', 'page-user' ) )
			->will( $this->returnValue( true ) );

		$task->expects( $this->once() )
			->method( 'sendMessage' )
			->will( $this->returnValue( null ) );

		$task->expects( $this->once() )
			->method( 'createUserProfilePage' )
			->will( $this->returnValue( null ) );

		$task->expects( $this->once( ) )
			->method( 'getTextVersionOfMessage' )
			->with( 'welcome-enabled' )
			->will( $this->returnValue( "message-anon message-user page-user" ) );

		$task->expects( $this->once( ) )
			->method( 'setSender' )
			->will( $this->returnValue( null ) );

		$senderObject = $this->getMock( '\User' );
		$senderObject->expects( $this->once() )
			->method( 'isAllowed' )
			->with( 'bot' )
			->will( $this->returnValue( true ) );
		$task->setSenderObject( $senderObject );

		$task->sendWelcomeMessage( $params );
	}


}
