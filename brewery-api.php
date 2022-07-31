<?php

/**
 * Plugin Name:       Brewery Api
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Handle the basics with this plugin.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Tarikul Islam
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       wecoder_brewery
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * When you start to configure the plugin in a new site then first need hit a url 
 * the url = https://example.com/wp-admin/admin-ajax.php?action=get_breweries_from_api
 * you can keep a button with the url 
 * the response you get 0 because we don't need to return statement on our purpose 
 * 
 * when hit the url then you get post on brewey custom post  
 */

/**
 * Template Function Configure 
 */

// Template stores in Array
function my_template_array()
{
    $temps = [];
    $temps['front-special-template.php'] = 'Front Special Template';

    return $temps;
}

// My template register 
function my_template_register($page_templates, $theme, $post)
{
    $templates = my_template_array();
    foreach ($templates as $key => $value) {
        $page_templates[$key] = $value;
    }
    return $page_templates;
}
add_filter('theme_page_templates', 'my_template_register', 10, 3);


// Templates path include post wise 
function wecoder_template_include($template)
{

    global $post, $wp_query, $wpdb;

    if (isset($post->ID)) {

        $templates = my_template_array();

        $page_temp_slug = get_page_template_slug($post->ID);

        // echo '<pre> Preformated: ' .plugin_dir_path(__FILE__).'page-templates/'.$page_temp_slug . '';

        if (isset($templates[$page_temp_slug])) {
            $template = plugin_dir_path(__FILE__) . 'page-templates/' . $page_temp_slug;
        }
    }

    return $template;
}
add_filter('template_include', 'wecoder_template_include');

/**
 * Brewery custom post 
 */

function wecoder_bewery_bewery_custom_post()
{
    register_post_type('brewery', array(
        'label' => 'Breweries',
        'public' => true,
        'capability_type' => 'post',
    ));
}
add_action('init', 'wecoder_bewery_bewery_custom_post');



// if ( ! wp_next_scheduled( 'update_brewery_list' ) ) {
//   wp_schedule_event( time(), 'weekly', 'update_brewery_list' );
// }
add_action('update_brewery_list', 'get_breweries_from_api');

/**
 * Brewery post get from by APi 
 */

function get_breweries_from_api()
{

    $current_page = (!empty($_POST['current_page'])) ? $_POST['current_page'] : 1;
    $breweries = [];

    // Should return an array of objects
    $results = wp_remote_retrieve_body(wp_remote_get('https://api.openbrewerydb.org/breweries/?page=' . $current_page . '&per_page=50'));

    // turn it into a PHP array from JSON string
    $results = json_decode($results);

    // Either the API is down or something else spooky happened. Just be done.
    if (!is_array($results) || empty($results)) {
        return false;
    }

    // The all data stroes in Array 
    $breweries[] = $results;

    $index = 0;
    // All data iterator 
    foreach ($breweries[0] as $brewery) {

        // We make sure if the below fields not empty then we store in our post 
        if (!empty($brewery->longitude) && !empty($brewery->latitude) && !empty($brewery->updated_at)) {

            // slug define 
            $brewery_slug = sanitize_title($brewery->name . '-' . $index++);

            // find exiting title 
            $existing_brewery = get_page_by_path($brewery_slug, OBJECT, 'brewery');

            if ($existing_brewery === null) {

                // post inserted and return id 
                $inserted_brewery = wp_insert_post(
                    [
                        'post_title' => $brewery_slug,
                        'post_name' => $brewery_slug,
                        'post_type' => 'brewery',
                        'post_status' => 'publish'
                    ]
                );
                // somehow if error then we skip it 
                if (is_wp_error($inserted_brewery)) {
                    continue;
                }

                // custom field key 
                $fillable = [
                    'field_62e56a031abbe'  => 'name',
                    'field_62e56a351abbf'  => 'brewery_type',
                    'field_62e56a881abc0'  => 'street',
                    'field_62e56a9e1abc1'  => 'city',
                    'field_62e56aab1abc2'  => 'state',
                    'field_62e56abd1abc3'  => 'postal_code',
                    'field_62e56ae11abc4'  => 'country',
                    'field_62e56aea1abc5'  => 'longitude',
                    'field_62e56af31abc6'  => 'latitude',
                    'field_62e56b041abc7'  => 'phone',
                    'field_62e56b161abc8'  => 'website_url',
                    'field_62e56b321abc9'  => 'updated_at',
                ];

                // dynamically update the field 
                foreach ($fillable as $key => $name) {
                    update_field($key, $brewery->$name ?? '', $inserted_brewery);
                }
            } else {

                $existing_brewery_id = $existing_brewery->ID;
                $exisiting_brewerey_timestamp = get_field('updated_at', $existing_brewery_id);

                if ($brewery->updated_at >= $exisiting_brewerey_timestamp) {

                    $fillable = [
                        'field_62e56a031abbe'  => 'name',
                        'field_62e56a351abbf'  => 'brewery_type',
                        'field_62e56a881abc0'  => 'street',
                        'field_62e56a9e1abc1'  => 'city',
                        'field_62e56aab1abc2'  => 'state',
                        'field_62e56abd1abc3'  => 'postal_code',
                        'field_62e56ae11abc4'  => 'country',
                        'field_62e56aea1abc5'  => 'longitutue',
                        'field_62e56af31abc6'  => 'latitude',
                        'field_62e56b041abc7'  => 'phone',
                        'field_62e56b161abc8'  => 'website',
                        'field_62e56b321abc9'  => 'update_at',
                    ];
                    foreach ($fillable as $key => $name) {
                        update_field($name, $brewery->$name, $existing_brewery_id);
                    }
                }
            } // else condition 
        } // 1st if condition 
    }

    // current page dynamically increase 
    $current_page = $current_page + 1;

    // dynamically hit the url till current page existing 
    wp_remote_post(admin_url('admin-ajax.php?action=get_breweries_from_api'), [
        'blocking' => false,
        'sslverify' => false, // we are sending this to ourselves, so trust it.
        'body' => [
            'current_page' => $current_page
        ]
    ]);

    //wp_send_json_success($breweries[0]);
    //wp_send_json_error('no');
}

add_action('wp_ajax_nopriv_get_breweries_from_api', 'get_breweries_from_api');
add_action('wp_ajax_get_breweries_from_api', 'get_breweries_from_api');
