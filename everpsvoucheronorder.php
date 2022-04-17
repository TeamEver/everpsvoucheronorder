<?php
/**
 * Project : everpsvoucheronorder
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link https://www.team-ever.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'everpsvoucheronorder/models/EverPsVoucherOnOrderClass.php';

class Everpsvoucheronorder extends Module
{
    private $html;
    private $postErrors = array();
    private $postSuccess = array();

    public function __construct()
    {
        $this->name = 'everpsvoucheronorder';
        $this->tab = 'pricing_promotion';
        $this->version = '2.2.2';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Ever PS Voucher on first order');
        $this->description = $this->l('Automatically create a voucher on customer first order');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->isSeven = Tools::version_compare(_PS_VERSION_, '1.7', '>=') ? true : false;
    }

    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');
        $this->createDefaultValues();
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionAdminControllerSetMedia');
    }

    private function createDefaultValues()
    {
        Configuration::updateValue('ORDERVOUCHER_MINIMAL', 1);
        Configuration::updateValue('ORDERVOUCHER_TAX', 0);
        Configuration::updateValue('ORDERVOUCHER_ENABLE', 0);
        Configuration::updateValue('ORDERVOUCHER_AMOUNT', 5);
        Configuration::updateValue('ORDERVOUCHER_PERCENT', 0);

        $voucherPrefix = array();
        foreach (Language::getLanguages(false) as $lang) {
            $voucherPrefix[$lang['id_lang']] = 'WELCOME';
        }
        Configuration::updateValue(
            'ORDERVOUCHER_PREFIX',
            $voucherPrefix,
            true
        );

        $voucherDetails = array();
        foreach (Language::getLanguages(false) as $lang) {
            $voucherDetails[$lang['id_lang']] = $this->l('Welcome voucher');
        }
        Configuration::updateValue(
            'ORDERVOUCHER_DETAILS',
            $voucherDetails,
            true
        );
        return true;
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');
        Configuration::deleteByName('ORDERVOUCHER_ZONES_TAX');
        Configuration::deleteByName('ORDERVOUCHER_TAX');
        Configuration::deleteByName('ORDERVOUCHER_ENABLE');
        Configuration::deleteByName('ORDERVOUCHER_MINIMAL');
        Configuration::deleteByName('ORDERVOUCHER_CATEGORY');
        Configuration::deleteByName('ORDERVOUCHER_DETAILS');
        Configuration::deleteByName('ORDERVOUCHER_PREFIX');
        return parent::uninstall();
    }

    public function hookHeader()
    {
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitEverpsvoucheronorderModule')) == true) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            }
        }

        // Display errors
        if (count($this->postErrors)) {
            foreach ($this->postErrors as $error) {
                $this->html .= $this->displayError($error);
            }
        }

        // Display confirmations
        if (count($this->postSuccess)) {
            foreach ($this->postSuccess as $success) {
                $this->html .= $this->displayConfirmation($success);
            }
        }

        $this->context->smarty->assign(array(
            'image_dir' => $this->_path,
        ));

        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/header.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/footer.tpl');
        return $this->html;
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEverpsvoucheronorderModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        $currency = new Currency(
            (int)Configuration::get('PS_CURRENCY_DEFAULT')
        );
        $zones = Zone::getZones(true);
        $selected_cat = json_decode(
            Configuration::get(
                'ORDERVOUCHER_CATEGORY'
            )
        );

        if (!is_array($selected_cat)) {
            $selected_cat = array($selected_cat);
        }

        $tree = array(
            'selected_categories' => $selected_cat,
            'use_search' => true,
            'use_checkbox' => true,
            'id' => 'id_category_tree',
        );

        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'label' => $this->l('Allow vouchers on each order'),
                        'desc' => $this->l('Will generate a voucher on each order if set to yes'),
                        'hint' => $this->l('Else vouchers will be generated on first order only'),
                        'type' => 'switch',
                        'name' => 'ORDERVOUCHER_ENABLE',
                        'values' => array(
                            array(
                                'value' => 1,
                            ),
                            array(
                                'value' => 0,
                            ),
                        ),
                    ),
                    array(
                        'label' => $this->l('Send voucher codes by email'),
                        'desc' => $this->l('Will send generated voucher code to customer by email'),
                        'hint' => $this->l('Else no email will be sent'),
                        'type' => 'switch',
                        'name' => 'ORDERVOUCHER_MAIL',
                        'values' => array(
                            array(
                                'value' => 1,
                            ),
                            array(
                                'value' => 0,
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Coupon code prefix'),
                        'desc' => $this->l('Please set coupon prefix'),
                        'hint' => $this->l('Coupon code will be auto generated'),
                        'name' => 'ORDERVOUCHER_PREFIX',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Voucher details'),
                        'desc' => $this->l('Please type coupon details'),
                        'hint' => $this->l('Coupon code will be auto generated'),
                        'name' => 'ORDERVOUCHER_DETAILS',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Reduction amount'),
                        'desc' => $this->l('Please set reduction amount for voucher'),
                        'hint' => $this->l('Will be used for each voucher generation'),
                        'name' => 'ORDERVOUCHER_AMOUNT',
                        'lang' => false,
                    ),
                    array(
                        'type' => 'date',
                        'label' => $this->l('Date limit for vouchers'),
                        'desc' => $this->l('Please set reduction date limit for voucher'),
                        'hint' => $this->l('Vouchers wont be created after this date'),
                        'name' => 'ORDERVOUCHER_DATE_LIMIT',
                        'lang' => false,
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true,
                        'label' => $this->l('Reduction amount is based on last order total'),
                        'desc' => $this->l('Will generate a voucher based on last order total'),
                        'hint' => $this->l('Reduction type must be set to amount, not percent'),
                        'name' => 'ORDERVOUCHER_LAST_TOTAL',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true,
                        'label' => $this->l('Discount percent or amount'),
                        'desc' => $this->l('Set No for amount'),
                        'hint' => $this->l('Is voucher percent or fixed amount ?'),
                        'name' => 'ORDERVOUCHER_PERCENT',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Minimum amount'),
                        'desc' => $this->l('So that the coupon can be used'),
                        'hint' => $this->l('Else voucher won\'t be available if minimal is not on cart'),
                        'name' => 'ORDERVOUCHER_MINIMAL',
                        'prefix' => $currency->sign,
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true,
                        'label' => $this->l('Apply taxes on the voucher'),
                        'desc' => $this->l('Taxes will be applied on the voucher'),
                        'hint' => $this->l('Else voucher will be without taxes'),
                        'name' => 'ORDERVOUCHER_TAX',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Allow taxes on specific zones'),
                        'hint' => 'These zones will have taxes applied on vouchers',
                        'desc' => $this->l('Leave empty for no use'),
                        'name' => 'ORDERVOUCHER_ZONES_TAX[]',
                        'class' => 'chosen',
                        'identifier' => 'name',
                        'multiple' => true,
                        'options' => array(
                            'query' => $zones,
                            'id' => 'id_zone',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'categories',
                        'name' => 'ORDERVOUCHER_CATEGORY',
                        'label' => $this->l('Allowed categories'),
                        'desc' => 'Choose allowed categories',
                        'hint' => 'Voucher can be used on these categories',
                        'required' => false,
                        'tree' => $tree,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        $voucherDetails = array();
        $voucherPrefix = array();

        foreach (Language::getLanguages(false) as $lang) {
            $voucherDetails[$lang['id_lang']] = (Tools::getValue(
                'ORDERVOUCHER_DETAILS_'
                .$lang['id_lang']
            ))
            ? Tools::getValue(
                'ORDERVOUCHER_DETAILS_'.$lang['id_lang']
            ) : '';
            $voucherPrefix[$lang['id_lang']] = (Tools::getValue(
                'ORDERVOUCHER_DETAILS_'
                .$lang['id_lang']
            ))
            ? Tools::getValue(
                'ORDERVOUCHER_DETAILS_'.$lang['id_lang']
            ) : '';
        }

        return array(
            'ORDERVOUCHER_DATE_LIMIT' => Configuration::get('ORDERVOUCHER_DATE_LIMIT'),
            'ORDERVOUCHER_LAST_TOTAL' => Configuration::get('ORDERVOUCHER_LAST_TOTAL'),
            'ORDERVOUCHER_MINIMAL' => Configuration::get('ORDERVOUCHER_MINIMAL'),
            'ORDERVOUCHER_AMOUNT' => Configuration::get('ORDERVOUCHER_AMOUNT'),
            'ORDERVOUCHER_PERCENT' => Configuration::get('ORDERVOUCHER_PERCENT'),
            'ORDERVOUCHER_TAX' => Configuration::get('ORDERVOUCHER_TAX'),
            'ORDERVOUCHER_ENABLE' => Configuration::get('ORDERVOUCHER_ENABLE'),
            'ORDERVOUCHER_MAIL' => Configuration::get('ORDERVOUCHER_MAIL'),
            'ORDERVOUCHER_ZONES_TAX[]' => json_decode(Configuration::get('ORDERVOUCHER_ZONES_TAX')),
            'ORDERVOUCHER_DETAILS' => (!empty($voucherDetails[(int)Configuration::get('PS_LANG_DEFAULT')]))
            ? $voucherDetails : Configuration::getInt('ORDERVOUCHER_DETAILS'),
            'ORDERVOUCHER_PREFIX' => (!empty($voucherPrefix[(int)Configuration::get('PS_LANG_DEFAULT')]))
            ? $voucherPrefix : Configuration::getInt('ORDERVOUCHER_PREFIX'),
            'ORDERVOUCHER_CATEGORY' => Tools::getValue(
                'ORDERVOUCHER_CATEGORY',
                json_decode(
                    Configuration::get(
                        'ORDERVOUCHER_CATEGORY'
                    )
                )
            ),
        );
    }

    public function postValidation()
    {
        if (((bool)Tools::isSubmit('submitEverpsvoucheronorderModule')) == true) {
            if (Tools::getValue('ORDERVOUCHER_CATEGORY')
                && !Validate::isArrayWithIds(Tools::getValue('ORDERVOUCHER_CATEGORY'))
            ) {
                $this->postErrors[] = $this->l('Error: allowed categories is not valid');
            }
            if (Tools::getValue('ORDERVOUCHER_PERCENT')
                && !Validate::isBool(Tools::getValue('ORDERVOUCHER_PERCENT'))
            ) {
                $this->postErrors[] = $this->l('Error: amount or percent is not valid');
            }
        }
    }

    protected function postProcess()
    {
        $voucherDetails = array();
        foreach (Language::getLanguages(false) as $lang) {
            $voucherDetails[$lang['id_lang']] = (Tools::getValue(
                'ORDERVOUCHER_DETAILS_'
                .$lang['id_lang']
            ))
            ? Tools::getValue(
                'ORDERVOUCHER_DETAILS_'.$lang['id_lang']
            ) : '';
        }
        Configuration::updateValue(
            'ORDERVOUCHER_DETAILS',
            $voucherDetails,
            true
        );
        $voucherPrefix = array();
        foreach (Language::getLanguages(false) as $lang) {
            $voucherPrefix[$lang['id_lang']] = (Tools::getValue(
                'ORDERVOUCHER_PREFIX_'
                .$lang['id_lang']
            ))
            ? Tools::getValue(
                'ORDERVOUCHER_PREFIX_'.$lang['id_lang']
            ) : '';
        }
        Configuration::updateValue(
            'ORDERVOUCHER_PREFIX',
            $voucherPrefix,
            true
        );
        Configuration::updateValue(
            'ORDERVOUCHER_CATEGORY',
            json_encode(Tools::getValue('ORDERVOUCHER_CATEGORY')),
            true
        );
        Configuration::updateValue(
            'ORDERVOUCHER_ZONES_TAX',
            json_encode(Tools::getValue('ORDERVOUCHER_ZONES_TAX')),
            true
        );
        Configuration::updateValue(
            'ORDERVOUCHER_DATE_LIMIT',
            Tools::getValue('ORDERVOUCHER_DATE_LIMIT')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_MINIMAL',
            Tools::getValue('ORDERVOUCHER_MINIMAL')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_AMOUNT',
            Tools::getValue('ORDERVOUCHER_AMOUNT')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_PERCENT',
            Tools::getValue('ORDERVOUCHER_PERCENT')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_TAX',
            Tools::getValue('ORDERVOUCHER_TAX')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_ENABLE',
            Tools::getValue('ORDERVOUCHER_ENABLE')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_MAIL',
            Tools::getValue('ORDERVOUCHER_MAIL')
        );
        Configuration::updateValue(
            'ORDERVOUCHER_LAST_TOTAL',
            Tools::getValue('ORDERVOUCHER_LAST_TOTAL')
        );
    }

    public function hookActionAdminControllerSetMedia()
    {
        $this->context->controller->addCss($this->_path.'views/css/ever.css');
    }

    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $customer = new Customer((int)$order->id_customer);
        $previous_orders = Order::getCustomerOrders(
            (int)$customer->id,
            true
        );
        // We should test if superior than 2, as current order is counted
        if (count($previous_orders) >= 2) {
            return;
        }
        $exists = EverPsVoucherOnOrderClass::getByCustomer(
            $customer->id
        );
        if (!Configuration::get('ORDERVOUCHER_DATE_LIMIT')
            || empty(Configuration::get('ORDERVOUCHER_DATE_LIMIT'))
        ) {
            return;
        }
        $today = date('Y-m-d');
        $expire = Configuration::get('ORDERVOUCHER_DATE_LIMIT');
        $today_time = strtotime($today);
        $expire_time = strtotime($expire);
        if ($today_time > $expire_time) {
            return;
        }
        if ((bool)Configuration::get('ORDERVOUCHER_ENABLE') === false) {
            if ((int)$exists <= 0) {
                $this->createFirstVoucher($customer->id, $order->id);
            }
        } else {
            $this->createFirstVoucher($customer->id, $order->id);
        }
    }

    public function createFirstVoucher($id_customer, $id_order)
    {
        $description = Configuration::getInt('ORDERVOUCHER_DETAILS');
        $prefixx = Configuration::getInt('ORDERVOUCHER_PREFIX');
        $prefix = $prefixx[(int)$this->context->language->id];
        $allowedTaxZones = json_decode(Configuration::get('ORDERVOUCHER_ZONES_TAX'));
        $customer = new Customer((int)$id_customer);
        $customerCountry = $customer->getCurrentCountry((int)$customer->id);
        $country = new Country((int)$customerCountry);
        $last_order = new Order((int)$id_order);
        /* Generate a voucher code */
        $voucher_code = null;

        do {
            $voucher_code = $prefix.''.rand(1000, 100000);
        } while (CartRule::cartRuleExists($voucher_code));

        // Voucher creation and affectation to the customer
        $cart_rule = new CartRule();
        $cart_rule->id_customer = (int)$customer->id;
        $cart_rule->date_from = date('Y-m-d H:i:s');
        $cart_rule->date_to = date(
            'Y-m-d H:i:s',
            strtotime(Configuration::get('ORDERVOUCHER_DATE_LIMIT'))
        );
        $cart_rule->quantity = 1;
        $cart_rule->quantity_per_user = 1;
        $cart_rule->partial_use = 0;
        $cart_rule->code = $voucher_code;
        $cart_rule->cart_rule_restriction = 1;
        // $cart_rule->description = $description[(int)$this->context->language->id];
        $cart_rule->minimum_amount = (float)Configuration::get('ORDERVOUCHER_MINIMAL');
        // First calculate percent for voucher based on last order total
        $last_cart = new Cart(
            (int)$last_order->id_cart
        );
        $last_order_total = Cart::getTotalCart(
            (int)$last_order->id_cart,
            false,
            Cart::BOTH_WITHOUT_SHIPPING
        );
        $percent_rule = (float)$last_order_total * Configuration::get('ORDERVOUCHER_AMOUNT') / 100;
        if ((int)Configuration::get('ORDERVOUCHER_PERCENT')) {
            $cart_rule->reduction_percent = Configuration::get('ORDERVOUCHER_AMOUNT');
        } else {
            if ((bool)Configuration::get('ORDERVOUCHER_LAST_TOTAL') === true) {
                $cart_rule->reduction_amount = $percent_rule;
            } else {
                $cart_rule->reduction_amount = Configuration::get('ORDERVOUCHER_AMOUNT');
            }
        }
        $cart_rule->highlight = 1;
        if (Configuration::get('ORDERVOUCHER_TAX')
            && in_array((int)$country->id_zone, $allowedTaxZones)
        ) {
            $cart_rule->reduction_tax = 1;
        }
        $cart_rule->active = 1;
        $categories = json_decode(Configuration::get('ORDERVOUCHER_CATEGORY'));
        $languages = Language::getLanguages(true);

        foreach ($languages as $language) {
            $cart_rule->name[(int)$language['id_lang']] = $voucher_code;
        }

        $contains_categories = is_array($categories) && count($categories);
        if ($contains_categories) {
            $cart_rule->product_restriction = 1;
        }
        $cart_rule->add();

        //Restrict cartRules with categories
        if ($contains_categories) {
            //Creating rule group
            $id_cart_rule = (int)$cart_rule->id;
            $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule_group (
                id_cart_rule,
                quantity
            ) VALUES (
                '$id_cart_rule',
                1
            )";
            Db::getInstance()->execute($sql);
            $id_group = (int)Db::getInstance()->Insert_ID();

            //Creating product rule
            $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule (
                id_product_rule_group,
                type
            ) VALUES (
                '$id_group',
                'categories'
            )";
            Db::getInstance()->execute($sql);
            $id_product_rule = (int)Db::getInstance()->Insert_ID();

            //Creating restrictions
            $values = array();
            foreach ($categories as $category) {
                $category = (int)$category;
                $values[] = "('$id_product_rule', '$category')";
            }
            $values = implode(',', $values);
            $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule_value (id_product_rule, id_item) VALUES $values";
            Db::getInstance()->execute($sql);
        }
        $currency = new Currency(
            (int)Configuration::get('PS_CURRENCY_DEFAULT')
        );

        if ((int)Configuration::get('ORDERVOUCHER_PERCENT')) {
            $reduction = Configuration::get('ORDERVOUCHER_AMOUNT').'%';
        } else {
            $reduction = Configuration::get('ORDERVOUCHER_AMOUNT').''.$currency->sign;
        }
        if ((bool)Configuration::get('ORDERVOUCHER_LAST_TOTAL') === true) {
            $reduction = $cart_rule->reduction_amount.''.$currency->sign;
        }
        $mini_amount = (float)Configuration::get('ORDERVOUCHER_MINIMAL');
        $date_to = strftime('%d-%m-%Y', strtotime($cart_rule->date_to));
        if ((bool)Configuration::get('ORDERVOUCHER_MAIL') === true) {
            Mail::Send(
                (int)(Configuration::get('PS_LANG_DEFAULT')), // defaut language id
                'everpsvoucheronorder', // email template file to be use
                $this->l('Voucher'), // email subject
                array(
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{voucher_num}' => $voucher_code, // email content
                    '{voucher_amount}' => $reduction,
                    '{voucher_date}' => $date_to,
                    '{mini_amount}' => $mini_amount.''.$currency->sign
                ),
                $customer->email, // receiver email
                $customer->firstname.' '.$customer->lastname, //receiver name
                Configuration::get('PS_SHOP_EMAIL'), //from email address
                Configuration::get('PS_SHOP_NAME'),  //from name
                null,
                null,
                dirname(__FILE__).'/mails/'
            );
        }
        // Save first voucher
        $order_voucher = new EverPsVoucherOnOrderClass();
        $order_voucher->id_customer = (int)$customer->id;
        $order_voucher->id_order = (int)$id_order;
        $order_voucher->email = (string)$customer->email;
        $order_voucher->voucher_code = (string)$voucher_code;
        return $order_voucher->save();
    }
}
