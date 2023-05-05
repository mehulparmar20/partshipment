<?php

defined('ABSPATH') || exit;

if(!class_exists('Wphub_Partial_Shipment_Update_Email')):

    class Wphub_Partial_Shipment_Update_Email extends WC_Email{

        public function __construct() {
            $this->id             = 'wphub_partial_shipment_update';
            $this->customer_email = true;
            $this->title          = __('Partial Shipment Update','wxp-partial-shipment');
            $this->description    = __('This is an order notification sent to customers if partial shipment updated.','wxp-partial-shipment');
            $this->template_base  = WPHUB_PARTIAL_SHIP.'/templates/';
            $this->template_html  = 'emails/partial-shipment-details-updated.php';
            $this->template_plain = 'emails/plain/partial-shipment-details-updated.php';
            $this->placeholders   = array(
                '{order_date}'   => '',
                '{order_number}' => '',
            );

            // Call parent constructor.
            parent::__construct();
        }

        public function get_default_subject(){
            return __('Your {site_title} partially shipped order is updated!','wxp-partial-shipment');
        }

        public function get_default_heading(){
            return __('Your partially shipped order is updated','wxp-partial-shipment');
        }

        public function trigger($order_id,$order,$shipment_id){
            $this->setup_locale();

            if($order_id && ! is_a($order,'WC_Order')){
                $order = wc_get_order($order_id);
            }

            if(is_a($order,'WC_Order')){
                $this->object                         = $order;
                $this->recipient                      = $this->object->get_billing_email();
                $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                $this->shipment_id = $shipment_id;
            }

            if($this->is_enabled() && $this->get_recipient()){
                $this->send($this->get_recipient(),$this->get_subject(),$this->get_content(),$this->get_headers(),$this->get_attachments());
            }

            $this->restore_locale();
        }

        public function get_content_html(){
            return wc_get_template_html(
                $this->template_html,
                array(
                    'order'              => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => false,
                    'plain_text'         => false,
                    'email'              => $this,
                    'shipment_id'        => $this->shipment_id,
                ),
                '',
                $this->template_base
            );
        }

        public function get_content_plain(){
            return wc_get_template_html(
                $this->template_plain,
                array(
                    'order'              => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => true,
                    'plain_text'         => true,
                    'email'              => $this,
                    'shipment_id'        => $this->shipment_id,
                ),
                '',
                $this->template_base
            );
        }
    }

endif;

return new Wphub_Partial_Shipment_Update_Email();
