<?php
/**
 * Geo Carpentry Child Theme Functions
 * Theme: Built to Last. Crafted with Pride.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue parent (Astra) and child (Geo Carpentry) styles.
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme()->parent()->get( 'Version' )
    );
    wp_enqueue_style(
        'geo-carpentry-child-style',
        get_stylesheet_uri(),
        [ 'astra-parent-style' ],
        wp_get_theme()->get( 'Version' )
    );
} );

/**
 * Register footer widget area.
 */
add_action( 'widgets_init', function () {
    register_sidebar( [
        'name'          => 'Footer Widget Area',
        'id'            => 'footer-1',
        'description'   => 'Widgets for the Geo Carpentry footer.',
        'before_widget' => '<div class="gc-footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ] );
} );

/**
 * Inject LocalBusiness schema markup in the <head> of every page.
 * Hooked early so SEO plugins can still override if needed.
 */
add_action( 'wp_head', function () {
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'GeneralContractor',
        '@id'         => home_url( '/#business' ),
        'name'        => 'Geo Carpentry LLC',
        'description' => 'Licensed carpentry and construction company serving Green Bay and Northeast Wisconsin since 2014. Custom carpentry, kitchen and bathroom remodeling, deck building, home renovation, and general construction.',
        'url'         => home_url( '/' ),
        'telephone'   => '+1-920-367-1272',
        'email'       => 'admin@geocarpentry.com',
        'priceRange'  => '$$',
        'foundingDate'=> '2014',
        'founder'     => [ '@type' => 'Person', 'name' => 'Jorge Cruz' ],
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '735 E Walnut St Suite 3',
            'addressLocality' => 'Green Bay',
            'addressRegion'   => 'WI',
            'postalCode'      => '54301',
            'addressCountry'  => 'US',
        ],
        'geo'         => [
            '@type'    => 'GeoCoordinates',
            'latitude' => 44.5133,
            'longitude'=> -88.0133,
        ],
        'areaServed'  => array_map( function ( $city ) {
            return [ '@type' => 'City', 'name' => $city, 'addressRegion' => 'WI' ];
        }, [
            'Green Bay', 'Appleton', 'Oshkosh', 'Sheboygan', 'Manitowoc',
            'Fond du Lac', 'Wausau', 'Marinette', 'Oconto', 'Shawano',
            'De Pere', 'Ashwaubenon', 'Howard', 'Suamico', 'Pulaski',
        ] ),
        'openingHoursSpecification' => [
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ],
                'opens'     => '08:00',
                'closes'    => '18:00',
            ],
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens'     => '09:00',
                'closes'    => '15:00',
            ],
        ],
        'sameAs'      => [
            'https://www.facebook.com/profile.php?id=61578160947198',
            'https://www.instagram.com/geocarpentryllc2026',
        ],
        'knowsLanguage' => [ 'en', 'es' ],
        'slogan'      => 'Built to Last. Crafted with Pride.',
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name'  => 'Carpentry & Construction Services',
            'itemListElement' => array_map( function ( $svc ) {
                return [
                    '@type' => 'Offer',
                    'itemOffered' => [ '@type' => 'Service', 'name' => $svc ],
                ];
            }, [
                'Custom Carpentry & Woodwork',
                'Kitchen Remodeling',
                'Bathroom Remodeling',
                'Deck Building',
                'Home Renovation',
                'General Construction',
            ] ),
        ],
    ];
    echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 5 );

/**
 * Add a default meta description fallback for pages without one.
 */
add_action( 'wp_head', function () {
    if ( is_front_page() ) {
        echo '<meta name="description" content="Licensed carpentry and construction in Green Bay and Northeast Wisconsin. Custom carpentry, kitchen and bathroom remodeling, decks, and home renovations. 10+ years experience. Free estimates: (920) 367-1272.">' . "\n";
    }
}, 1 );
