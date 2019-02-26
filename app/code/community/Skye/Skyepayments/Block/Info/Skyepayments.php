<?php
/**
 * Class Skye_Skyepayments_Info_Form_Skyepayments
 * @Description Code behind for the custom Skye payment info block.
 * @Remarks Invokes the view template in a distant folder structure: ~/app/design/frontend/base/default/template/skyepayments/info.phtml
 *
 */
class Skye_Skyepayments_Block_Info_Skyepayments extends Mage_Payment_Block_Info
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('skyepayments/info.phtml');
    }
}