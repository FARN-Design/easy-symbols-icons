const { __ } = wp.i18n;
const { useSelect } = wp.data;

export default function Save({ attributes }) {
    const { className } = attributes;

	console.log(className);

    return (
        <div className="selected-icon-wrapper">
            {className ? (
                <span className={className} style={{ fontSize: '30px' }}></span>
            ) : (
                <p>{__('No Icon Selected', 'easyicon')}</p>
            )}
        </div>
    );
}

