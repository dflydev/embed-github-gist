<?php
/*
Plugin Name: Embed GitHub Gist
Plugin URI: http://wordpress.org/extend/plugins/embed-github-gist/
Description: Embed GitHub Gists
Author: Dragonfly Development
Author URI: http://dflydev.com/
Version: 0.4
License: New BSD License - http://www.opensource.org/licenses/bsd-license.php
*/

// TODO Option for default ttl
// TODO Option to bypass cache
// TODO Option to select css loading location preference
// TODO Shortcode attribute to control inline vs. js preference
// TODO Implement admin interface to control options

/**
 * Track how many gists have been displayed
 * 
 * Used to determine whether or not to late-load the gist css.
 * @var int
 */
$embed_github_gist_loaded_count = 0;

/**
 * Default ttl for cache.
 * @var int
 */
$embed_github_gist_default_ttl = 86400; // 60*60*24 (1 day)

/**
 * Build a cache key
 * @param int $id GitHub Gist ID
 * @param string $bump Bump value to force cache expirey.
 */
function embed_github_gist_build_cache_key($id, $bump = null) {
    $key = 'embed_github_gist-' . $id;
    if ( $bump ) $key .= '-' . $bump;
    return $key;
}

/**
 * Bypass cache?
 */
function embed_github_gist_bypass_cache() {
    return true;
}

/**
 * Prefer inline HTML over JS?
 */
function embed_github_gist_prefer_inline_html() {
    return false;
}

/**
 * Gets content from GitHub Gist
 * @param int $id GitHub Gist ID
 * @param int $ttl How long to cache (in seconds)
 * @param string $bump Bump value to force cache expirey.
 * @param string $file Name of file
 */
function embed_github_gist($id, $ttl = null, $bump = null, $file = null) {
		if ( !class_exists('WP_Http') ) {
			require_once ABSPATH.WPINC.'/class-http.php';
		}
	
    $key = embed_github_gist_build_cache_key($id, $bump);
    if ( embed_github_gist_bypass_cache() or false === ( $gist = get_transient($key) ) ) {
    		$http = new WP_Http;
    	
        if ( embed_github_gist_prefer_inline_html() and function_exists('json_decode') ) {
        		$result = $http->request('https://gist.github.com/' . $id . '.json');
            $json = json_decode($result['body']);
            $gist = $json->div;
        } else {
            if ( ! $file ) $file = 'file';
            $result = $http->request('https://gist.github.com/raw/' . $id . '/' . $file);
            $gist = '<script src="https://gist.github.com/' . $id . '.js?file=' . $file . '%5B345%5D"></script>';
            $gist .= '<noscript><div class="embed-github-gist-source"><code><pre>';
            $gist .= htmlentities($result['body']);
            $gist .= '</pre></code></div></noscript>';
        }
        
        unset($result, $http);
        
        if ( ! embed_github_gist_bypass_cache() ) {
            if ( ! $ttl ) $ttl = $embed_github_gist_default_ttl;
            set_transient($key, $gist, $ttl);
        }
    }
    global $embed_github_gist_loaded_count;
    if ( $gist ) $embed_github_gist_loaded_count++;
    return $gist;
}

/**
 * Shortcode handler
 * @param array $atts Attributes
 * @param mixed $content
 */
function handle_embed_github_gist_shortcode($atts, $content = null) {
    extract(shortcode_atts(array(
        'id' => null,
        'file' => null,
        'ttl' => null,
        'bump' => null,
    ), $atts));
    if ( ! $id ) {
        if ( $content ) {
            if ( preg_match('/\s*https?.+\/(\d+)/', $content, $matches) ) {
                $id = $matches[1];
            }
        }
    }
    return $id ? embed_github_gist($id, $ttl, $bump, $file) : $content;
}

/**
 * Styles.
 */
function embed_github_gist_styles() {
    wp_enqueue_style('embed_github_gist_from_gist', 'https://gist.github.com/stylesheets/gist/embed.css');
}

/**
 * Handle styles early. (HEAD)
 */
function handle_embed_github_gist_styles_early() {
    embed_github_gist_styles();
}

/**
 * Handle styles late. (footer, only if one gist has been show)
 */
function handle_embed_github_gist_styles_late() {
    global $embed_github_gist_loaded_count;
    if ( $embed_github_gist_loaded_count > 0 ) {
        // Not entirely clear how safe this is?
        embed_github_gist_styles();
        wp_print_styles();
    }
}

/**
 * Init the plugin.
 */
function handle_embed_github_gist_init() {
    add_shortcode('gist', 'handle_embed_github_gist_shortcode');
    if ( true ) {
        add_action('wp_print_styles', 'handle_embed_github_gist_styles_early');
    } else {
        add_action('wp_print_footer_scripts', 'handle_embed_github_gist_styles_late');
    }
}

add_action('init', 'handle_embed_github_gist_init');
