<?php
/**
 * One sponsor logo.
 *
 * @package Studentspex
 * @var array $attributes
 */

$src = $attributes['imageId'] ? wp_get_attachment_image_url( (int) $attributes['imageId'], 'medium' ) : '';
if ( ! $src ) {
	$src = $attributes['imageUrl'];
}
if ( ! $src ) {
	return;
}
$alt = trim( $attributes['alt'] );
// A linked logo needs a text alternative, or the link has no accessible name.
// Fall back to the sponsor's domain when no alt text has been entered.
if ( '' === $alt && $attributes['link'] ) {
	$alt = preg_replace( '/^www\./', '', (string) wp_parse_url( $attributes['link'], PHP_URL_HOST ) );
}
$img = sprintf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $src ), esc_attr( $alt ) );
if ( $attributes['link'] ) {
	$img = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $attributes['link'] ), $img );
}
echo '<li>' . $img . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
