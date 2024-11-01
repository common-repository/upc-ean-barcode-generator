<p class="form-field <?php echo esc_attr($this->fieldName); ?>_field ">
    <label for="<?php echo esc_attr($this->fieldName); ?>"><?php echo esc_html($this->fieldLabel); ?></label>
    <?php echo wc_help_tip(esc_html__('This field created by "UPC/EAN Generator" plugin.')); ?>
    <input type="text" class="short" style="" name="<?php echo esc_attr($this->fieldName); ?>" id="<?php echo esc_attr($this->fieldName); ?>" value="<?php echo esc_html($value); ?>" placeholder="<?php echo __("Enter code", "upc-ean-generator") ?>">
    <?php if (isset($newProductDatabaseCodeId) && isset($newProductDatabaseCodeValue)): ?>
        <input type="hidden" name="new_products_database_code_id" value="<?php echo esc_attr($newProductDatabaseCodeId); ?>">
        <input type="hidden" name="new_products_database_code_value" value="<?php echo esc_attr($newProductDatabaseCodeValue); ?>">
    <?php endif; ?>
</p>
