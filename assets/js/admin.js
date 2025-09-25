(function($){
    $(function(){
        $('.mpc-settings-form').each(function(){
            var $form = $(this);
            $form.find('h2').each(function(){
                var $heading = $(this);
                var $desc    = $heading.next('p.description');
                var $table   = $desc.length ? $desc.next('.form-table') : $heading.next('.form-table');

                if ( $table.length ) {
                    var $card = $('<div class="mpc-section-card" />');
                    $card.insertBefore($heading);
                    $card.append($heading);
                    if ( $desc.length ) {
                        $card.append($desc);
                    }
                    $card.append($table);
                }
            });
        });
    });
})(jQuery);
