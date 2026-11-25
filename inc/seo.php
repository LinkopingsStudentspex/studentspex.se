<?php
/**
 * Search and sharing helpers: event structured data and font preloading.
 *
 * @package Studentspex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request shows the current production's dates.
 *
 * True on pages that contain the "show-dates" block and on the current production's own page.
 *
 * @return bool
 */
function studentspex_is_event_page() {
	if ( ! is_singular() ) {
		return false;
	}
	$current = studentspex_current_show_id();
	if ( ! $current ) {
		return false;
	}
	return is_singular( 'spex_uppsattning' ) ? get_queried_object_id() === $current : has_block( 'studentspex/show-dates', get_queried_object() );
}

/**
 * Number from a price field such as "220", "220 kr" or "220:-".
 *
 * @param string $price Raw price.
 * @return string Digits, or empty.
 */
function studentspex_price_number( $price ) {
	return preg_match( '/\d+(?:[.,]\d+)?/', (string) $price, $m ) ? str_replace( ',', '.', $m[0] ) : '';
}

/**
 * schema.org TheaterEvent pieces, one per performance of the current production.
 *
 * @return array[]
 */
function studentspex_event_schema() {
	$show = studentspex_show( studentspex_current_show_id() );
	if ( ! $show || ! $show['performances'] ) {
		return array();
	}

	$tz       = wp_timezone();
	$name     = $show['title'] . ( $show['subtitle'] ? ' – ' . $show['subtitle'] : '' );
	$page_url = is_singular() ? get_permalink( get_queried_object_id() ) : $show['permalink'];
	$image_id = $show['poster_id'] ? $show['poster_id'] : $show['cover_id'];
	$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
	$org      = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	$location = array(
		'@type'   => 'Place',
		'name'    => $show['venue'],
		'address' => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $show['venue_address'],
			'addressLocality' => 'Linköping',
			'addressCountry'  => 'SE',
		),
	);

	$offers = array();
	$prices = array(
		'Ordinarie'   => $show['price_regular'],
		'Rabatterat'  => $show['price_reduced'],
		'Stöd spexet' => $show['price_support'],
	);
	foreach ( $prices as $label => $raw ) {
		$price = studentspex_price_number( $raw );
		if ( '' === $price ) {
			continue;
		}
		$offer = array(
			'@type'         => 'Offer',
			'name'          => $label,
			'price'         => $price,
			'priceCurrency' => 'SEK',
			'url'           => $show['ticket_url'] ? $show['ticket_url'] : $page_url,
		);
		if ( $show['ticket_url'] ) {
			$offer['availability'] = 'https://schema.org/InStock';
		}
		$offers[] = $offer;
	}

	$events = array();
	foreach ( $show['performances'] as $i => $perf ) {
		$time = preg_match( '/(\d{1,2})[.:](\d{2})/', (string) $perf['time'], $m ) ? sprintf( '%02d:%02d', $m[1], $m[2] ) : '19:00';
		try {
			$start = new DateTimeImmutable( $perf['date'] . ' ' . $time, $tz );
		} catch ( Exception $e ) {
			continue;
		}
		$event = array(
			'@type'               => 'TheaterEvent',
			'@id'                 => $page_url . '#event-' . $perf['date'],
			'name'                => $name,
			'url'                 => $page_url,
			'startDate'           => $start->format( 'c' ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'inLanguage'          => 'sv-SE',
			'location'            => $location,
			'organizer'           => $org,
			'performer'           => $org,
		);
		if ( $show['excerpt'] ) {
			$event['description'] = wp_strip_all_tags( $show['excerpt'] );
		}
		if ( $image ) {
			$event['image'] = $image;
		}
		if ( $offers ) {
			$event['offers'] = $offers;
		}
		$events[] = $event;
	}
	return $events;
}

/**
 * Add the events to Yoast's schema graph.
 *
 * @param array $graph Schema pieces.
 * @return array
 */
function studentspex_yoast_event_graph( $graph ) {
	if ( studentspex_is_event_page() ) {
		$graph = array_merge( $graph, studentspex_event_schema() );
	}
	return $graph;
}
add_filter( 'wpseo_schema_graph', 'studentspex_yoast_event_graph' );

/**
 * Without Yoast, print the events as a JSON-LD block of their own.
 */
function studentspex_print_event_schema() {
	if ( defined( 'WPSEO_VERSION' ) || ! studentspex_is_event_page() ) {
		return;
	}
	$events = studentspex_event_schema();
	if ( ! $events ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $events,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'studentspex_print_event_schema' );

/**
 * Preload the display font used by every page's main heading, so the hero title
 * doesn't swap fonts after first paint.
 */
function studentspex_preload_fonts() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( STUDENTSPEX_URI . '/assets/fonts/fraunces-normal-latin.woff2' )
	);
}
add_action( 'wp_head', 'studentspex_preload_fonts', 2 );
