import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import "./style.scss";
import { useBlockProps } from '@wordpress/block-editor';

const deprecated = [
	{
		attributes: {
			className: {
				type: 'string',
				source: 'attribute',
				selector: 'span',
				attribute: 'class',
			},
			align: {
				type: 'string',
			},

			backgroundColor: {
				type: 'string',
			},
			textColor: {
				type: 'string',
			},
			style: {
				type: 'object',
			},
			fontSize: {
				type: 'string',
			},
		},

		save({ attributes }) {
			const { className, style } = attributes;

			const blockProps = useBlockProps.save({
				className: `selected-icon-wrapper alignundefined ${className || ''}`,
				style: style || {},
			});

			return (
				<div {...blockProps}>
					<span className={className}></span>
				</div>
			);
		},

		migrate(attributes) {
			const extractIconClass = (cls) => {
				if (!cls) return '';
				const match = cls.match(/eics-[^\s]+__[^\s]+/);
				return match ? match[0] : '';
			};

			return {
				iconClass: extractIconClass(attributes.className),
				backgroundColor: attributes.backgroundColor,
				textColor: attributes.textColor,
				style: attributes.style || {},
				fontSize: attributes.fontSize,
				align: undefined,
			};
		}
	},

	{
		save() {
			return null;
		},
		migrate() {
			return {
				iconClass: '',
			};
		},
	},
];

registerBlockType('easy-symbols-icons/eics-symbols-icons', {
	edit: Edit,
	save: () => null,
	deprecated,
});