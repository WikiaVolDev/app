<?php

use Wikia\Logger\WikiaLogger;

/**
 * Class VideoFileUploader
 */
class VideoFileUploader {

	protected static $ILLEGAL_TITLE_CHARS = array( '/', ':', '#' );
	protected static $IMAGE_NAME_MAX_LENGTH = 255;

	const SANITIZE_MODE_FILENAME = 1;
	const SANITIZE_MODE_ARTICLETITLE = 2;

	// Number of times to attempt uploading a thumbnail
	const UPLOAD_RETRIES = 3;
	// Number of seconds to wait after each upload attempt
	const UPLOAD_RETRY_WAIT = 3;

	protected $sTargetTitle;
	protected $sDescription;
	protected $sExternalUrl;
	protected $sVideoId;
	protected $sProvider;
	protected $oApiWrapper;

	public function setTargetTitle( $sTitle ) {
		$this->sTargetTitle = $sTitle;
	}
	public function setDescription( $sDescription )          { $this->sDescription = $sDescription; }
	public function setExternalUrl( $sUrl )                  { $this->sExternalUrl = $sUrl; }
	public function setProvider( $sProvider )                { $this->sProvider = $sProvider; }
	public function setVideoId( $sVideoId )                  { $this->sVideoId = $sVideoId; }

	public function __construct( ) {
		$this->sTargetTitle = '';
		$this->sDescription = '';
		$this->sExternalUrl = null;
		$this->sVideoId = '';
		$this->sProvider = '';
		$this->oApiWrapper = null;
	}

	protected function tmpUpload( $urlFrom ) {
		wfProfileIn( __METHOD__ );
		$data = array(
			'wpUpload' => 1,
			'wpSourceType' => 'web',
			'wpUploadFileURL' => $urlFrom
		);

		$request = new FauxRequest( $data, true );

		$upload = (new UploadFromUrl); /* @var $upload UploadFromUrl */
		$upload->initializeFromRequest( $request );
		wfProfileOut( __METHOD__ );
		return $upload;
	}

	/**
	 * Start the upload.  Note that this method always returns an object, even when it fails.
	 * Make sure to check that the return value with:
	 *
	 *   $status->isOK()
	 *
	 * @param $oTitle - A title object that will be set if this call is successful
	 * @return FileRepoStatus|Status - A status object representing the result of this call
	 * @throws Exception
	 */
	public function upload( &$oTitle ) {
		$apiWrapper = $this->getApiWrapper();
		$thumbnailUrl = null;
		if ( method_exists( $apiWrapper, 'getThumbnailUrl' ) ) {
			// Some providers will sometimes return error codes when attempting
			// to fetch a thumbnail
			try {
				$thumbnailUrl1 = $apiWrapper->getThumbnailUrl();
				$upload = $this->uploadBestThumbnail( $thumbnailUrl1 );
			} catch ( Exception $e ) {
				WikiaLogger::instance()->error('Video upload failed', [
					'targetFile' => $this->sTargetTitle,
					'externalURL' => $this->sExternalUrl,
					'videoID' => $this->sVideoId,
					'provider' => $this->sProvider,
					'exception' => $e,
					'thumbnailURL' => $thumbnailUrl,
				]);
				return Status::newFatal($e->getMessage());
			}
		} else {
			WikiaLogger::instance()->error( 'Api wrapper corrupted', [
				'targetFile' => $this->sTargetTitle,
				'externalURL' => $this->sExternalUrl,
				'videoID' => $this->sVideoId,
				'provider' => $this->sProvider,
				'apiWrapper' => get_class( $apiWrapper )
			]);

			return Status::newFatal( 'Api wrapper corrupted' );
		}
		$oTitle = Title::newFromText( $this->getNormalizedDestinationTitle(), NS_FILE );

		if ( $oTitle === null ) {
			throw new Exception ( wfMessage ('videohandler-unknown-title')->inContentLanguage()->text() );
		}

		// Check if the user has the proper permissions
		// Mimicks Special:Upload's behavior
		$user = F::app()->wg->User;
		$permErrors = $oTitle->getUserPermissionsErrors( 'edit', $user );
		$permErrorsUpload = $oTitle->getUserPermissionsErrors( 'upload', $user );
		if ( !$oTitle->exists() ) {
			$permErrorsCreate = $oTitle->getUserPermissionsErrors( 'create', $user );
		} else {
			$permErrorsCreate = [];
		}

		if ( $permErrors || $permErrorsUpload || $permErrorsCreate ) {
			$permErrors = array_merge( $permErrors, wfArrayDiff2( $permErrorsUpload, $permErrors ) );
			$permErrors = array_merge( $permErrors, wfArrayDiff2( $permErrorsCreate, $permErrors ) );
			$msgKey = array_shift( $permErrors[0] );
			throw new Exception( wfMessage( $msgKey, $permErrors[0] )->parse()  );
		}

		if ( $oTitle->exists() ) {
			$article = new Article( $oTitle );
			$content = $article->getContent();
			$newcontent = $this->getDescription();
			if ( $content != $newcontent ) {
				$article->doEdit( $newcontent, wfMessage( 'videos-update-edit-summary' )->inContentLanguage()->text() );
			}
		}

		/** @var WikiaLocalFile|WikiaLocalFileShared $file */
		$file = new WikiaLocalFile(
				$oTitle,
				RepoGroup::singleton()->getLocalRepo()
		);

		/* override thumbnail metadata with video metadata */
		$file->forceMime( $this->getApiWrapper()->getMimeType() );
		$file->setVideoId( $this->getVideoId() );

		$forceMime = $file->forceMime;

		$file->getMetadata();

		//In case of video replacement - Title already exists - preserve forceMime value.
		//By default it is changed to false in WikiaLocalFileShared::afterSetProps method
		//which is called by $file->getMetadata().
		if ( $oTitle->exists() ) {
			$file->forceMime = $forceMime;
		}

		/* real upload */
		$result = $file->upload(
			$upload->getTempPath(),
			wfMessage( 'videos-initial-upload-edit-summary' )->inContentLanguage()->text(),
			\UtfNormal::toNFC( $this->getDescription() ),
			File::DELETE_SOURCE
		);

		Hooks::run('AfterVideoFileUploaderUpload', array($file, $result));

		// SUS-1195: make sure the file cache is up to date shortly after the video upload
		if ( $result->isOK() ) {
			$file->saveToCache();
		}

		return $result;
	}

	/**
	 * Get the normalized composed version of the title
	 *
	 * @return string
	 */
	public function getNormalizedDestinationTitle() {
		return \UtfNormal::toNFC( $this->getSanitizedTitleText() );
	}

	/**
	 * Get the sanitized title caption
	 *
	 * @return string
	 */
	public function getSanitizedTitleText() {
		// Create a reference to article that will contain uploaded file
		$titleText = $this->getDestinationTitle();

		return self::sanitizeTitle( $titleText );
	}

	/**
	 * Reset the thumbnail for this video to its original from the provider
	 * @param File $file
	 * @param string $thumbnailUrl
	 * @param int $delayIndex See VideoHandlerHelper->resetVideoThumb for more info
	 * @return FileRepoStatus
	 */
	public function resetThumbnail( File &$file, $thumbnailUrl, $delayIndex = 0 ) {
		wfProfileIn(__METHOD__);

		// Some providers will sometimes return error codes when attempting
		// to fetch a thumbnail
		try {
			$upload = $this->uploadBestThumbnail( $thumbnailUrl, $delayIndex );

			// Publish the thumbnail file (some filerepo classes do not support write operations)
			$result = $file->publish( $upload->getTempPath(), File::DELETE_SOURCE );
		} catch ( Exception $e ) {
			WikiaLogger::instance()->error( __METHOD__, [
				'thumbnailUrl' => $thumbnailUrl,
				'delayIndex' => $delayIndex,
				'file_obj' => $file,
				'exception' => $e
			]);
			wfProfileOut(__METHOD__);
			return Status::newFatal($e->getMessage());
		}

		wfProfileOut(__METHOD__);
		return $result;
	}

	/**
	 * Try to upload the best thumbnail for this file, starting with the one the provider
	 * gives and falling back to the default thumb
	 * @param string $thumbnailUrl
	 * @param int $delayIndex See VideoHandlerHelper->resetVideoThumb for more info
	 * @throws Exception
	 * @return UploadFromUrl
	 */
	protected function uploadBestThumbnail( $thumbnailUrl, $delayIndex = 0 ) {
		wfProfileIn( __METHOD__ );

		// disable proxy
		F::app()->wg->DisableProxy = true;
		// Try to upload the thumbnail for this video
		$upload = $this->uploadThumbnailFromUrl( $thumbnailUrl );

		// If uploading the actual thumbnail fails, load a default thumbnail
		if ( empty($upload) ) {
			$upload = $this->uploadThumbnailFromUrl( LegacyVideoApiWrapper::getLegacyThumbnailUrl() );
			$this->scheduleJob( $delayIndex );
		}

		// If we still don't have anything, give up.
		if ( empty( $upload ) ) {
			wfProfileOut( __METHOD__ );
			throw new Exception( 'Thumbnail upload failed' );
		}

		$this->adjustThumbnailToVideoRatio( $upload );

		wfProfileOut( __METHOD__ );

		return $upload;
	}

	/**
	 * @param $delayIndex
	 */
	private function scheduleJob( $delayIndex ) {
		$provider = $this->oApiWrapper->getProvider();
		if ( $delayIndex < UpdateThumbnailTask::getDelayCount( $provider ) ) {
			$delay = UpdateThumbnailTask::getDelay( $provider, $delayIndex );
			$task = ( new UpdateThumbnailTask() )->wikiId( F::app()->wg->CityId );
			$task->delay( $delay );
			$task->dupCheck();
			$task->call( 'retryThumbUpload', $this->getDestinationTitle(), $delayIndex, $provider );
			$task->queue();
		}
	}

	/**
	 * Attempt to upload the thumbnail for the given URL and return an UploadFromUrl object if
	 * successful.
	 *
	 * @param string $url The source URL for the thumbnail
	 * @return UploadFromUrl An upload object
	 */
	protected function uploadThumbnailFromUrl( $url ) {
		wfProfileIn(__METHOD__);

		for ( $i = 0; $i < self::UPLOAD_RETRIES; $i++ ) {
			if ( $i > 0 ) sleep( self::UPLOAD_RETRY_WAIT );
			// Prepare a temporary file
			$upload = $this->tmpUpload( $url );
			$fetchStatus = $upload->fetchFile();
			if ( $fetchStatus->isGood() ) {
				$status = $upload->verifyUpload();
				if ( isset( $status['status'] ) && ( $status['status'] != UploadBase::EMPTY_FILE ) ) {
					break;
				}
			}
		}

		wfProfileOut(__METHOD__);

		// Return null if we've used up all our retries
		return $i == self::UPLOAD_RETRIES ? null: $upload;
	}


	protected function adjustThumbnailToVideoRatio( $upload ) {

		wfProfileIn( __METHOD__ );

		$sTmpPath = $upload->getTempPath();

		$props = getimagesize( $sTmpPath );
		$orgWidth = $props[0];
		$orgHeight = $props[1];
		$finalWidth = $props[0];
		$finalHeight = $finalWidth / $this->getApiWrapper()->getAspectRatio();

		if ( $orgHeight == $finalHeight ) {
			// no need to resize, we're lucky :)
			wfProfileOut( __METHOD__ );
			return;
		}

		$data = file_get_contents( $sTmpPath );
		$src = imagecreatefromstring( $data );

		$dest = imagecreatetruecolor ( $finalWidth, $finalHeight );
		imagecopy( $dest, $src, 0, 0, 0, ( $orgHeight - $finalHeight ) / 2 , $finalWidth, $finalHeight );


		switch ( $props[2] ) {
			case 2:	imagejpeg( $dest, $sTmpPath ); break;
			case 1:	imagegif ( $dest, $sTmpPath ); break;
			case 3:	imagepng ( $dest, $sTmpPath ); break;
		}
		imagedestroy( $src );
		imagedestroy( $dest );
		wfProfileOut( __METHOD__ );

	}

	public function getApiWrapper( ) {
		wfProfileIn( __METHOD__ );
		if ( !empty( $this->oApiWrapper ) ) {
			wfProfileOut( __METHOD__ );
			return $this->oApiWrapper;
		}

		if ( !empty( $this->sExternalUrl ) ) {
			$apiWF = ApiWrapperFactory::getInstance();
			$this->oApiWrapper = $apiWF->getApiWrapper( $this->sExternalUrl );

			if ( !empty( $this->oApiWrapper ) ) {
				wfProfileOut( __METHOD__ );
				return $this->oApiWrapper;
			}
		}

		if ( !empty($this->sProvider ) ) {
			if ( strstr( $this->sProvider, '/' ) ) {
				$provider = explode( '/', $this->sProvider );
				$apiWrapperPrefix = $provider[0];
			} else {
				$apiWrapperPrefix = $this->sProvider;
			}

			$class = ucfirst( $apiWrapperPrefix ) . 'ApiWrapper';
			$this->oApiWrapper = new $class(
					$this->sVideoId
			);
		}
		wfProfileOut( __METHOD__ );
		return $this->oApiWrapper;
	}

	protected function getDestinationTitle( ) {

		if ( empty( $this->sTargetTitle ) ) {
			$this->sTargetTitle = $this->getApiWrapper()->getTitle();
		}

		return $this->sTargetTitle;
	}

	protected function getDescription( ) {

		wfProfileIn( __METHOD__ );
		if ( empty( $this->sDescription ) ) {
			$headerText = wfMessage( 'videohandler-description' );
			$this->sDescription = "\n== $headerText ==\n" .
								  $this->getApiWrapper()->getDescription() . "\n" .
								  $this->getCategoryVideosWikitext();
		}
		wfProfileOut( __METHOD__ );

		return $this->sDescription;
	}

	/**
	 * gets wiki text for the "Videos" category. For example, on English
	 * wikis: [[Category:Videos]]. i18n-compatible
	 * @return string
	 */
	public function getCategoryVideosWikitext( ) {
		$cat = F::app()->wg->ContLang->getFormattedNsText( NS_CATEGORY );
		return '[[' . $cat . ':' . wfMessage( 'videohandler-category' )->inContentLanguage()->text() . ']]';
	}

	public function getVideoId( ) {
		wfProfileIn( __METHOD__ );
		if ( empty( $this->sVideoId ) ) {
			$this->sVideoId = $this->getApiWrapper()->getVideoId();
		}
		wfProfileOut( __METHOD__ );
		return $this->sVideoId;
	}

	/**
	 * Generates unique Title for new video
	 * The function checks if given title exists and if so, it's adding a postfix
	 * @param string $title
	 * @param int $level
	 * @return Title $oTitle
	 */
	public function getUniqueTitle( $title, $level=0 ) {

		$numRetry = 3;

		$oTitle = Title::newFromText( $title, NS_FILE );

		if ( !empty( $oTitle ) && $oTitle->exists() ) {

			for ( $r = 0; $r <= $numRetry; $r++ ) {
				$newTitleText = $oTitle->getBaseText() . '-' . $r;
				$newTitleObject = Title::newFromText( $newTitleText, NS_FILE );
				if ( !empty( $newTitleObject ) && $newTitleObject->exists() ) {

					if ( $r == $numRetry ) { // stop checking and fallback to timestamp
						$newTitleText = $oTitle->getBaseText() . '-' . time();
						$newTitleObject = Title::newFromText( $newTitleText, NS_FILE );
					}
					continue;

				} else {
					break;
				}
			}

			return $newTitleObject;
		}
		return $oTitle;
	}

	/**
	 * Translate URL to Title object.  Can transparently upload new video if it doesn't exist
	 * @param string $url
	 * @param string $sTitle - if $requestedTitle new Video will be created you can optionally request
	 *  it's title (otherwise Video name from provider is used)
	 * @param string $sDescription
	 * @return null|Title
	 */
	public static function URLtoTitle( $url, $sTitle = '', $sDescription = '' ) {
		wfProfileIn( __METHOD__ );
		$oTitle = null;
		$oUploader = new self();
		$oUploader->setExternalUrl( $url );
		$oUploader->setTargetTitle( $sTitle );
		if ( !empty( $oUploader->getApiWrapper() ) ) {
			if ( !empty($sDescription) ) {
				$categoryVideosTxt = self::getCategoryVideosWikitext();
				if ( strpos( $sDescription, $categoryVideosTxt ) === false ) {
					$sDescription .= $categoryVideosTxt;
				}
				$oUploader->setDescription( $sDescription );
			}

			$status = $oUploader->upload( $oTitle );
			if ( $status->isOK() ) {
				wfProfileOut( __METHOD__ );
				return $oTitle;
			}
		}

		\Wikia\Logger\WikiaLogger::instance()->error( __METHOD__ . ' - video upload failed', [
			'video_url' => (string) $url,
			'provider_name' => (string) $oUploader->sProvider,
			'upload_status' => isset( $status ) ? $status : Status::newFatal( 'api-wrapper-not-found' )
		] );

		wfProfileOut( __METHOD__ );
		return null;
	}

	/**
	 * Sanitize text for use as filename and article title
	 * @param string $titleText title to sanitize
	 * @param string $replaceChar character to replace illegal characters with
	 * @return string sanitized title
	 */
	public static function sanitizeTitle( $titleText, $replaceChar=' ' ) {

		wfProfileIn( __METHOD__ );

		foreach ( self::$ILLEGAL_TITLE_CHARS as $illegalChar ) {
			$titleText = str_replace( $illegalChar, $replaceChar, $titleText );
		}

		$titleText = preg_replace(Title::getTitleInvalidRegex(), $replaceChar, $titleText);

		// remove multiple spaces
		$aTitle = explode( $replaceChar, $titleText );
		$sTitle = implode( $replaceChar, array_filter( $aTitle ) );    // array_filter() removes null elements

		$sTitle = substr($sTitle, 0, self::$IMAGE_NAME_MAX_LENGTH);     // DB column Image.img_name has size 255

		wfProfileOut( __METHOD__ );

		return trim( $sTitle );
	}
}
