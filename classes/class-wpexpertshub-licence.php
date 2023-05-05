<?php

defined( 'ABSPATH' ) || exit;

if(!class_exists('WpExpertshub_Licence')) {
	class WpExpertshub_Licence{

		private $slug;
		private $file;
		private $version;
        private $slug_base;

		function __construct($slug,$file){

			$this->slug = $slug;
			$this->file = $file;
            $this->version = '1.1';
            $this->slug_base = $this->wphub_str_replace($this->slug);
			$this->plugin_data = $this->wphub_plugin_data($this->slug); 

			if(!defined('WPHUB_UPDATER_INIT') || TRUE !== WPHUB_UPDATER_INIT){
				add_action('admin_head',array($this,'wphub_license_admin_head'));
				define('WPHUB_UPDATER_INIT',true);
			}

			add_action('admin_menu',array($this,'wphub_license'),10);
			add_filter('submenu_file',array($this,'wphub_license_submenu'),10,2);
			add_action('wp_ajax_'.$this->slug_base,array($this,'wphub_license_func'));

			add_filter('plugins_api_args', array($this,'wphub_plugins_api_args'),10,2);
			add_filter('plugins_api',array($this,'check_info'),10,3);
			add_filter('http_request_args',array($this,'http_request_args'),10,2);
			add_action('upgrader_process_complete',array($this,'wphub_plugin_updated'),10,2);
			add_action('in_plugin_update_message-'.$this->get_basename(),array($this,'update_available_notice'),10,2);
			add_action('pre_set_site_transient_update_plugins',array($this,'transient_update_plugins'),999,1);
		}

        function get_version(){
	        $transient_key = '_'.$this->plugin_data['slug'].'_ver_check';
	        $transient_key = $this->wphub_str_replace($transient_key);
	        $version = get_transient($transient_key);
	        if(false === ($version)){
		        $version = $this->getRemote_version();
		        set_transient($transient_key,$version,86400);
	        }
            return $version;
        }

        function wphub_str_replace($str){
	        return str_replace('-','_',$str);
        } 

        function get_basename(){
            return $this->file;
        }

        function get_license_page_url(){
            return admin_url('plugins.php?page=wpexperts-hub-license');
        }

		function wphub_plugin_get($file){
			if(!function_exists('get_plugins')){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_folder = get_plugins('/'.plugin_basename(dirname($file)));
			$plugin_file   = basename($file);
			return $plugin_folder[$plugin_file];
		}

		function wphub_license(){
			if(!current_user_can('manage_options')){
				return;
			}
			add_submenu_page(null,__('WpExperts Hub License','wc-cancel-order'),__('WpExperts Hub License','wc-cancel-order'), 'manage_options', 'wpexperts-hub-license', array($this,'wxp_license_page'));
		}

		function wphub_license_submenu($submenu_file,$parent_file){
			$screen = get_current_screen();
			global $plugin_page;
			if(isset($screen->id) && $screen->id === 'plugins_page_wpexperts-hub-license'){
				$submenu_file = null;
				$plugin_page = null;
			}
			return $submenu_file;
		}

		function wphub_license_func(){
            $action_ref = $this->wphub_str_replace($this->slug.'_ref');
            if((isset($_POST['check']) && !wp_verify_nonce($_POST['check'],$action_ref)) || (!isset($_POST['check']))){
	            wp_send_json(array('valid'=>false,'msg'=>'Invalid request.'));
            }
			elseif(isset($_POST['callback']) && $_POST['callback']=='get_key'){
				$email = isset($_POST['email']) ? $_POST['email'] : '';
				$slug = isset($_POST['slug']) ? $_POST['slug'] : '';
				$request = wp_remote_post($this->plugin_data['path'],array(
					'method'=>'POST',
					'timeout'=>30,
					'body'=>array(
						'action'=>'get_key',
						'slug'=>$slug,
						'email'=>$email,
						'url'=>$this->plugin_data['url'],
						'ver'=>$this->plugin_data['version'],
						'updater'=>$this->version,
					)
				));
				$res = unserialize($request['body']);
				wp_send_json(array('valid'=>isset($res->valid) ? $res->valid : false,'msg'=>isset($res->msg) ? $res->msg : ''));
			}
            elseif(isset($_POST['callback']) && $_POST['callback']=='activate_key'){
				$key = isset($_POST['key']) ? $_POST['key'] : '';
				$slug = isset($_POST['slug']) ? $_POST['slug'] : '';
				$request = wp_remote_post($this->plugin_data['path'],array(
					'method'=>'POST',
					'timeout'=>30,
					'body'=>array(
						'action'=>'activate_key',
						'slug'=>$slug,
						'key'=>$key,
						'url'=>$this->plugin_data['url'],
						'ver'=>$this->plugin_data['version'],
						'updater'=>$this->version,
					)
				));
				$res = unserialize($request['body']);
				if(isset($res->valid) && $res->valid){
					update_option('_'.$this->slug.'_licence_key',$key,'no');
					update_option('_'.$this->slug.'_key_status','active','no');
					$this->wphub_delete_plugin_transient();
				}
				wp_send_json(array('valid'=>isset($res->valid) ? $res->valid : false,'msg'=>isset($res->msg) ? $res->msg : ''));
			}
            elseif(isset($_POST['callback']) && $_POST['callback']=='deactivate_key'){
				$key = isset($_POST['key']) ? $_POST['key'] : '';
				$slug = isset($_POST['slug']) ? $_POST['slug'] : '';
				$request = wp_remote_post($this->plugin_data['path'],array(
					'method'=>'POST',
					'timeout'=>30,
					'body'=>array(
						'action'=>'deactivate_key',
						'slug'=>$slug,
						'key'=>$key,
						'url'=>$this->plugin_data['url'],
						'ver'=>$this->plugin_data['version'],
						'updater'=>$this->version,
					)
				));
				$res = unserialize($request['body']);
				if(isset($res->valid) && $res->valid){
					delete_option('_'.$this->slug.'_licence_key');
					delete_option('_'.$this->slug.'_key_status');
					$this->wphub_delete_plugin_transient();
				}
				wp_send_json(array('valid'=>isset($res->valid) ? $res->valid : false,'msg'=>isset($res->msg) ? $res->msg : ''));
			}
		}

		function wphub_plugins_api_args($args,$action){
			if(isset($this->slug) && $this->slug === $this->plugin_data['slug'] && $action=='plugin_information'){
				$args->fields = array();
			}
			return $args;
		}

		function check_info($false,$action,$arg){
			if(isset($args->slug) && $arg->slug === $this->plugin_data['slug']){
				return false;
			}
			return $false;
		}

		function http_request_args($args,$url){
			if(strpos($url,'https://') !== false && strpos($url,'wc_update')){
				$args['sslverify'] = true;
			}
			return $args;
		}

		function wphub_plugin_updated($upgrader_object,$options){
			if(isset($options['action']) && $options['action'] == 'update' && $options['type']==='plugin'){
				$this->wphub_delete_plugin_transient();
			}
		}

		function update_available_notice($plugin_data,$response){
			if(empty($response->package)){
				$license_page        = $this->get_license_page_url();
				$settings_link_open  = $license_page ? '<a href="'.esc_url($license_page).'">' : '';
				$settings_link_close = $license_page ? '</a>' : '';
				printf(
					' <em>%s</em>',
					sprintf(
						'get and Activate %1$syour license key%2$s to enable updates.',
						$settings_link_open,
						$settings_link_close
					)
				);
				printf('</p><p><em>Get your license key from %1$sHere%2$s or write us at <strong>support@wpexpertshub.com</strong></em>',$settings_link_open,$settings_link_close);
			}
		}

		function transient_update_plugins($transient){
			$transient = $this->check_update($transient);
			return $transient;
		}

		function wphub_plugin_data($slug){
			$transient_key_data = '_'.$slug.'_data';
			$transient_key_data = $this->wphub_str_replace($transient_key_data);
			$plugin_data = get_transient($transient_key_data);
			if(false === ($plugin_data)){
				$plug = $this->wphub_plugin_get($this->file);
				$plugin_data = array(
					'name'=> isset($plug['Name']) ? $plug['Name'] : '',
					'slug'=> $this->slug!='' ? $this->slug : '',
					'version'=> isset($plug['Version']) ? $plug['Version'] : '',
					'path'=> isset($plug['PluginURI']) ? $plug['PluginURI'].'/wc_update.php' : '',
					'licence' => get_option('_'.$this->slug.'_licence_key'),
					'url'=> site_url(),
					'file'=> $this->file,
					'plugin_url'=> isset($plug['PluginURI']) ? $plug['PluginURI'] : ''
				);
				set_transient($transient_key_data,$plugin_data,86400);
			}
			return $plugin_data;
		}

		function getRemote_version(){
			$request = wp_remote_post($this->plugin_data['path'],array(
				'method'=>'POST',
				'timeout'=>30,
				'body'=>array(
					'action'=>'version',
					'slug'=>$this->plugin_data['slug'],
					'key'=>$this->plugin_data['licence'],
					'url'=>$this->plugin_data['url'],
					'ver'=>$this->plugin_data['version'],
					'updater'=>$this->version, 
				)
			));
			if (!is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200) {
				return $request['body'];
			}
			return false;
		}

		function getRemote_license(){
			$request = wp_remote_post($this->plugin_data['path'], array(
				'method'=>'POST',
				'timeout'=>30,
				'body' => array(
					'action'=>'license',
					'key'=>$this->plugin_data['licence'],
					'url'=>$this->plugin_data['url'],
					'slug'=>$this->plugin_data['slug'],
					'ver'=>$this->plugin_data['version'],
					'updater'=>$this->version,
				)
			));
			if(!is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200){
				return $request['body'];
			}
			return false;
		}

		function wphub_get_plugin_log(){
            $transient_key = '_'.$this->plugin_data['slug'].'_log';
			$transient_key = $this->wphub_str_replace($transient_key);
			$log_obj = get_transient($transient_key);
			if(false === ($log_obj)){
				$remote_version = $this->get_version();
				$license = $this->getRemote_license();
				$license = maybe_unserialize($license);
				$log_obj = new stdClass();
				$log_obj->id = $this->plugin_data['slug'];
				$log_obj->slug = $this->plugin_data['slug'];
				$log_obj->plugin = $this->plugin_data['file'];
				$log_obj->new_version = $remote_version>0 ? $remote_version : $this->plugin_data['version'];
				$log_obj->url = $this->plugin_data['plugin_url'];
				$log_obj->package = isset($license->file_url) ? $license->file_url : '';
				$log_obj->tested = isset($license->tested) ? $license->tested : '';
				$log_obj->compatibility = new stdClass();
                if(isset($license->valid_key) && $license->valid_key===false){
	                delete_option('_'.$this->slug.'_licence_key');
	                delete_option('_'.$this->slug.'_key_status');
                }
				set_transient($transient_key,$log_obj,86400);
			}
			return $log_obj;
		}

        function get_plugin_data(){
	        $log_obj = new stdClass();
	        $log_obj->id = $this->plugin_data['slug'];
	        $log_obj->slug = $this->plugin_data['slug'];
	        $log_obj->plugin = $this->plugin_data['file'];
	        $log_obj->new_version = $this->plugin_data['version'];
	        $log_obj->url = $this->plugin_data['plugin_url'];
	        $log_obj->package = '';
	        $log_obj->tested = '';
	        $log_obj->compatibility = new stdClass();
            return $log_obj;
        }

		function check_update($transient){
			$new_version = $this->get_version();
			if($new_version && isset($this->plugin_data['version'])){
				if((int)version_compare($this->plugin_data['version'],$new_version,'<') && isset($transient->response)){
					$transient->response[$this->plugin_data['file']] = $this->wphub_get_plugin_log();
					unset($transient->no_update[$this->plugin_data['file']]);
				}
                else
                {
	                $transient->no_update[$this->plugin_data['file']] = $this->get_plugin_data();
	                unset($transient->response[$this->plugin_data['file']]);
                }
			}
			return $transient;
		}

        function wphub_delete_plugin_transient(){
	        $transient_key_log = '_'.$this->plugin_data['slug'].'_log';
	        $transient_key_log = $this->wphub_str_replace($transient_key_log);
	        delete_transient($transient_key_log);

	        $transient_key_data = '_'.$this->slug.'_data';
	        $transient_key_data = $this->wphub_str_replace($transient_key_data);
	        delete_transient($transient_key_data);

	        $transient_key_ver = '_'.$this->plugin_data['slug'].'_ver_check';
	        $transient_key_ver = $this->wphub_str_replace($transient_key_ver);
	        delete_transient($transient_key_ver);
        }

		function wphub_license_admin_head(){
			$screen = get_current_screen();
			if(isset($screen->id) && $screen->id === 'plugins_page_wpexperts-hub-license'){
				?>
                <style>
                    .wphub-license-main{
                        background:#FFFFFF;
                        padding:15px;
                        border:1px solid #c3c4c7;
                    }
                    .wphub-license-main input[type="text"]{
                        min-width:50%;
                    }
                    .wphub-license-main span.find-license{
                        display: block;
                        padding: 4px 0;
                        cursor: pointer;
                    }
                    .wphub-license-main .lic-row{
                        margin:10px 0;
                        display:block;
                    }
                    .wphub-license-main .lic-hide{
                        display:none;
                    }
                    .wphub-license-main button.get-wphub-lic{
                        margin-left:15px;
                    }
                    .lic-status{
                        vertical-align:sub;
                        margin-left:10px;
                    }
                    .dashicons-dismiss{
                        color:#FF0000FF;
                    }
                    .dashicons-yes-alt{
                        color:#008000FF;
                    }
                    .lic-head-label{
                        font-weight:600;
                        font-size:18px;
                    }
                    .wphub-license-main h1 > span{
                        font-size: 18px;
                        font-weight: 400;
                    }
                    .wphub-license-main .license-main-box .lic-help{
                        margin-top:30px;
                    }
                </style>
                <script type="text/javascript">

                    function valid_email(email){
                        return String(email).toLowerCase().match(/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/);
                    }

                    function wphub_send_req(wphubdata,wphub_req,$this){
                        wphub_req = jQuery.ajax({
                            type	: "POST",
                            cache	: true,
                            async: true,
                            url     : '<?php echo admin_url('admin-ajax.php'); ?>',
                            dataType : 'json',
                            data:wphubdata,
                            beforeSend:function(){
                                if(wphub_req != null){
                                    wphub_req.abort();
                                }
                            },
                            success:function(data){
                                if(data.valid){
                                    $this.closest('.license-main-box').find('.lic-txt-msg').html("<span style='color:#008000FF;display:block;'>"+data.msg+"</span>");
                                    if(wphubdata['callback']==='activate_key'){
                                        $this.closest('.license-main-box').find('span.lic-status').removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
                                    }
                                    else if(wphubdata['callback']==='deactivate_key'){
                                        $this.closest('.license-main-box').find('.wphub_license_key').val('');
                                        $this.closest('.license-main-box').find('span.lic-status').addClass('dashicons-dismiss').removeClass('dashicons-yes-alt');
                                    }
                                }
                                else
                                {
                                    $this.closest('.license-main-box').find('.lic-txt-msg').html("<span style='color:#ff0000;display:block;'>"+data.msg+"</span>");
                                }
                            }
                        });
                    }

                    jQuery(function($){

                        var wphub_req = null;

                        $('a.find-lic').on('click',function(e){
                            $(this).closest('.license-main-box').find('.lic-togg').toggleClass("lic-hide");
                            e.preventDefault();
                        });

                        $('button.get-wphub-lic').on('click',function(e){
                            var $this = $(this);
                            $(this).closest('.license-main-box').find('.lic-txt-msg').html('');
                            var email = $(this).closest('.license-main-box').find('.wphub_license_email').val(),
                                slug = $(this).closest('.license-main-box').find('.wphub_license_slug').val(),
                                check = $(this).closest('.license-main-box').find('.wphub_check_ref').val(),
                                action = $(this).closest('.license-main-box').find('.wphub_ref_action').val();
                            if(email.trim()===''){
                                $(this).closest('.license-main-box').find('.lic-txt-msg').html("<span style='color:#ff0000;display:block;'>Email address required.</span>");
                            }
                            else if(!valid_email(email.trim())){
                                $(this).closest('.license-main-box').find('.lic-txt-msg').html("<span style='color:#ff0000;display:block;'>Invalid email address.</span>");
                            }
                            wphub_send_req({'email':email,'slug':slug,'check':check,'action':action,'callback':'get_key'},wphub_req,$this);

                        });

                        $('button.activate-wphub-lic').on('click',function(e){
                            var $this = $(this);
                            $(this).closest('.license-main-box').find('.lic-txt-msg').html('');
                            var key = $(this).closest('.license-main-box').find('.wphub_license_key').val(),
                                slug = $(this).closest('.license-main-box').find('.wphub_license_slug').val(),
                                check = $(this).closest('.license-main-box').find('.wphub_check_ref').val(),
                                action = $(this).closest('.license-main-box').find('.wphub_ref_action').val();
                            wphub_send_req({'key':key,'slug':slug,'check':check,'action':action,'callback':'activate_key'},wphub_req,$this);
                        });

                        $('button.deactivate-wphub-lic').on('click',function(e){
                            var $this = $(this);
                            $(this).closest('.license-main-box').find('.lic-txt-msg').html('');
                            var key = $(this).closest('.license-main-box').find('.wphub_license_key').val(),
                                slug = $(this).closest('.license-main-box').find('.wphub_license_slug').val(),
                                check = $(this).closest('.license-main-box').find('.wphub_check_ref').val(),
                                action = $(this).closest('.license-main-box').find('.wphub_ref_action').val();
                            wphub_send_req({'key':key,'slug':slug,'check':check,'action':action,'callback':'deactivate_key'},wphub_req,$this);
                        });

                    });
                </script>
				<?php
			}
		}

		function wxp_license_page(){
			?>
            <div class="wrap wphub-license">
                <div class="wphub-license-main">
                    <h1>LICENSE <span>( <?php echo $this->plugin_data['name']; ?> )</span></h1>
					<?php
					$key_value = get_option('_'.$this->slug.'_licence_key');
					$key_status = get_option('_'.$this->slug.'_key_status');
					$status_class = $key_status=='active' ? ' dashicons-yes-alt' : ' dashicons-dismiss';
					?>
                    <div class="license-main-box">
                        <div class="lic-row lic-head">Please enter the license key that you received in the email right after the purchase:</div>
                        <div class="lic-row lic-key lic-togg"><input type="text" name="license_key" class="wphub_license_key" placeholder="License Key" value="<?php echo $key_value; ?>" /><span class="lic-status dashicons<?php echo $status_class; ?>"></span></div>
                        <div class="lic-row lic-email lic-togg lic-hide"><input type="text" class="wphub_license_email" name="license_email" value="" placeholder="Registered Email Address" /><button class="button-primary get-wphub-lic">Send License Key</button></div>
                        <div class="lic-row lic-slug"><input type="hidden" class="wphub_license_slug" name="license_slug" value="<?php echo $this->slug; ?>" /></div>
                        <div class="lic-row lic-slug lic-togg">Can't find your license key ? <a class="find-lic" href="javascript:void(0);" role="button">Get it here.</a></div>
                        <div class="lic-row lic-togg lic-hide">
                            <a class="find-lic" href="javascript:void(0);" role="button">Enter license key</a>
                            <input type="hidden" name="wphub_check_ref" class="wphub_check_ref" value="<?php echo wp_create_nonce($this->wphub_str_replace($this->slug.'_ref')); ?>">
                            <input type="hidden" name="wphub_check_ref" class="wphub_ref_action" value="<?php echo $this->slug_base; ?>">
                        </div>
                        <div class="lic-row lic-togg">The plugin will be periodically check for security and feature updates, and verify the validity of your license.</div>
						<?php
						if($key_status=='active'){
							?>
                            <div class="lic-row lic-togg"><button class="button-primary deactivate-wphub-lic">Deactivate License</button></div>
							<?php
						}
						else
						{
							?>
                            <div class="lic-row lic-togg"><button class="button-primary activate-wphub-lic">Agree & Activate License</button></div>
							<?php
						}
						?>
                        <div class="lic-row lic-txt-msg"></div>
                        <div class="lic-row lic-help"><a href="https://wpexpertshub.com/get-support/" target="_blank">Are you stuck or need help?</a></div>
                    </div>
                </div>
            </div>
			<?php
		}
	}
}