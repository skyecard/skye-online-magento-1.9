<?php
class Skye_Skyewidgets_Model_Options {
/**
  * Provide available options as a value/label array
  *
  * @return array
  */
  public function toOptionArray() {
    return array(
      array('value' => 'monthly', 'label' => 'Monthly'),
      array('value' => 'weekly', 'label' => 'Weekly'),
    );
  }
}