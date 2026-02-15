
<?php
/**
 * Plugin Name: Songfield Object Selector
 * Description: Interactive object selector using WordPress Interactivity API
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Songfield_Object_Selector {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('songfield_object_selector', [$this, 'render_shortcode']);
    }
    
    public function enqueue_assets() {
        // Register the view script for Interactivity API
        wp_register_script_module(
            'songfield-object-selector-view',
            plugin_dir_url(__FILE__) . 'songfield-object-selector-view.js',
            [],
            '1.0.0'
        );
        
        // Register CSS
        wp_register_style(
            'songfield-object-selector',
            plugin_dir_url(__FILE__) . 'songfield-object-selector.css',
            [],
            '1.0.0'
        );
    }
    
    public function render_shortcode($atts) {
        // Enqueue the assets
        wp_enqueue_script_module('songfield-object-selector-view');
        wp_enqueue_style('songfield-object-selector');
        
        // Get the JWT cookie value
        $jwt_token = isset($_COOKIE['my_jwt']) ? $_COOKIE['my_jwt'] : '';
        
        $user_id = get_current_user_id();
        $current_object = array('id' => 333  , 'title' => 'Bērnudārzs X' );
        
        $children = array(  array('id' => 555  , 'title' => 'AAAA' ), array('id' => 666  , 'title' => 'BBBB'));
        
        // Initial context state
        $context = [
            'currentObjectId' => $current_object['id'],
            'currentObjectTitle' => $current_object['title'],
            'availableObjects' => $children,
            'isLoading' => false,
            'isListVisible' => false,
            'error' => '',
            'jwtToken' => $jwt_token,
            'apiBaseUrl' => rest_url('songfield/v1')
        ];
        
        ob_start();
        ?>
        <div 
            data-wp-interactive="songfield-object-selector"
            data-wp-context='<?php echo wp_json_encode($context); ?>'
            data-wp-init="callbacks.init"
            class="songfield-object-selector"
        >
            
            <div class="songfield-current-container" data-wp-text="context.currentObjectTitle">
                 
            </div>
            <a class="songfield-change-button" data-wp-on--click="actions.showList">
		<span class="button-content-wrapper">
		<span class="button-icon">
                    <svg aria-hidden="true" class="e-font-icon-svg e-fas-exchange-alt" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M0 168v-16c0-13.255 10.745-24 24-24h360V80c0-21.367 25.899-32.042 40.971-16.971l80 80c9.372 9.373 9.372 24.569 0 33.941l-80 80C409.956 271.982 384 261.456 384 240v-48H24c-13.255 0-24-10.745-24-24zm488 152H128v-48c0-21.314-25.862-32.08-40.971-16.971l-80 80c-9.372 9.373-9.372 24.569 0 33.941l80 80C102.057 463.997 128 453.437 128 432v-48h360c13.255 0 24-10.745 24-24v-16c0-13.255-10.745-24-24-24z"></path></svg>			</span>
		   
		</span>
	    </a>
            <div class="songfield-selector-container">
                <div data-wp-bind--hidden="!state.isListVisible">
                    <div>

                        <ul 
                            id="object-selector"
                            class="songfield-selector-dropdown"
                            data-wp-bind--disabled="state.isUpdating"
                            data-wp-context='{ "list" : <?php echo wp_json_encode($context['availableObjects']); ?> }'
                        >
                            <template data-wp-each="context.list">      
                                <li  data-wp-bind--value="context.item.id"  data-wp-text="context.item.title" data-wp-on--click="actions.handleObjectChange"></li>
                            </template>
                        </ul>
      
                      
                    </div>
                    
                    <div 
                        class="songfield-selector-error"
                        data-wp-show="state.error"
                        data-wp-text="state.error"
                    ></div>
                </div>
                
                <div 
                    class="songfield-selector-loading"
                    data-wp-bind--hidden="!state.isLoading"
                >
                    Loading objects...
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
new Songfield_Object_Selector();
