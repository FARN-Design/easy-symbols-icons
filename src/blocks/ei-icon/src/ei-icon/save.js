import { useBlockProps } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save();
	return (
		<div { ...blockProps }>
			<span class="icon-dashicons-products">t</span>
		</div>
	);
}
