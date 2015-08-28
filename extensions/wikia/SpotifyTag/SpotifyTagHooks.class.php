<?php

class SpotifyTagHooks {
	
	/*
	 * onParserFirstCallInit
	 *
	 * Registers the <spotify> tag with the parser and sets its callback
	 *
	 * @param $parser - The parser
	 * @return true
	 */
	static public function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'spotify', [ __CLASS__, 'parseSpotifyTag' ] );
		return true;
	}
	
	/*
	 * parseSpotifyTag
	 *
	 * Parses the spotify tag. Checks to ensure the required attributes are there. 
	 *   Then constructs the HTML after seeing which attributes are in use. 
	 *
	 * @param $input - not used
	 * @param $args - The attributes to the tag in an assoc array
	 * @param $parser - not used
	 * @param $frame - not used
	 * @return $html - The HTML for the spotify tag
	 */
	static public function parseSpotifyTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		if ( empty( $args['uri'] ) ) {
			$html = wfMessage( 'spotifytag-nouri' )->parse();
			return "<strong class='error'>$html</strong>";
		}
		
		if ( !empty( $args['width'] ) ) {
			$attributes['width'] = $args['width'];
		} else {
			$attributes['width'] = '300'; // spotify preferred default
		}
		
		if ( !empty( $args['height'] ) ) {
			$attributes['height'] = $args['height'];
		} else {
			$attributes['height'] = '380'; // spotify preferred default
		}
		
		$attributes['src'] = 'https://embed.spotify.com/?uri=' . urlencode( $args['uri'] );
		if ( !empty( $args['theme'] ) ) {
			$attributes['src'] = $attributes['src'] . '&theme=' . urlencode( $args['theme'] );
		}
		
		if ( !empty( $args['view'] ) ) {
			$attributes['src'] = $attributes['src'] . '&view=' . urlencode( $args['view'] );
		}
		
		$attributes['frameborder'] = '0';
		$attributes['allowtransparency'] = 'true';
		
		$html = Html::element( 
			'iframe', 
			$attributes, 
			wfMessage( 'spotifytag-failed-to-render' )->plain() 
		);
		
		return $html;
	}
}
