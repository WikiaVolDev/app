<?php

class PortableInfoboxBuilderController extends WikiaController {

	const INFOBOX_BUILDER_PARAM = 'portableInfoboxBuilder';

	public function getAssets() {
		global $wgEnablePortableInfoboxEuropaTheme;

		$response = $this->getResponse();
		$response->setFormat( WikiaResponse::FORMAT_JSON );
		if ( !empty( $wgEnablePortableInfoboxEuropaTheme ) ) {
			$response->setVal( 'css', array_merge(
				AssetsManager::getInstance()->getURL( 'portable_infobox_builder_preview_scss' ),
				AssetsManager::getInstance()->getURL( 'portable_infobox_europa_theme_scss' )
			) );
		} else {
			$response->setVal( 'css', AssetsManager::getInstance()->getURL( 'portable_infobox_builder_preview_scss' ) );
		}

		$params = $this->getRequest()->getParams();
		if ( isset( $params[ 'title' ] ) ) {
			$infoboxes = PortableInfoboxDataService::newFromTitle(
				Title::newFromText( $params[ 'title' ], NS_TEMPLATE )
			)->getInfoboxes();

			$builderService = new PortableInfoboxBuilderService();
			if ( $builderService->isValidInfoboxArray( $infoboxes ) ) {
				$response->setVal( 'data', $builderService->translateMarkupToData( $infoboxes[ 0 ] ) );

				// There are no infoboxes yet
				if ( empty( $infoboxes ) ) {
					$response->setVal( 'isNew', true );
				}
			}
		} else {
			$status = new Status();
			$status->warning( 'no-title-provided' );
			$response->setVal( 'warnings', $status->getWarningsArray() );
		}
	}

	public function publish() {
		$response = $this->getResponse();
		$response->setFormat( WikiaResponse::FORMAT_JSON );

		$status = $this->attemptSave( $this->getRequest()->getParams() );

		$response->setVal( 'success', $status->isOK() );
		$response->setVal( 'errors', $status->getErrorsArray() );
		$response->setVal( 'warnings', $status->getWarningsArray() );
	}

	private function attemptSave( $params ) {
		$status = new Status();

		$title = $this->getTitle( $params[ 'title' ], $status );

		$status = $this->checkRequestValidity( $status );
		$status = $this->checkUserPermissions( $title, $status );

		$infoboxDataService = PortableInfoboxDataService::newFromTitle( $title );
		$infoboxes = $infoboxDataService->getInfoboxes();
		$status = $this->checkSaveEligibility( $infoboxes, $status );

		return $status->isGood() ? $this->save( $title, $params[ 'data' ], $infoboxes[ 0 ] ) : $status;
	}

	/**
	 * Wraps WikiaDispatchableObject::checkWriteRequest
	 * @param $status
	 * @return Status
	 */
	private function checkRequestValidity( &$status ) {
		try {
			$this->checkWriteRequest();
		} catch ( BadRequestException $e ) {
			$status->fatal( 'invalid-write-request' );
		}
		return $status;
	}

	/**
	 * creates Title object from provided title string. If Title object can not be created then status is updated
	 * @param $titleParam
	 * @param $status
	 * @return Title
	 * @throws MWException
	 */
	private function getTitle( $titleParam, &$status ) {
		if ( !$titleParam ) {
			$status->fatal( 'no-title-provided' );
		}

		$title = $status->isGood() ? Title::newFromText( $titleParam, NS_TEMPLATE ) : false;
		// check if title object created
		if ( $status->isGood() && !$title ) {
			$status->fatal( 'bad-title' );
		}
		return $title;
	}

	/**
	 * checks if user can edit
	 * @param $title
	 * @param $status
	 * @return mixed
	 */
	private function checkUserPermissions( $title, $status ) {
		if ( $status->isGood() && !$title->userCan( 'edit' ) ) {
			$status->fatal( 'user-cant-edit' );
		}
		return $status;
	}

	/**
	 * checks if there are more infoboxes within template.
	 * @param $infoboxes
	 * @param $status
	 * @return mixed
	 */
	private function checkSaveEligibility( $infoboxes, $status ) {
		//if there are more than one infobox in template it means that someone else added it manually.
		if ( $status->isGood() && count( $infoboxes ) > 1 ) {
			$status->fatal( 'article-usupported' );
		}
		return $status;
	}

	private function save( Title $title, $data, $oldInfobox ) {
		$article = new Article( $title );
		$editPage = new EditPage( $article );
		$editPage->initialiseForm();
		$editPage->edittime = $article->getTimestamp();

		$infoboxBuilderService = new PortableInfoboxBuilderService();
		$infoboxMarkup = $infoboxBuilderService->translateDataToMarkup( $data );
		$infoboxDocumentation = $infoboxBuilderService->getDocumentation( $infoboxMarkup, $title );

		$oldContent = $article->fetchContent();
		if ( empty( $oldInfobox ) ) {
			$editPage->textbox1 = implode( "\n", [ $infoboxMarkup, $infoboxDocumentation, $oldContent ] );
		} else {
			$oldDocumentation = $infoboxBuilderService->getDocumentation( $oldInfobox, $title );
			$content = $infoboxBuilderService->updateInfobox( $oldInfobox, $infoboxMarkup, $oldContent );
			$editPage->textbox1 = $infoboxBuilderService->updateDocumentation( $oldDocumentation, $infoboxDocumentation, $content );
		}

		$status = $editPage->internalAttemptSave( $result );

		if ($status->isGood()) {
			$this->classifyAsInfobox( $title );
		}

		return $status;
	}

	/**
	 * @param Title $title
	 * @return int
	 */
	private function classifyAsInfobox( Title $title ) {
		( new TemplateClassificationService() )->classifyTemplate(
			$this->wg->cityId,
			$title->getArticleID(),
			TemplateClassificationService::TEMPLATE_INFOBOX,
			UserTemplateClassificationService::USER_PROVIDER,
			$this->wg->user->getName()
		);
	}
}
