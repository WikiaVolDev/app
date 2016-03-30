<?php

class AdTargeting {

	const ADULTS_ONLY = 'ao';
	const EARLY_CHILDHOOD = 'ec';
	const EVERYONE = 'everyone';
	const EVERYONE_10_PLUS = 'ec10';
	const MATURE = 'mature';
	const RATING_PENDING = 'rp';
	const TEEN = 'teen';

	/**
	 * @return string
	 */
	static public function getEsrbRating() {
		global $wgWikiDirectedAtChildrenByFounder, $wgWikiDirectedAtChildrenByStaff;

		$directedAtChildren = $wgWikiDirectedAtChildrenByFounder || $wgWikiDirectedAtChildrenByStaff;
		$rating = $directedAtChildren ? self::EARLY_CHILDHOOD : self::TEEN;
		$dartRating = self::getEsrbRatingFromDartKeyValues();

		return $dartRating !== null ? $dartRating : $rating;
	}

	/**
	 * @return null|string
	 */
	static private function getEsrbRatingFromDartKeyValues() {
		global $wgDartCustomKeyValues;

		$dartRating = null;
		$pairs = explode(';', $wgDartCustomKeyValues);

		foreach ($pairs as $pair) {
			list($key, $value) = explode('=', $pair);
			if ($key === 'esrb') {
				$dartRating = $value;
			}
		}

		return $dartRating;
	}

}
