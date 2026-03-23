import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import "./style.scss";

const deprecated = [
	{
		attributes: {
			className: {
				type: 'string',
			},
			align: {
				type: 'string',
			},
		},

		save({ attributes }) {
			const { className, align } = attributes;

			return (
				<div className={`selected-icon-wrapper align${align}`}>
					<span className={className}></span>
				</div>
			);
		},

		migrate(attributes) {
			const { className, align } = attributes;

			return {
				iconClass: className,
				align: align && align !== 'undefined' ? align : undefined,
			};
		},
	},
];

registerBlockType('easy-symbols-icons/eics-symbols-icons', {
	edit: Edit,
	save: () => null,
	deprecated,
});