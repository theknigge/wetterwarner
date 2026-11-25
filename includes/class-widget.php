<?php
/**
 * Klassisches Widget (für Themes ohne Block-Widgets).
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Widget;

defined( 'ABSPATH' ) || exit;

class Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'wetterwarner_widget',
			'Wetterwarner',
			array(
				'description'                 => __( 'Displays official weather alerts of the Deutscher Wetterdienst and, if desired, a warning map.', 'wetterwarner' ),
				'customize_selective_refresh' => true,
				'show_instance_in_rest'       => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		$context = array(
			'wrapper'      => 'class="wetterwarner wetterwarner--widget"',
			'before_title' => $args['before_title'],
			'after_title'  => $args['after_title'],
		);
		// Im Customizer nicht nachladen, sonst wird die Vorschau überschrieben.
		$live = is_customize_preview() ? null : array(
			'a' => (array) $instance,
			'c' => $context,
			'b' => $args['before_widget'],
			'e' => $args['after_widget'],
		);
		$html = Renderer::render( (array) $instance, $context + array( 'live' => $live ) );

		if ( '' === $html ) {
			return;
		}
		// Der Platzhalter ohne Warnungen bekommt keinen Widget-Rahmen.
		if ( 0 === strpos( $html, '<div hidden' ) ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo $args['before_widget'] . $html . $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes, Theme liefert before/after.
	}

	public function update( $new_instance, $old_instance ) {
		// Ohne angehakte Warnart sendet das Formular nichts.
		$values = array( 'eventTypes' => '' );
		foreach ( Renderer::defaults() as $key => $default ) {
			if ( is_bool( $default ) ) {
				$values[ $key ] = ! empty( $new_instance[ $key ] );
			} elseif ( isset( $new_instance[ $key ] ) ) {
				$values[ $key ] = wp_unslash( $new_instance[ $key ] );
			}
		}
		return Renderer::sanitize( $values );
	}

	public function form( $instance ) {
		$atts   = Renderer::sanitize( (array) $instance );
		$region = $atts['regionId'] ? Regions::get( $atts['regionId'] ) : null;
		$label  = $region ? Rest::prepare( $region )['label'] : '';
		$list   = $this->get_field_id( 'region-results' );
		?>
		<div class="wetterwarner-widget-form">
			<?php // Kein <p>: Ein Absatz darf keine Liste enthalten, der Browser würde ihn vorher schließen. ?>
			<div class="wetterwarner-region">
				<label for="<?php echo esc_attr( $this->get_field_id( 'region-search' ) ); ?>"><strong><?php esc_html_e( 'Warning region', 'wetterwarner' ); ?></strong></label>
				<input
					type="search"
					class="widefat wetterwarner-region-search"
					id="<?php echo esc_attr( $this->get_field_id( 'region-search' ) ); ?>"
					value="<?php echo esc_attr( $label ); ?>"
					placeholder="<?php esc_attr_e( 'Search town or district …', 'wetterwarner' ); ?>"
					autocomplete="off"
					role="combobox"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $list ); ?>"
				/>
				<input type="hidden" class="wetterwarner-region-id" name="<?php echo esc_attr( $this->get_field_name( 'regionId' ) ); ?>" value="<?php echo esc_attr( $atts['regionId'] ); ?>" />
				<ul class="wetterwarner-region-results" id="<?php echo esc_attr( $list ); ?>" role="listbox" hidden></ul>
				<small><?php esc_html_e( 'Enter at least 2 characters. Search "demo" for sample warnings.', 'wetterwarner' ); ?></small>
			</div>

			<?php
			$this->text( 'title', __( 'Title', 'wetterwarner' ), $atts );
			$this->text( 'introText', __( 'Introduction text', 'wetterwarner' ), $atts );
			$this->text( 'noWarningsText', __( 'Text when there are no alerts', 'wetterwarner' ), $atts );
			?>
			<p><small><?php esc_html_e( '%region% is replaced by the name of the region.', 'wetterwarner' ); ?></small></p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'maxWarnings' ) ); ?>"><?php esc_html_e( 'Maximum number of alerts (0 = all)', 'wetterwarner' ); ?></label>
				<input class="tiny-text" type="number" min="0" max="50" id="<?php echo esc_attr( $this->get_field_id( 'maxWarnings' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'maxWarnings' ) ); ?>" value="<?php echo esc_attr( $atts['maxWarnings'] ); ?>" />
			</p>

			<?php
			$this->checkbox( 'showAlways', __( 'Show even without alerts', 'wetterwarner' ), $atts );
			$this->checkbox( 'showValidity', __( 'Show validity period', 'wetterwarner' ), $atts );
			$this->checkbox( 'showDetails', __( 'Show expandable details', 'wetterwarner' ), $atts );
			$this->checkbox( 'showIcons', __( 'Show icons', 'wetterwarner' ), $atts );
			$this->checkbox( 'showColors', __( 'Color by warning level', 'wetterwarner' ), $atts );
			$this->checkbox( 'hideDuplicates', __( 'Hide duplicate alerts', 'wetterwarner' ), $atts );
			$this->checkbox( 'linkWarnings', __( 'Link alerts to dwd.de', 'wetterwarner' ), $atts );
			$this->checkbox( 'showPrior', __( 'Show prior information', 'wetterwarner' ), $atts );
			?>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'minStage' ) ); ?>"><?php esc_html_e( 'Minimum warning level', 'wetterwarner' ); ?></label>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'minStage' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'minStage' ) ); ?>">
					<?php foreach ( Renderer::stage_labels() as $value => $name ) : ?>
						<option value="<?php echo (int) $value; ?>" <?php selected( $atts['minStage'], $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Types of warnings (none selected = all)', 'wetterwarner' ); ?></legend>
				<?php foreach ( Renderer::type_labels() as $value => $name ) : ?>
					<label style="display:inline-block;margin:0 8px 4px 0"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'eventTypes' ) ); ?>[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, explode( ',', $atts['eventTypes'] ), true ) ); ?>> <?php echo esc_html( $name ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<?php
			?>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'mapSize' ) ); ?>"><?php esc_html_e( 'Map width in % (0 = no map)', 'wetterwarner' ); ?></label>
				<input class="tiny-text" type="number" min="0" max="100" step="5" id="<?php echo esc_attr( $this->get_field_id( 'mapSize' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'mapSize' ) ); ?>" value="<?php echo esc_attr( $atts['mapSize'] ); ?>" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'mapRegion' ) ); ?>"><?php esc_html_e( 'Map area', 'wetterwarner' ); ?></label>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'mapRegion' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'mapRegion' ) ); ?>">
					<?php foreach ( Map::choices() as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $atts['mapRegion'], $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<?php
		return '';
	}

	private function text( $key, $label, array $atts ) {
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" type="text" id="%1$s" name="%3$s" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( $key ) ),
			esc_html( $label ),
			esc_attr( $this->get_field_name( $key ) ),
			esc_attr( $atts[ $key ] )
		);
	}

	private function checkbox( $key, $label, array $atts ) {
		printf(
			'<p><input class="checkbox" type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> <label for="%1$s">%4$s</label></p>',
			esc_attr( $this->get_field_id( $key ) ),
			esc_attr( $this->get_field_name( $key ) ),
			checked( $atts[ $key ], true, false ),
			esc_html( $label )
		);
	}
}
