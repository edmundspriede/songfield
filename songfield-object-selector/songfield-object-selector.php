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
        $current_object_id = get_user_meta( $user_id, 'current_object', true );
        $current_object_title = get_the_title( $current_object_id );

        $children = $this->get_related_objects($user_id);
        
        // Initial context state
        $context = [
            'currentObjectId' => $current_object_id,
            'currentObjectTitle' => $current_object_title,
            'availableObjects' => $children,
            'isUpdating' => false,
            'isListHidden' => true,
            'error' => '',
            'jwtToken' => $jwt_token,
            'apiBaseUrl' => admin_url('admin-ajax.php').'?action=my_objects_action'
        ];
        
       wp_interactivity_state( 'songfieldObjectSelector', $context );
        
        ob_start();
        ?>
        <div 
            data-wp-interactive="songfieldObjectSelector"
            data-wp-context='<?php echo wp_json_encode($context); ?>'
            data-wp-init="callbacks.init"
            class="songfield-object-selector"
        >
         
            <div class="songfield-current-wrapper">
                <div class="songfield-current-container is-loading" data-wp-text="context.currentObjectTitle" data-wp-class--is-loading="context.isLoading"></div>
                
                <div class="songfield-change-button" data-wp-class--spin="context.isUpdating">
                    <a  data-wp-on--click="actions.showList" class="button-spin" data-wp-class--spinning="context.isUpdating"> 
                      
                    </a>    
                </div>
            </div>    
               
           
            <div class="songfield-selector-container hidden" data-wp-bind--hidden="context.isListHidden" data-wp-class--hidden="context.isListHidden">
              
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
        </div>          
                
        
        <?php
        //$html = ob_get_clean();
        //$processed_html = wp_interactivity_process_directives( $html );
        return  ob_get_clean();
    }
    
    private function get_related_objects( $user_id)  {
        
        if ( function_exists( 'jet_engine' ) ) {

           $children = array();
           $relation = jet_engine()->relations->get_active_relations( 7 );

           if ( $relation ) {

              // Get children where parent = current user
              $related_items = $relation->get_children( $user_id );

            if ( ! empty( $related_items ) ) {
                foreach ( $related_items as $child ) {
                    $children[] = [
                        'id'    => $child['child_object_id'],
                        'title' => get_the_title( $child['child_object_id'] ),
                    ];
                }
            }
        }
        
        return $children;
       } else  return false;
    
    }
}

// Initialize the plugin
new Songfield_Object_Selector();
