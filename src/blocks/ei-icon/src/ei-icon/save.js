const { __ } = wp.i18n;
const { useBlockProps } = wp.blockEditor;

export default function Save({ attributes }) {
    const { className, fontSize, lineHeight, backgroundColor, textColor, align } = attributes;

    return (
        <div
            {...useBlockProps.save()}
            className={`selected-icon-wrapper align${align}`}
            style={{
                fontSize: fontSize ? `${fontSize}px` : undefined,
                lineHeight: lineHeight ? `${lineHeight}px` : undefined,
                backgroundColor: backgroundColor || undefined,
                color: textColor || undefined,
            }}
        >
            {className ? (
                <span className={className}></span>
            ) : (
                <p>{__('No Icon Selected', 'easyicon')}</p>
            )}
        </div>
    );
}
