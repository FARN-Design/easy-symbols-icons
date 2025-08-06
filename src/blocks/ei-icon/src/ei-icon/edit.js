const { __ } = wp.i18n;
const { useState, useEffect } = wp.element;
const { TextControl } = wp.components;

export default function Edit({ attributes, setAttributes }) {
    const [fonts, setFonts] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedIcon, setSelectedIcon] = useState(null);

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

    // Filter fonts based on the search term
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

    // Handle icon selection and update attributes
    const handleIconClick = (className) => {
        setSelectedIcon({ className });
        setAttributes({ className });
    };

    // Render the icons grid and selected icon preview
    return (
        <>
            <div className="ei-icon-search">
                <TextControl
                    label={__('Search Icons', 'easyicon')}
                    value={searchTerm}
                    onChange={(value) => setSearchTerm(value)}
                    placeholder={__('Search by glyph name...', 'easyicon')}
                    __next40pxDefaultSize={true}
                    __nextHasNoMarginBottom={true}
                />
            </div>

            <div className="ei-icon-grid">
                {loading && <p>{__('Loading fonts...', 'easyicon')}</p>}
                {error && <p>{__('Error: ', 'easyicon')}{error}</p>}
                
                {!loading && !error && filteredFonts.length > 0 && (
                    filteredFonts.map((font, index) => (
                        <details key={index} className="ei-font-details">
                            <summary>{font.fontFolder}</summary>
                            <div className="ei-font-icons">
                                {font.glyphs.map(([name], i) => {
                                    // Dynamic class name for rendering the icon
                                    const className = `ei-${font.fontFolder.toLowerCase()}-${name.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase()}`;

                                    return (
                                        <span 
                                            key={i}
                                            className="ei-font-icon"
                                            onClick={() => handleIconClick(className)}
                                            style={{ cursor: 'pointer', fontSize: '20px', margin: '5px' }}
                                        >
                                            <span className={className}></span>
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

            {selectedIcon && selectedIcon.className && (
                <div className="selected-icon-preview">
                    <p>{__('Selected Icon:', 'easyicon')}</p>
                    <span 
                        className={selectedIcon.className} 
                        style={{ fontSize: '30px' }}
                    ></span>
                </div>
            )}
        </>
    );
}
