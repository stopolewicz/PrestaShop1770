<?php

class PageNotFoundController extends PageNotFoundControllerCore
{
    public function init()
    {
        // Get the requested URI from the context
        $requestUri = Context::getContext()->shop->getRequestURI();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;

        // 1. Check if the URL looks like a standard PrestaShop product URL (e.g., /123-product-name.html)
        if (preg_match('/\/(\d+)-[a-zA-Z0-9-]+\.html$/', $requestUri, $matches)) {
            $id_product = (int)$matches[1];

            // Redirect URL, defaults to homepage
            $redirectUrl = $this->context->link->getPageLink('index', true);

            if ($id_product > 0) {
                // 2. Search for the product (active or inactive) in the database using its ID
                // This is safer for multi-store as it checks the product_shop table
                $sql = 'SELECT ps.id_category_default
                        FROM `' . _DB_PREFIX_ . 'product` p
                        INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON (p.id_product = ps.id_product AND ps.id_shop = ' . $id_shop . ')
                        WHERE p.id_product = ' . $id_product;

                $id_category_default = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

                if ($id_category_default) {
                    // 3. Product was found, now check its category
                    $category = new Category((int)$id_category_default, $id_lang);

                    if (Validate::isLoadedObject($category) && $category->active) {
                        // 3a. Category is valid and active, set it as redirect URL
                        $redirectUrl = $this->context->link->getCategoryLink($category, null, $id_lang);
                    } elseif (Validate::isLoadedObject($category) && $category->id_parent) {
                        // 3b. Category is inactive, try the parent category.
                        $parentCategory = new Category($category->id_parent, $id_lang);
                        if (Validate::isLoadedObject($parentCategory) && $parentCategory->active) {
                            $redirectUrl = $this->context->link->getCategoryLink($parentCategory, null, $id_lang);
                        }
                    }
                }
            }

            // 4. Perform the redirect to either the found category or the homepage
            Tools::redirect($redirectUrl, __PS_BASE_URI__, null, 'HTTP/1.1 301 Moved Permanently');
            exit;
        }

        // 5. URL does not look like a product URL, proceed with normal 404 page.
        parent::init();
    }
}
