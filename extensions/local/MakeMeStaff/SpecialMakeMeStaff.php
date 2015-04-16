<?php
class SpecialMakeMeStaff extends SpecialPage {
	function __construct() {parent::__construct('MakeMeStaff');}

	function execute($par) {
		global $wgUser;
		$output = $this->getOutput();
		$this->setHeaders();

		if(!$wgUser->isLoggedIn()) {
			$output->addWikiText(wfMessage('makemestaff-login')->text());
			return;
		}

		$groups = $wgUser->getGroups();

		if(!array_search('staff', $groups)) {$wgUser->addGroup('staff');}
		if(!array_search('util', $groups)) {$wgUser->addGroup('util');}

		$output->addWikiText(wfMessage('makemestaff-success')->text());
	}
}
?>
