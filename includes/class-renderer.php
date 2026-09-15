<?php
/**
 * Gemeinsame HTML-Ausgabe für Block, Shortcode und Widget.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Renderer {

	const DWD_URL = 'https://www.dwd.de/DE/wetter/warnungen_gemeinden/warnWetter_node.html';

	/**
	 * Standardwerte aller Optionen (identisch mit block.json).
	 */
	public static function defaults() {
		return array(
			'regionId'       => '',
			'title'          => __( 'Weather alerts', 'wetterwarner' ),
			'introText'      => __( 'Weather alerts for %region%', 'wetterwarner' ),
			'noWarningsText' => __( 'No weather alerts for %region%', 'wetterwarner' ),
			'maxWarnings'    => 3,
			'showAlways'     => false,
			'showValidity'   => true,
			'showDetails'    => true,
			'showIcons'      => true,
			'showColors'     => true,
			'linkWarnings'   => false,
			'hideDuplicates' => true,
			'showSource'     => true,
			'mapSize'        => 0,
			'mapRegion'      => 'auto',
		);
	}

	/**
	 * Bereinigt Optionen aus Block, Shortcode oder Widget.
	 *
	 * @param array $atts Rohwerte.
	 */
	public static function sanitize( array $atts ) {
		$defaults = self::defaults();
		$atts     = array_merge( $defaults, array_intersect_key( $atts, $defaults ) );

		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				$atts[ $key ] = filter_var( $atts[ $key ], FILTER_VALIDATE_BOOLEAN );
			} elseif ( is_int( $default ) ) {
				$atts[ $key ] = absint( $atts[ $key ] );
			} else {
				$atts[ $key ] = sanitize_text_field( (string) $atts[ $key ] );
			}
		}

		$atts['mapSize']   = min( 100, $atts['mapSize'] );
		$atts['mapRegion'] = array_key_exists( $atts['mapRegion'], Map::choices() ) ? $atts['mapRegion'] : 'auto';
		if ( Regions::DEMO !== $atts['regionId'] && ! Regions::is_valid_id( $atts['regionId'] ) ) {
			$atts['regionId'] = '';
		}

		return $atts;
	}

	/**
	 * Erzeugt das HTML.
	 *
	 * @param array $atts    Optionen.
	 * @param array $context wrapper (Attribut-String), before_title, after_title, preview (bool).
	 */
	public static function render( array $atts, array $context = array() ) {
		$atts    = self::sanitize( $atts );
		$context = array_merge(
			array(
				'wrapper'      => 'class="wetterwarner"',
				'before_title' => '<h3 class="wetterwarner__title">',
				'after_title'  => '</h3>',
				'preview'      => false,
			),
			$context
		);

		wp_enqueue_style( 'wetterwarner-warnings-style' );

		$region = $atts['regionId'] ? Regions::get( $atts['regionId'] ) : null;
		if ( ! $region ) {
			return self::notice( __( 'Please select a warning region in the Wetterwarner settings.', 'wetterwarner' ), $context );
		}

		$map_code = $atts['mapSize'] > 0 ? Map::resolve_code( $atts['mapRegion'], $region ) : '';
		Plugin::track_usage( $region['id'], $map_code );

		$warnings = Source::get_warnings( $region['id'] );
		$error    = is_wp_error( $warnings ) ? $warnings : null;
		$warnings = $error ? array() : self::limit( $warnings, $atts );

		if ( ! $warnings && ! $error && ! $atts['showAlways'] ) {
			return $context['preview']
				? self::notice( __( 'There are currently no warnings. The block is hidden on the website until a warning is issued (option "Show always").', 'wetterwarner' ), $context, false )
				: '';
		}

		$replace = static function ( $text ) use ( $region ) {
			return str_replace( '%region%', $region['name'], $text );
		};

		$html = '<div ' . $context['wrapper'] . '>';

		if ( '' !== $atts['title'] ) {
			$html .= $context['before_title'] . esc_html( $replace( $atts['title'] ) ) . $context['after_title'];
		}

		if ( $error ) {
			$html .= '<p class="wetterwarner__error">' . esc_html__( 'Weather alerts are currently unavailable.', 'wetterwarner' ) . ' ';
			$html .= '<a href="' . esc_url( self::DWD_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'View alerts at dwd.de', 'wetterwarner' ) . '</a>';
			if ( current_user_can( 'manage_options' ) ) {
				$html .= '<br><small>' . esc_html( $error->get_error_message() ) . '</small>';
			}
			$html .= '</p>';
		} elseif ( $warnings ) {
			if ( '' !== $atts['introText'] ) {
				$html .= '<p class="wetterwarner__intro">' . esc_html( $replace( $atts['introText'] ) ) . '</p>';
			}
			$html .= '<ul class="wetterwarner__list">';
			foreach ( $warnings as $warning ) {
				$html .= self::item( $warning, $atts );
			}
			$html .= '</ul>';
		} elseif ( '' !== $atts['noWarningsText'] ) {
			$html .= '<p class="wetterwarner__none">' . esc_html( $replace( $atts['noWarningsText'] ) ) . '</p>';
		}

		if ( $map_code ) {
			$html .= self::map( $map_code, $atts, $region );
		}

		if ( $atts['showSource'] ) {
			$html .= '<p class="wetterwarner__source">' . sprintf(
				/* translators: %s: link to Deutscher Wetterdienst */
				esc_html__( 'Source: %s', 'wetterwarner' ),
				'<a href="https://www.dwd.de/" target="_blank" rel="noopener">Deutscher Wetterdienst</a>'
			) . '</p>';
		}

		return $html . '</div>';
	}

	private static function limit( array $warnings, array $atts ) {
		if ( $atts['hideDuplicates'] ) {
			$seen     = array();
			$warnings = array_values(
				array_filter(
					$warnings,
					static function ( $w ) use ( &$seen ) {
						$key = $w['event'] . '|' . (int) $w['prior'];
						if ( isset( $seen[ $key ] ) ) {
							return false;
						}
						return $seen[ $key ] = true;
					}
				)
			);
		}
		return $atts['maxWarnings'] > 0 ? array_slice( $warnings, 0, $atts['maxWarnings'] ) : $warnings;
	}

	private static function item( array $w, array $atts ) {
		$classes = array(
			'wetterwarner__item',
			'wetterwarner__item--stage-' . (int) $w['stage'],
			'wetterwarner__item--' . sanitize_html_class( $w['type'] ),
		);
		if ( $atts['showColors'] ) {
			$classes[] = 'has-stage-color';
		}
		if ( $w['prior'] ) {
			$classes[] = 'is-prior';
		}

		$event = esc_html( $w['event'] );
		if ( $atts['linkWarnings'] ) {
			$event = '<a href="' . esc_url( self::DWD_URL ) . '" target="_blank" rel="noopener">' . $event . '</a>';
		}

		$html  = '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<p class="wetterwarner__headline">';
		if ( $atts['showIcons'] ) {
			$html .= Icons::svg( $w['type'] );
		}
		$html .= '<span class="wetterwarner__event">' . $event . '</span>';
		$html .= '<span class="screen-reader-text">' . esc_html( sprintf( /* translators: %d: warning level 1-4 */ __( 'Warning level %d', 'wetterwarner' ), $w['stage'] ) ) . '</span>';
		$html .= '</p>';

		if ( $w['prior'] ) {
			$html .= '<p class="wetterwarner__badge">' . esc_html__( 'Prior information', 'wetterwarner' ) . '</p>';
		}

		if ( $atts['showValidity'] ) {
			$html .= '<p class="wetterwarner__time">' . esc_html( self::validity( $w ) ) . '</p>';
		}

		if ( $atts['showDetails'] && ( $w['description'] || $w['instruction'] ) ) {
			$html .= '<details class="wetterwarner__details"><summary>' . esc_html__( 'Details', 'wetterwarner' ) . '</summary>';
			if ( $w['headline'] ) {
				$html .= '<p><strong>' . esc_html( $w['headline'] ) . '</strong></p>';
			}
			if ( $w['description'] ) {
				$html .= '<p>' . esc_html( $w['description'] ) . '</p>';
			}
			if ( $w['instruction'] ) {
				$html .= '<p>' . nl2br( esc_html( trim( $w['instruction'] ) ) ) . '</p>';
			}
			$html .= '</details>';
		}

		return $html . '</li>';
	}

	private static function validity( array $w ) {
		$tz   = wp_timezone();
		$from = $w['start'] ? wp_date( __( 'D, m/d, g:i a', 'wetterwarner' ), $w['start'], $tz ) : '';

		if ( ! $w['end'] ) {
			/* translators: %s: start date and time */
			$text = $from ? sprintf( __( 'From %s until further notice', 'wetterwarner' ), $from ) : __( 'Until further notice', 'wetterwarner' );
		} else {
			$same_day = wp_date( 'Ymd', $w['start'], $tz ) === wp_date( 'Ymd', $w['end'], $tz );
			$until    = wp_date( $same_day ? __( 'g:i a', 'wetterwarner' ) : __( 'D, m/d, g:i a', 'wetterwarner' ), $w['end'], $tz );
			/* translators: 1: start date and time, 2: end date or time */
			$text = sprintf( __( '%1$s – %2$s', 'wetterwarner' ), $from, $until );
		}

		if ( $w['altitude_start'] && $w['altitude_end'] ) {
			/* translators: 1: lower altitude, 2: upper altitude in meters */
			$text .= ' · ' . sprintf( __( 'between %1$d m and %2$d m', 'wetterwarner' ), $w['altitude_start'], $w['altitude_end'] );
		} elseif ( $w['altitude_start'] ) {
			/* translators: %d: altitude in meters */
			$text .= ' · ' . sprintf( __( 'above %d m', 'wetterwarner' ), $w['altitude_start'] );
		} elseif ( $w['altitude_end'] ) {
			/* translators: %d: altitude in meters */
			$text .= ' · ' . sprintf( __( 'below %d m', 'wetterwarner' ), $w['altitude_end'] );
		}

		return $text;
	}

	private static function map( $code, array $atts, array $region ) {
		$map = Map::get( $code );
		if ( ! $map ) {
			return '';
		}

		$img = sprintf(
			'<img src="%s" width="%d" height="%d" alt="%s" loading="lazy" decoding="async" style="width:%d%%;height:auto">',
			esc_url( $map['url'] ),
			$map['width'],
			$map['height'],
			/* translators: %s: map area */
			esc_attr( sprintf( __( 'Current weather warning map: %s', 'wetterwarner' ), Map::choices()[ $code ] ) ),
			$atts['mapSize']
		);

		if ( $atts['linkWarnings'] ) {
			$img = '<a href="' . esc_url( self::DWD_URL ) . '" target="_blank" rel="noopener">' . $img . '</a>';
		}

		return '<figure class="wetterwarner__map">' . $img . '</figure>';
	}

	private static function notice( $message, array $context, $editors_only = true ) {
		if ( $editors_only && ! $context['preview'] && ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<div ' . $context['wrapper'] . '><p class="wetterwarner__notice">' . esc_html( $message ) . '</p></div>';
	}
}

/**
 * Inline-SVG-Icons (angelehnt an Lucide, ISC-Lizenz).
 */
class Icons {

	const PATHS = array(
		'thunderstorm' => '<path d="M6 16.326A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 .5 8.973"/><path d="m13 12-3 5h4l-3 5"/>',
		'wind'         => '<path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/>',
		'rain'         => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 14v6"/><path d="M8 14v6"/><path d="M12 16v6"/>',
		'snow'         => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M8 15h.01M8 19h.01M12 17h.01M12 21h.01M16 15h.01M16 19h.01"/>',
		'fog'          => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 17H7"/><path d="M17 21H9"/>',
		'frost'        => '<path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/>',
		'ice'          => '<path d="M12 2v20M3.34 7l17.32 10M3.34 17l17.32-10"/><path d="m9 4 3 2 3-2M9 20l3-2 3 2"/>',
		'thaw'         => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
		'heat'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
		'uv'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
		'other'        => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
	);

	public static function svg( $type ) {
		$paths = isset( self::PATHS[ $type ] ) ? self::PATHS[ $type ] : self::PATHS['other'];
		return '<svg class="wetterwarner__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}
}
