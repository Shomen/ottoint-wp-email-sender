<?php
/**
 * Main Class
 * **/
defined( 'ABSPATH' ) || exit;

class OttointEmailSender{
    const P_TYPE = 'ottoint_email_sender';
    //initialization
    public function __construct(){        
        // register actions				
		add_action('init', array(&$this, 'ottoint_init'));
        
        //Cron Activities as per condition
        add_filter('cron_schedules', array(&$this, 'filter_cron_schedules'));
        add_action ('otto_page_cronjob', array(&$this,'ottoint_send_page_wp_email'));
        add_action ('otto_post_cronjob', array(&$this,'ottoint_send_post_wp_email'));
        add_action ('otto_user_in_cronjob', array(&$this,'ottoint_send_user_in_wp_email'));

        add_action(  'transition_post_status', array( &$this,'on_all_status_transitions'), 10, 3 );
        add_action('wp_login', array(&$this,'otto_user_login_action'));
    }       

    public function ottoint_init(){
        $this->register_ottoint_ptype(); // Register post type		
		add_action('save_post',array(&$this,'ottoint_p_type_save'));
    }
    
	public function register_ottoint_ptype(){
		register_post_type(
			self::P_TYPE,
			array(
				'public' => true,				
				'label' => 'Otto International WP Email Sender',
				'has_archive'    => false,
				'publicly_queryable' => false, 
                'supports'=> array('title'),
                'register_meta_box_cb'=>$this->init_metabox(),
			)
		);
	}

    // Meta box for custom post type
    public function init_metabox() {
        add_action( 'add_meta_boxes', array( $this, 'add_metabox'  ));
        
    }
    public function add_metabox() {
        add_meta_box(
            'add-trigger-condition',
            __( 'Add Trigger Condition', 'textdomain' ),
            array( $this, 'render_metabox' ),
            self::P_TYPE,
            'advanced',
            'default'
        );
 
    }
    public function render_metabox( $post ) {
        // Add nonce for security and authentication.
        wp_nonce_field( 'custom_nonce_action', 'custom_nonce' );        
        $val='';
        $txt='Select';
        $val=get_post_meta($post->ID, '_otto_post_type_', true);
        $rol=get_post_meta($post->ID, '_otto_user_role_', true);
        if($val=='user-in')
            $txt='User logged in';
        if($val=='page')
            $txt='New page';
        if($val=='post')
            $txt='New post';        
        
        echo '<select id="otto_post_type" name="otto_post_type"><option value="'.$val.'">'.$txt.'</option><option value="post">New post</option><option value="page">New page</option><option value="user-in">User logged in</option>
        </select>';
        ?>
        <select name="otto_user_role">
            <?php wp_dropdown_roles( $rol ); ?>
        </select>
        <?php
        
    }
    // Save custom post type
    public function ottoint_p_type_save($post_id){
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE):
				return;
		endif;
		    
		if(isset($_POST['post_type']) && $_POST['post_type'] == self::P_TYPE && current_user_can('edit_post', $post_id)):    	    		
    			// Update the post's meta field                
    			update_post_meta($post_id, '_otto_post_type_', $_POST['otto_post_type']); 
                update_post_meta($post_id, '_otto_user_role_', $_POST['otto_user_role']);    		
		else:
    		return;
		endif;
    }
    
    public function filter_cron_schedules( $schedules ) {
        $schedules['in_five_minute'] = array(
            'interval' => 300,
            'display' => __('Once in Five minutes')
        );  
        
        $schedules['in_minute'] = array(
            'interval' => 60,
            'display' => __('In every minute')
        );  
        
        $schedules['in_ten_minute'] = array(
            'interval' => 600,
            'display' => __('Once in Ten minutes')
        );  
        
        $schedules['in_three_hour'] = array(
            'interval' => 10800,
            'display' => __('Once in three hours')
        );  
        return $schedules;
    }

    public function otto_crons_activation($typ) {
        $args = array(
            'post_type' => self::P_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',            
    
        );
        $the_query = new WP_Query($args);
        if($the_query->have_posts()){
            while ($the_query->have_posts()){
                    $the_query->the_post();
                    $pst_typ=get_post_meta(get_the_ID(), '_otto_post_type_', true);            
                    if($typ==$pst_typ){
                        if($pst_typ=='page'){
                            if(!wp_next_scheduled( 'otto_page_cronjob' ) ) {  
                            wp_schedule_event( time(), 'in_minute', 'otto_page_cronjob');
                            }
                        }elseif($pst_typ=='post'){
                            if(!wp_next_scheduled( 'otto_post_cronjob' ) ) {  
                            wp_schedule_event( time(), 'in_minute', 'otto_post_cronjob');
                            }
                        }elseif($pst_typ=='user-in'){
                            if(!wp_next_scheduled( 'otto_user_in_cronjob' ) ) {  
                                wp_schedule_event( time(), 'in_minute', 'otto_user_in_cronjob');
                                }
                        }
                    }                            
                }
        }
        wp_reset_query();
    }    
            
    // Send email when a page published
    public function ottoint_send_page_wp_email(){
        $id="";
        $args = array(
            'post_type' => self::P_TYPE,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_otto_post_type_',
                    'value' => 'page',
                )
            )
    
        );
        $the_query = new WP_Query($args);
        if($the_query->have_posts()){
            while ($the_query->have_posts()){
                    $the_query->the_post();
                    $id=get_the_ID();
            }
        }
        $role=get_post_meta($id,'_otto_user_role_',true);
       
        $users = get_users( array( 'role' => $role ) );
        foreach ( $users as $user ) {             
            $user_info =get_userdata($user->ID);			
            $to = $user_info->user_email;         
        
        $subject = 'A page published';
        $body = 'Body content';
        $headers =  'MIME-Version: 1.0' . "\r\n";        
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n"; 
 
        wp_mail( $to, $subject, $body, $headers );                        
        }
        wp_clear_scheduled_hook( 'otto_page_cronjob' );      
    }

    // Send email when a post published
    public function ottoint_send_post_wp_email(){
        $id="";
        $args = array(
            'post_type' => self::P_TYPE,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_otto_post_type_',
                    'value' => 'post',
                )
            )
    
        );
        $the_query = new WP_Query($args);
        if($the_query->have_posts()){
            while ($the_query->have_posts()){
                    $the_query->the_post();
                    $id=get_the_ID();
            }
        }
        $role=get_post_meta($id,'_otto_user_role_',true);
       
        $users = get_users( array( 'role' => $role ) );
        foreach ( $users as $user ) {             
            $user_info =get_userdata($user->ID);			
            $to = $user_info->user_email;            
            $subject = 'A new post published';
            $body = 'Body content';
            $headers =  'MIME-Version: 1.0' . "\r\n";         
            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";             
            wp_mail( $to, $subject, $body, $headers ); 
        }
        wp_clear_scheduled_hook( 'otto_post_cronjob' );      
    }

    // Send email when a user logged in
    public function ottoint_send_user_in_wp_email(){
        $id="";
        $args = array(
            'post_type' => self::P_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_otto_post_type_',
                    'value' => 'user-in',
                )
            )
    
        );
        $the_query = new WP_Query($args);
        if($the_query->have_posts()){
            while ($the_query->have_posts()){
                    $the_query->the_post();
                    $id=get_the_ID();
            }
        }
        $role=get_post_meta($id,'_otto_user_role_',true);
       
        $users = get_users( array( 'role' => $role ) );
        foreach ( $users as $user ) {             
            $user_info =get_userdata($user->ID);			
            $to = $user_info->user_email;           
        
        $subject = 'An user logged in';
        $body = 'Body content';
        $headers =  'MIME-Version: 1.0' . "\r\n";         
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n"; 
        
        wp_mail( $to, $subject, $body, $headers );         
        }               
        wp_clear_scheduled_hook( 'otto_user_in_cronjob' ); 
    }

    // Trigger when a post published
    public function on_all_status_transitions( $new_status, $old_status, $post ) {
        if( ( $new_status != $old_status ) && ( $new_status =='publish' )){
                
            $this->otto_crons_activation(get_post_type( $post->ID ));
        }
        
    }
    // Trigger when logged in
    public function otto_user_login_action() {
        $this->otto_crons_activation('user-in');// IF user login
    }
    
}