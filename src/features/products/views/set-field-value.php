<script>
    console.log('add variation');
    (function ($) {
        $(document).ready(function (){
            var inputField = $('<?php echo $codeInputName;?>');
            if (inputField.length && inputField.val() === "") {
                inputField.val(<?php echo $value; ?>);
            }
        });
    })(jQuery);
</script>
