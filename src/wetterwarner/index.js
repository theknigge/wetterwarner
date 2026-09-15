import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

const icon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		// Der Editor setzt für Block-Icons fill: currentColor.
		style={ { fill: 'none' } }
	>
		<path d="M6 16.326A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 .5 8.973" />
		<path d="m13 12-3 5h4l-3 5" />
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => null,
} );
