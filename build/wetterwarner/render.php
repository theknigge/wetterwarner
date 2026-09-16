<?php
/**
 * Server-Rendering des Blocks "wetterwarner/warnings".
 *
 * @package Wetterwarner
 *
 * @var array $attributes Block-Attribute.
 */

defined( 'ABSPATH' ) || exit;

$wetterwarner_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;
$wetterwarner_level   = isset( $attributes['titleLevel'] ) ? min( 6, max( 2, (int) $attributes['titleLevel'] ) ) : 3;

$wetterwarner_html = Wetterwarner\Renderer::render(
	$attributes,
	array(
		// Im Editor liefert der äußere Block-Wrapper bereits Farben und Abstände.
		'wrapper'      => $wetterwarner_preview ? 'class="wetterwarner"' : get_block_wrapper_attributes( array( 'class' => 'wetterwarner' ) ),
		'before_title' => '<h' . $wetterwarner_level . ' class="wetterwarner__title">',
		'after_title'  => '</h' . $wetterwarner_level . '>',
		'preview'      => $wetterwarner_preview,
	)
);

// Renderer::render() escapt alle dynamischen Werte selbst.
echo $wetterwarner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
