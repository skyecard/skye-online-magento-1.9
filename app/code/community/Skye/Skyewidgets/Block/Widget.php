<?php
// app/code/community/Skye/Skyewidgets/Block/Widget.php
class Skye_Skyewidgets_Block_Widget extends Mage_Core_Block_Abstract implements Mage_Widget_Block_Interface {
/**
  * Produce links list rendered as html
  *
  * @return string
  */
  protected function _toHtml() {
    $html = '';
    $term = '';    
    $calc_mode = '';
    $merchant_id = self::getData('merchant_id');
    $price_selector = self::getData('price_selector');
    $element_selector = self::getData('element_selector');
    $promo_term = self::getData('promo_term');
    if (!empty($promo_term)) {
      $term = '&term='.$promo_term;
    }

    $display_mode = self::getData('calculate_mode');

    $arr_options = explode(',', $display_mode);

    if (is_array($arr_options) && count($arr_options)) {
      foreach ($arr_options as $mode) {
        Switch ($mode) {
          case 'monthly':
            $calc_mode = '';
            break;
          case 'weekly':
            $calc_mode = '&mode=weekly';
            break;
        }

      }
    }
    if (!empty( $merchant_id)) {
         $html .='<p>';
        $html .= '<script type="text/javascript" id="skye-widget" src="https://d1y94doel0eh42.cloudfront.net/content/scripts/skye-widget.js?id='.$merchant_id.'&price-selector='.$price_selector.'&element='.$element_selector.$term.$calc_mode.'"></script>';
         $html .='</p>';
    } 
     
    return $html;
  }
}