const { __ } = wp.i18n;
const { useState, useEffect } = wp.element;
const { TextControl, PanelBody, PanelRow, ColorPicker } = wp.components;
const { BlockControls, AlignmentToolbar, InspectorControls } = wp.blockEditor;
const { useBlockProps } = wp.blockEditor;

export default function Edit({ attributes, setAttributes }) {
    const { fontSize, lineHeight, align, backgroundColor, textColor, className } = attributes;

    const [fonts, setFonts] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [showFontSelection, setShowFontSelection] = useState(false);

    const [selectedIcon, setSelectedIcon] = useState({ className });


    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch('/?rest_route=/easyicon/v1/fonts');
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const json = await response.json();

                if (typeof json === 'object') {
                    setFonts(json);
                } else {
                    setError('Data is not in the expected format.');
                }

                setLoading(false);
            } catch (error) {
                setError('Failed to fetch fonts');
                setLoading(false);
                console.error(error);
            }
        };

        fetchData();
    }, []);

    const filteredFonts = Object.keys(fonts).map(fontFolder => {
        const fontArray = fonts[fontFolder];

        const filteredGlyphs = fontArray.filter(([name]) => {
            return name.toLowerCase().includes(searchTerm.toLowerCase());
        });

        return {
            fontFolder,
            glyphs: filteredGlyphs
        };
    }).filter(font => font.glyphs.length > 0);

    const handleTypographyChange = (value, property) => {
        setAttributes({ [property]: value });
    };

    const handleAlignmentChange = (newAlign) => {
        setAttributes({ align: newAlign });
    };

    const handleIconClick = (className) => {
        setSelectedIcon({ className });
        setAttributes({ className });
        setShowFontSelection(false);
    };

    const toggleFontSelection = () => {
        setShowFontSelection(prevState => !prevState);
    };

    const blockProps = useBlockProps({
        style: {
            fontSize: fontSize ? `${fontSize}px` : undefined,
            lineHeight: lineHeight ? `${lineHeight}px` : undefined,
            backgroundColor: backgroundColor || undefined,
            color: textColor || undefined,
        }
    });

    const wrapperClass = `selected-icon-wrapper align${align}`;
    
    return (
         <>
            <BlockControls>
                <AlignmentToolbar
                    value={align}
                    onChange={handleAlignmentChange}
                />
            </BlockControls>

            <InspectorControls>
                <PanelBody title={__('Icon Settings', 'easyicon')}>
                    <PanelRow>
                        <TextControl
                            label={__('Font Size', 'easyicon')}
                            value={fontSize}
                            onChange={(value) => handleTypographyChange(value, 'fontSize')}
                            type="number"
                            min="10"
                            max="200"
                            step="1"
                        />
                    </PanelRow>

                    <PanelRow>
                        <TextControl
                            label={__('Line Height', 'easyicon')}
                            value={lineHeight}
                            onChange={(value) => handleTypographyChange(value, 'lineHeight')}
                            type="number"
                            min="10"
                            max="200"
                            step="1"
                        />
                    </PanelRow>

                    <PanelRow>
                        <label>{__('Background Color', 'easyicon')}</label>
                        <ColorPicker
                            color={backgroundColor}
                            onChangeComplete={(color) => setAttributes({ backgroundColor: color.hex })}
                        />
                    </PanelRow>

                    <PanelRow>
                        <label>{__('Text Color', 'easyicon')}</label>
                        <ColorPicker
                            color={textColor}
                            onChangeComplete={(color) => setAttributes({ textColor: color.hex })}
                        />
                    </PanelRow>
                </PanelBody>
            </InspectorControls>
            

            <div {...blockProps} className={`${blockProps.className} ${wrapperClass}`}>
                {selectedIcon.className ? (
                    <span
                        className={selectedIcon.className}
                        style={{ cursor: 'pointer' }}
                        onClick={toggleFontSelection}
                    />
                ) : (
                    <p onClick={toggleFontSelection}>
                        {__('No Icon Selected', 'easyicon')}
                    </p>
                )}
            </div>

            {showFontSelection && (
                <div className="ei-icon-grid">
                    <div className="ei-icon-search">
                        <TextControl
                            label={__('Search Icons', 'easyicon')}
                            value={searchTerm}
                            onChange={(value) => setSearchTerm(value)}
                            placeholder={__('Search by glyph name...', 'easyicon')}
                        />
                    </div>

                    {loading && <p>{__('Loading fonts...', 'easyicon')}</p>}
                    {error && <p>{__('Error: ', 'easyicon')}{error}</p>}

                    {!loading && !error && filteredFonts.length > 0 && (
                        filteredFonts.map((font, index) => (
                            <details key={index} className="ei-font-details">
                                <summary>{font.fontFolder}</summary>
                                <div className="ei-font-icons">
                                    {font.glyphs.map(([name], i) => {
                                        const iconClass = `ei-${font.fontFolder.toLowerCase()}-${name.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase()}`;

                                        return (
                                            <span
                                                key={i}
                                                className="ei-font-icon"
                                                onClick={() => handleIconClick(iconClass)} // Select icon on click
                                                style={{ cursor: 'pointer', fontSize: '20px', margin: '5px' }}
                                            >
                                                <span className={iconClass}></span>
                                                <span>{name}</span>
                                            </span>
                                        );
                                    })}
                                </div>
                            </details>
                        ))
                    )}

                    {!loading && !error && filteredFonts.length === 0 && (
                        <p>{__('No fonts found', 'easyicon')}</p>
                    )}
                </div>
            )}
        </>
    );
}
