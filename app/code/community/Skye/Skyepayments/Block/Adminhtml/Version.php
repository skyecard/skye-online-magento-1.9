<?php

/**
 * Class Skye_Skyepayments_Block_Form_Skyepayments
 * @Description Code behind for the custom Skye payment form.
 * @Remarks Invokes the view template in a distant folder structure: ~/app/design/frontend/base/default/template/skyepayments/form.phtml
 *
 */
class Skye_Skyepayments_Block_Adminhtml_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return (string)Mage::getConfig()->getNode()->modules->Skye_Skyepayments->version;
    }
}