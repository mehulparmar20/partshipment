jQuery(function($){

    var wphub_shipment = {
        init:function(){
            $(document).on('click','button.add-shipment',{view:this},this.add_shipment);
            $('.tips').tipTip({
                'attribute': 'data-tip',
                'fadeIn': 50,
                'fadeOut': 50,
                'delay': 200,
                'keepAlive': true
            });
        },
        add_shipment:function(){
            var data = $(this).attr('data-row');
            $('#new-shipment').html(data);
        },
    };
    wphub_shipment.init();
})