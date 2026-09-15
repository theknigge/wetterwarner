import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import RegionPicker from './region-picker';

const MAP_REGIONS = [
	{ value: 'auto', label: __( 'Federal state of the region', 'wetterwarner' ) },
	{ value: 'de', label: __( 'Germany', 'wetterwarner' ) },
	{ value: 'baw', label: 'Baden-Württemberg' },
	{ value: 'bay', label: 'Bayern' },
	{ value: 'bbb', label: 'Berlin / Brandenburg' },
	{ value: 'hes', label: 'Hessen' },
	{ value: 'mvp', label: 'Mecklenburg-Vorpommern' },
	{ value: 'nib', label: 'Niedersachsen / Bremen' },
	{ value: 'nrw', label: 'Nordrhein-Westfalen' },
	{ value: 'rps', label: 'Rheinland-Pfalz / Saarland' },
	{ value: 'sac', label: 'Sachsen' },
	{ value: 'saa', label: 'Sachsen-Anhalt' },
	{ value: 'shh', label: 'Schleswig-Holstein / Hamburg' },
	{ value: 'thu', label: 'Thüringen' },
];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	// Texte ohne gespeicherten Wert nutzen die (übersetzten) Standardtexte.
	const texts = {
		title: __( 'Weather alerts', 'wetterwarner' ),
		introText: __( 'Weather alerts for %region%', 'wetterwarner' ),
		noWarningsText: __( 'No weather alerts for %region%', 'wetterwarner' ),
	};
	const textValue = ( key ) => attributes[ key ] ?? texts[ key ];

	const picker = (
		<RegionPicker
			value={ attributes.regionId }
			label={ attributes.regionLabel }
			onChange={ ( regionId, regionLabel ) => setAttributes( { regionId, regionLabel } ) }
		/>
	);

	const toggle = ( key, label ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ label }
			checked={ !! attributes[ key ] }
			onChange={ ( value ) => setAttributes( { [ key ]: value } ) }
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Region', 'wetterwarner' ) }>{ picker }</PanelBody>

				<PanelBody title={ __( 'Texts', 'wetterwarner' ) } initialOpen={ false }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Title', 'wetterwarner' ) }
						value={ textValue( 'title' ) }
						onChange={ ( title ) => setAttributes( { title } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Title level', 'wetterwarner' ) }
						value={ attributes.titleLevel }
						options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( { value: level, label: `H${ level }` } ) ) }
						onChange={ ( level ) => setAttributes( { titleLevel: Number( level ) } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Introduction text', 'wetterwarner' ) }
						value={ textValue( 'introText' ) }
						onChange={ ( introText ) => setAttributes( { introText } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Text when there are no alerts', 'wetterwarner' ) }
						value={ textValue( 'noWarningsText' ) }
						onChange={ ( noWarningsText ) => setAttributes( { noWarningsText } ) }
						help={ __( '%region% is replaced by the name of the region. Empty fields are hidden.', 'wetterwarner' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Display', 'wetterwarner' ) } initialOpen={ false }>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Maximum number of alerts (0 = all)', 'wetterwarner' ) }
						value={ attributes.maxWarnings }
						min={ 0 }
						max={ 20 }
						onChange={ ( maxWarnings ) => setAttributes( { maxWarnings: maxWarnings ?? 3 } ) }
					/>
					{ toggle( 'showAlways', __( 'Show even without alerts', 'wetterwarner' ) ) }
					{ toggle( 'showValidity', __( 'Show validity period', 'wetterwarner' ) ) }
					{ toggle( 'showDetails', __( 'Show expandable details', 'wetterwarner' ) ) }
					{ toggle( 'showIcons', __( 'Show icons', 'wetterwarner' ) ) }
					{ toggle( 'showColors', __( 'Color by warning level', 'wetterwarner' ) ) }
					{ toggle( 'hideDuplicates', __( 'Hide duplicate alerts', 'wetterwarner' ) ) }
					{ toggle( 'linkWarnings', __( 'Link alerts to dwd.de', 'wetterwarner' ) ) }
					{ toggle( 'showSource', __( 'Show source', 'wetterwarner' ) ) }
				</PanelBody>

				<PanelBody title={ __( 'Warning map', 'wetterwarner' ) } initialOpen={ false }>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Map width in % (0 = no map)', 'wetterwarner' ) }
						value={ attributes.mapSize }
						min={ 0 }
						max={ 100 }
						step={ 5 }
						onChange={ ( mapSize ) => setAttributes( { mapSize: mapSize ?? 0 } ) }
					/>
					{ attributes.mapSize > 0 && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Map area', 'wetterwarner' ) }
							value={ attributes.mapRegion }
							options={ MAP_REGIONS }
							onChange={ ( mapRegion ) => setAttributes( { mapRegion } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ attributes.regionId ? (
					<Disabled>
						<ServerSideRender block="wetterwarner/warnings" attributes={ attributes } skipBlockSupportAttributes />
					</Disabled>
				) : (
					<Placeholder
						label="Wetterwarner"
						instructions={ __( 'Select the warning region for which official alerts of the Deutscher Wetterdienst should be displayed.', 'wetterwarner' ) }
					>
						<div style={ { width: '100%' } }>{ picker }</div>
					</Placeholder>
				) }
			</div>
		</>
	);
}
