<?php
/**
 * Payment CC Types Source Model
 *
 * @category	Inchoo
 * @package		Inchoo_Stripe
 * @author		Ivan Weiler <ivan.weiler@gmail.com>
 * @copyright	Inchoo (http://inchoo.net)
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Jakopec_InchooPayDay_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
	protected $_allowedTypes = array('VI','DC');

}
