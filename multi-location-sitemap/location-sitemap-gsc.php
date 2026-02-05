<?php
/**
 * Plugin Name: Multi Location Sitemap
 * Description: Outputs proper XML sitemap and KML for submission to Google Search Console.
 * Version: 1.5
 * Author: Vince L
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', function() {
    add_options_page('Location Sitemap', 'Location Sitemap', 'manage_options', 'location-sitemap', 'rmla_settings_page');
});

function rmla_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rmla_locations'])) {
        check_admin_referer('rmla_save_locations');
        $locations = array_chunk(array_map('sanitize_text_field', $_POST['rmla_locations']), 6);
        update_option('rmla_locations', $locations);
        echo '<div class="updated"><p>Locations saved.</p></div>';
    }

    $locations = get_option('rmla_locations', []);
    ?>
    <div class="wrap">
        <h1>Location Sitemap Settings</h1>
        <form method="post">
            <?php wp_nonce_field('rmla_save_locations'); ?>
            <table id="rmla-table" class="form-table">
                <thead>
                    <tr><th>Name</th><th>Description</th><th>Address</th><th>Phone</th><th>Latitude</th><th>Longitude</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location): ?>
                        <tr>
                            <?php foreach ($location as $val): ?>
                                <td><input type="text" name="rmla_locations[]" value="<?php echo esc_attr($val); ?>" /></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input type="text" name="rmla_locations[]" /></td>
                        <td><input type="text" name="rmla_locations[]" /></td>
                        <td><input type="text" name="rmla_locations[]" /></td>
                        <td><input type="text" name="rmla_locations[]" /></td>
                        <td><input type="text" name="rmla_locations[]" /></td>
                        <td><input type="text" name="rmla_locations[]" /></td>
                    </tr>
                </tbody>
            </table>
            <p><button type="button" onclick="addRow()">+ Add Row</button></p>
            <p><button type="submit" class="button button-primary">Save Locations</button></p>
        </form>
        <script>
            function addRow() {
                const row = `<tr>
                    <td><input type="text" name="rmla_locations[]" /></td>
                    <td><input type="text" name="rmla_locations[]" /></td>
                    <td><input type="text" name="rmla_locations[]" /></td>
                    <td><input type="text" name="rmla_locations[]" /></td>
                    <td><input type="text" name="rmla_locations[]" /></td>
                    <td><input type="text" name="rmla_locations[]" /></td>
                </tr>`;
                document.querySelector('#rmla-table tbody').insertAdjacentHTML('beforeend', row);
            }
        </script>
    </div>
    <?php
}

add_action('init', function() {
    add_rewrite_rule('^sitemap-local\.xml$', 'index.php?local_sitemap=1', 'top');
    add_rewrite_rule('^locations\.kml$', 'index.php?locations_kml=1', 'top');
    add_rewrite_tag('%local_sitemap%', '1');
    add_rewrite_tag('%locations_kml%', '1');
});

add_filter('redirect_canonical', function($redirect_url, $requested_url) {
    if (strpos($requested_url, '.kml') !== false || strpos($requested_url, '.xml') !== false) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

add_action('template_redirect', function() {
    $locations = get_option('rmla_locations', []);
    $base_url = home_url();

    if (get_query_var('local_sitemap')) {
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        echo '<sitemap>';
        echo '<loc>' . esc_url($base_url . '/locations.kml') . '</loc>';
        echo '<lastmod>' . esc_html(date('c')) . '</lastmod>';
        echo '</sitemap>';
        echo '</sitemapindex>';
        exit;
    }

    if (get_query_var('locations_kml')) {
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<Document>';
        echo '<name>Locations for Your Business</name>';
        echo '<open>1</open>';
        echo '<Folder>';
        echo '<atom:link href="' . esc_url($base_url) . '" />';
        foreach ($locations as $loc) {
            list($name, $desc, $address, $phone, $lat, $lng) = $loc;
            echo '<Placemark>';
            echo '<name><![CDATA[' . esc_html($name) . ']]></name>';
            echo '<description><![CDATA[' . esc_html($desc) . ']]></description>';
            echo '<address><![CDATA[' . esc_html($address) . ']]></address>';
            echo '<phoneNumber><![CDATA[' . esc_html($phone) . ']]></phoneNumber>';
            echo '<atom:link href="' . esc_url($base_url) . '"/>';
            echo '<LookAt>';
            echo '<latitude>' . esc_html($lat) . '</latitude>';
            echo '<longitude>' . esc_html($lng) . '</longitude>';
            echo '<altitude>0</altitude>';
            echo '<range></range>';
            echo '<tilt>0</tilt>';
            echo '</LookAt>';
            echo '<Point><coordinates>' . esc_html($lng) . ',' . esc_html($lat) . '</coordinates></Point>';
            echo '</Placemark>';
        }
        echo '</Folder>';
        echo '</Document>';
        echo '</kml>';
        exit;
    }
});


// Remove locations.kml from Rank Math sitemap or other auto-indexers
add_filter('rank_math/sitemap/exclude_cpt_slugs', function($excluded) {
    $excluded[] = 'locations.kml';
    return $excluded;
});

add_filter('rank_math/sitemap/entry', function($url, $type, $object_id) {
    if (isset($url['loc']) && strpos($url['loc'], 'locations.kml') !== false) {
        return false;
    }
    return $url;
}, 10, 3);