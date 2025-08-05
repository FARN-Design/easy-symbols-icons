const { __ } = wp.i18n;
const { useState, useEffect } = wp.element;
const { PanelBody, SelectControl } = wp.components;
const { InspectorControls } = wp.blockEditor;

// Access the localized fonts data injected by wp_add_inline_script
const ICON_FONTS = eiIconData.fonts;  // Access fonts from the inline script

export default function Edit({ attributes, setAttributes }) {
    const { iconFont, iconName } = attributes;
    const [availableIcons, setAvailableIcons] = useState([]);

    // Update available icons when the font changes
    useEffect(() => {
        if (iconFont && ICON_FONTS[iconFont]) {
            setAvailableIcons(ICON_FONTS[iconFont]);
        }
    }, [iconFont]);

    const onFontChange = (newFont) => {
        setAttributes({ iconFont: newFont, iconName: '' });
    };

    const onIconSelect = (name) => {
        setAttributes({ iconName: name });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Icon Settings', 'ei-icon')} initialOpen={true}>
                    <SelectControl
                        label={__('Icon Font', 'ei-icon')}
                        value={iconFont}
                        options={Object.keys(ICON_FONTS).map((font) => ({
                            label: font,
                            value: font,
                        }))}
                        onChange={onFontChange}
                    />
                </PanelBody>
            </InspectorControls>

            <div className="ei-icon-grid">
                {availableIcons.map((icon) => (
                    <div
                        key={icon}
                        className={`ei-icon-item ${iconFont === 'dashicons' ? `dashicons dashicons-${icon}` : icon}`}
                        onClick={() => onIconSelect(icon)}
                        style={{
                            fontSize: '32px',
                            padding: '10px',
                            cursor: 'pointer',
                            border: icon === iconName ? '2px solid #007cba' : '1px solid #ccc',
                        }}
                    />
                ))}
            </div>
        </>
    );
}
