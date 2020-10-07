<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class BNDuplicateCartRule extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'bnduplicatecartrule';
        $this->tab = 'administration';
        $this->version = '0.0.1';
        $this->author = 'Brand New srl';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Duplicate Cart Rule');
        $this->description = $this->l('Select a cart rule (coupon/voucher/discounts) and massively duplicate it.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->prefix = Tools::strtoupper($this->name);
    }

    public function getContent()
    {
        $output = '';

        if (((bool)Tools::isSubmit('submit' . get_class($this) . 'Module')) == true) {
            $output .= $this->postProcess();
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . get_class($this) . 'Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => [
                $this->prefix . '_ORIGINAL_CART_RULE' => 0,
                $this->prefix . '_NUM_DUPLICATES' => 0,
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'name' => $this->prefix . '_ORIGINAL_CART_RULE',
                        'label' => $this->l('Original Rule'),
                        'desc' => $this->l('Cart rule to be duplicated.'),
                        'options' => array(
                            'query' => $this->getCartRules(),
                            'id' => 'id_cart_rule',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => $this->prefix . '_NUM_DUPLICATES',
                        'label' => $this->l('Number of Duplicates'),
                        'desc' => $this->l('How many copies are created.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Generate'),
                ),
            ),
        );
    }

    /**
     * Get the shop Cart Rules.
     *
     * @return array An array of Cart Rules from DB
     */
    protected static function getCartRules()
    {
        $cart_rules = Db::getInstance()->executeS('
            SELECT cr.`id_cart_rule`, crl.`name` FROM ' . _DB_PREFIX_ . 'cart_rule AS cr
            JOIN ' . _DB_PREFIX_ . 'cart_rule_lang AS crl ON cr.`id_cart_rule` = crl.`id_cart_rule`
            WHERE crl.`id_lang` = ' . Context::getContext()->language->id . '
            ORDER BY cr.`id_cart_rule` ASC
        ');

        foreach ($cart_rules as &$cr) {
            $cr['name'] = $cr['name'] . ' (' . $cr['id_cart_rule'] . ')';
        }

        array_unshift($cart_rules, array(
            'id_cart_rule' => 0,
            'name' => '----'
        ));

        return $cart_rules;
    }

    protected function postProcess()
    {
        $original = (int) Tools::getValue($this->prefix . '_ORIGINAL_CART_RULE');
        $num_duplicates = (int) Tools::getValue($this->prefix . '_NUM_DUPLICATES');

        if (!$original || !$num_duplicates) {
            return $this->displayError($this->l('Missing a parameter.'));
        }

        if (!$this->duplicateCartRule($original, $num_duplicates)) {
            return $this->displayError($this->l('An error occurred.'));
        }

        return $this->displayConfirmation(sprintf($this->l('The cart rule %s has been duplicated %d times.'), $original, $num_duplicates));
    }

    protected function duplicateCartRule($id_cart_rule, $num_copies)
    {
        $cart_rule = new CartRule($id_cart_rule);
        if (!Validate::isLoadedObject($cart_rule)) {
            return false;
        }

        for ($i = 0; $i < $num_copies; $i += 1) {
            // generate a new code until it is not found in the database
            do {
                $new_code = Tools::passwdGen(8, 'NO_NUMERIC');
            } while (CartRule::getCartsRuleByCode($new_code, $this->context->language->id));

            $new_rule = clone $cart_rule;
            $new_rule->id = 0;
            $new_rule->code = $new_code;
            $new_rule->save();
        }

        return true;
    }
}
