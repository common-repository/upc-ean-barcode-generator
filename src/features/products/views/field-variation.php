<p class="form-row form-field form-row-full">
    <label for="<?php echo esc_attr("v_{$this->fieldName}[{$variation->ID}]"); ?>"><?php echo esc_html($this->fieldLabel); ?></label> &nbsp;
    <span style="display: block;">
        <input type="text" class="input-text" style="max-width: 48%;" name="<?php echo esc_attr("v_{$this->fieldName}[{$variation->ID}]"); ?>" id="<?php echo esc_attr("v_{$this->fieldName}[{$variation->ID}]"); ?>" value="<?php echo esc_html($value); ?>" placeholder="<?php echo __("Enter code", "upc-ean-generator") ?>">
        <?php if (isset($newProductDatabaseCodeId) && isset($newProductDatabaseCodeValue)): ?>
            <input type="hidden" name="<?php echo esc_attr("new_products_database_code_id[{$variation->ID}]"); ?>" value="<?php echo esc_attr($newProductDatabaseCodeId); ?>">
            <input type="hidden" name="<?php echo esc_attr("new_products_database_code_value[{$variation->ID}]"); ?>" value="<?php echo esc_attr($newProductDatabaseCodeValue); ?>">
        <?php endif; ?>
        <button type="button" class="upc-ean-generator-regenerate" style="margin: 4px 0 0 10px; position: relative; top: 2px; display: none;" data-product-id="<?php echo esc_attr($variation->ID); ?>"><?php echo __("Regenerate", "upc-ean-generator") ?></button>
    </span>
</p>
