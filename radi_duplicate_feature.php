<?php
/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Radi_duplicate_feature extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'radi_duplicate_feature';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Raditoweb';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('رادی فیچر کپی');
        $this->description = $this->l('کپی کردن از ویژگی های محصول و انتقال به محصول دیگر');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.0');
    }

    public function install()
    {
        Configuration::updateValue('RADI_DUPLICATE_FEATURE_id_product_source', 0);
        Configuration::updateValue('RADI_DUPLICATE_FEATURE_id_product_target', 0);
        return parent::install();
    }

    public function uninstall()
    {
        Configuration::deleteByName('RADI_DUPLICATE_FEATURE_id_product_source');
        Configuration::deleteByName('RADI_DUPLICATE_FEATURE_id_product_target');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitRADI_DUPLICATE_FEATUREModule')) == true) {
            $result=$this->postProcess();
            $this->context->smarty->assign('resultProcess', $result);
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRADI_DUPLICATE_FEATUREModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('تنظیمات'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('شناسه محصولی قصد دارید ویژگی ها از این محصول کپی شود'),
                        'name' => 'RADI_DUPLICATE_FEATURE_id_product_source',
                        'label' => $this->l('شناسه محصول مبدا'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('شناسه محصولی قصد دارید ویژگی ها به روی این محصول کپی شود'),
                        'name' => 'RADI_DUPLICATE_FEATURE_id_product_target',
                        'label' => $this->l('شناسه محصول مقصد'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'RADI_DUPLICATE_FEATURE_id_product_source' => Configuration::get('RADI_DUPLICATE_FEATURE_id_product_source'),
            'RADI_DUPLICATE_FEATURE_id_product_target' => Configuration::get('RADI_DUPLICATE_FEATURE_id_product_target'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $source = (int)Tools::getValue('RADI_DUPLICATE_FEATURE_id_product_source');
        $target = (int)Tools::getValue('RADI_DUPLICATE_FEATURE_id_product_target');
        if (!$source || !$target)
            return ['result' => false, 'message' => 'محصول مبدا یا مقصد مشخص نشده است'];
        return $this->copyFeatures($source, $target);
    }


    private function copyFeatures($source_id, $target_id)
    {
        $db = Db::getInstance();
        $db->delete('feature_product', 'id_product = ' . (int)$target_id);
        $features = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'feature_product` WHERE id_product = ' . (int)$source_id);
        if (!$features) {
            return ['result' => false, 'message' => 'محصول مورد نظر فاقد ویژگی است'];
        }
        foreach ($features as $feature) {
            $db->insert('feature_product', [
                'id_feature' => (int)$feature['id_feature'],
                'id_product' => (int)$target_id,
                'id_feature_value' => (int)$feature['id_feature_value'],
            ]);
        }
        return ['result' => true, 'message' => 'ویژگی های محصول کپی شد'];

    }
}
