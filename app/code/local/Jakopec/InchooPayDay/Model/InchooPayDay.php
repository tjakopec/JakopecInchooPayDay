<?php
class Jakopec_InchooPayDay_Model_InchooPayDay extends Mage_Payment_Model_Method_Abstract
{
	

	protected $_code = 'inchoopayday';
	//protected $_formBlockType = 'inchoopayday/form_inchoopayday';
	//protected $_infoBlockType = 'inchoopayday/info_inchoopayday';

	protected $_canAuthorize            = true;
	protected $_canCapture              = true;
	protected $_canRefund               = true;
	protected $_canUseCheckout=true;
	protected $_canSaveCc = false; 
	protected $_isInitializeNeeded = true;

   protected $_canUseInternal = true;

	

	public function process($data){
	
		if($data['cancel'] == 1){
		 $order->getPayment()
		 ->setTransactionId(null)
		 ->setParentTransactionId(time())
		 ->void();
		 $message = 'Unable to process Payment';
		 $order->registerCancellation($message)->save();
		}
	}

	/** For capture **/
	public function capture(Varien_Object $payment, $amount)
	{
		
		//Mage::log('stigao capture', null, 'jakopec.log');
		//ovo bi možda moglo ići u validate
		if($amount<1){
			
			Mage::throwException("minimalni iznos je 1.00 u bilo kojoj valuti");
			
			return false;
		}
		
		
		$order = $payment->getOrder();
		$currencyDesc = $order->getOrderCurrency()->getCurrencyCode();
		$dozvoljeneValute = explode(',', "HRK,EUR");
		if (!in_array($currencyDesc, $dozvoljeneValute)) {
                        Mage::throwException("dozvoljene valute su Hrvatska Kuna i Euro");
			
			return false;
         }
		
		
		//sad vidim kako se nisam trebao zezati s custom listom kartica već sam mogao ovdje kontrolirati

		
		
		$result = $this->apiPozivPlacanje($payment,$amount);
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
		} else {

			if($result['status'] == 1){
				$payment->setTransactionId($result['transaction_id']);
				$payment->setIsTransactionClosed(1);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('key1'=>'value1','key2'=>'value2'));
				$order->addStatusToHistory($order->getStatus(), 'Payment Sucessfully Placed with Transaction ID '.$result['transaction_id'], false);
				$order->save();
			}else{
				Mage::throwException($errorMsg);
			}
		}
		if($errorMsg){
			Mage::throwException($errorMsg);
		}

		return $this;
	}

	
	public function refund(Varien_Object $payment, $amount){
		//Mage::log('stigao refund', null, 'jakopec.log');
		
		$order = $payment->getOrder();
		$result = $this->apiPozivPovrat($payment,$amount);
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
			Mage::throwException($errorMsg);
		}else {
			if($result['status'] != 1){
				Mage::throwException($this->_getHelper()->__('Pogreška prilikom API poziva povrata sredstava'));
			}

			// Add the comment and save the order
		}
		return $this;

	}

	private function apiPozivPlacanje(Varien_Object $payment, $amount){
		
		$order = $payment->getOrder();
		$billingaddress = $order->getBillingAddress();
		$totals = number_format($amount, 2, '.', '');
		$orderId = $order->getIncrementId();
		$currencyDesc = $order->getOrderCurrency()->getCurrencyCode();

		//ovo je trebalo ići u konfiguuraciju ili u bazu
		$url = "https://inchoo.net/payday/capture/";
		$fields = array(
				'order_id'=> $orderId,
				'name'=> $billingaddress->getData('firstname') . " " . $billingaddress->getData('lastname'),
				//'name'=> "Dobar zadatak :) ostalo mi još ograničenja, refund, preimenovanje tuđeg koda u moj te stavljanje na GITHUB. Pozdrav!",
				'mail'=> $billingaddress->getData('email'),
				'address'=> $billingaddress->getData('street'),
				'zip'=> $billingaddress->getData('postcode'),
				'city'=> $billingaddress->getData('city'),
				'state'=> "samodanefale" . $billingaddress->getData('region'),
				'country'=> $billingaddress->getData('country_id'),
				'amount'=>$totals,
				'currency'=>$currencyDesc,
				'cc'=> $payment->getCcNumber(),
				'cvv'=> $payment->getCcCid()
		);
		$rez=$this->apiPoziv($url, $fields);
		//Mage::log($rez, null, 'jakopec.log');
		return array('status'=>$rez->status_code,'transaction_id' => $rez->transaction_id);
	}


	private function apiPozivPovrat(Varien_Object $payment, $amount){
		
		$order = $payment->getOrder();
		$orderId = $order->getIncrementId();
		$transactionId=$payment->getRefundTransactionId();
	                                                              
		//ovo je trebalo ići u konfiguuraciju ili u bazu
		$url = "https://inchoo.net/payday/refund/";
		$fields = array(
				'order_id'=> $orderId,
				'transaction_id'=> $transactionId
		);
		$rez=$this->apiPoziv($url, $fields);
		//Mage::log( $fields, null, 'jakopec.log');
		return array('status'=>$rez->status_code);
	}

	private function apiPoziv($url, $fields){
		
		$fields_string="";
		foreach($fields as $key=>$value) {
		$fields_string .= $key.'='.$value.'&';
		}
		$fields_string = substr($fields_string,0,-1);
		//open connection
		
		//Mage::log($fields_string, null, $this->getCode().'.log');
		
		$ch = curl_init($url);
		//set the url, number of POST vars, POST data
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION ,1);
		curl_setopt($ch, CURLOPT_HEADER ,0); // DO NOT RETURN HTTP HEADERS
		curl_setopt($ch, CURLOPT_RETURNTRANSFER ,1); // RETURN THE CONTENTS OF THE CALL
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120); // Timeout on connect (2 minutes)
		//execute post
		$result = curl_exec($ch);
		curl_close($ch);
		
		return json_decode($result);
	}



	
	
	
	
	public function validate()
    {
    	
		//Mage::log("stigao validate", null, 'jakopec.log');
        /*
        * calling parent validate function
        */
        parent::validate();

        $info = $this->getInfoInstance();
        $errorMsg = false;
		//ovo bi trebalo vući iz konfiguracije, probao sam, ne ide a ne da mi se :)
        $availableTypes = explode(',', "VI,DC");

        $ccNumber = $info->getCcNumber();

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = 'OT';
		//Mage::log($info->getCcType(), null, 'jakopec.log');
        if (in_array($info->getCcType(), $availableTypes)){
            if ($this->validateCcNum($ccNumber)
                // Other credit card type number validation
                || ($this->OtherCcType($info->getCcType()) && $this->validateCcNumOther($ccNumber))) {

                $ccTypeRegExpList = array(
                    // Visa
                    'VI'  => '/^4[0-9]{12}([0-9]{3})?$/',
                    // Master Card
                    'DC'  => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/'
                );

                $specifiedCCType = $info->getCcType();
                if (array_key_exists($specifiedCCType, $ccTypeRegExpList)) {
                    $ccTypeRegExp = $ccTypeRegExpList[$specifiedCCType];
                    if (!preg_match($ccTypeRegExp, $ccNumber)) {
                        $errorMsg = Mage::helper('payment')->__('Credit card number mismatch with credit card type.');
                    }
                }
            }
            else {
                $errorMsg = Mage::helper('payment')->__('Invalid Credit Card Number');
            }

        }
        else {
            $errorMsg = Mage::helper('payment')->__('Credit card type is not allowed for this payment method.');
        }

        //validate credit card verification number
        if ($errorMsg === false && $this->hasVerification()) {
            $verifcationRegEx = $this->getVerificationRegEx();
            $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
            if (!$info->getCcCid() || !$regExp || !preg_match($regExp ,$info->getCcCid())){
                $errorMsg = Mage::helper('payment')->__('Please enter a valid credit card verification number.');
            }
        }

        if ($ccType != 'SS' && !$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            $errorMsg = Mage::helper('payment')->__('Incorrect credit card expiration date.');
        }

        if($errorMsg){
            Mage::throwException($errorMsg);
        }

        //This must be after all validation conditions
        if ($this->getIsCentinelValidationEnabled()) {
            $this->getCentinelValidator()->validate($this->getCentinelValidationData());
        }

        return $this;
    }



















public function processCreditmemo($creditmemo, $payment){
		return parent::processCreditmemo($creditmemo, $payment);
	}
	
	public function processBeforeRefund($invoice, $payment){
		return parent::processBeforeRefund($invoice, $payment);
	}
	




//pokupio iz Mage_Payment_Model_Method_Cc koja je nasljeđivala abstract 
	 public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
            ->setCcOwner($data->getCcOwner())
            ->setCcLast4(substr($data->getCcNumber(), -4))
            ->setCcNumber($data->getCcNumber())
            ->setCcCid($data->getCcCid())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
            ->setCcSsIssue($data->getCcSsIssue())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
            ;
        return $this;
    }

    /**
     * Prepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();
        if ($this->_canSaveCc) {
            $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
        }
        //$info->setCcCidEnc($info->encrypt($info->getCcCid()));
        $info->setCcNumber(null)
            ->setCcCid(null);
        return $this;
    }
	
	
	public function hasVerification()
    {
        $configData = $this->getConfigData('useccv');
        if(is_null($configData)){
            return true;
        }
        return (bool) $configData;
    }

    public function getVerificationRegEx()
    {
        $verificationExpList = array(
            'VI' => '/^[0-9]{3}$/', 
            'DC' => '/^[0-9]{3}$/'
        );
        return $verificationExpList;
    }

    protected function _validateExpDate($expYear, $expMonth)
    {
        $date = Mage::app()->getLocale()->date();
        if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1)
            || ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1))
        ) {
            return false;
        }
        return true;
    }

    public function OtherCcType($type)
    {
        return $type=='OT';
    }

    /**
     * Validate credit card number
     *
     * @param   string $cc_number
     * @return  bool
     */
    public function validateCcNum($ccNumber)
    {
        $cardNumber = strrev($ccNumber);
        $numSum = 0;

        for ($i=0; $i<strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);

            /**
             * Double every second digit
             */
            if ($i % 2 == 1) {
                $currentNum *= 2;
            }

            /**
             * Add digits of 2-digit numbers together
             */
            if ($currentNum > 9) {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $numSum += $currentNum;
        }

        /**
         * If the total has no remainder it's OK
         */
        return ($numSum % 10 == 0);
    }

    /**
     * Other credit cart type number validation
     *
     * @param string $ccNumber
     * @return boolean
     */
    public function validateCcNumOther($ccNumber)
    {
        return preg_match('/^\\d+$/', $ccNumber);
    }

    /**
     * Check whether there are CC types set in configuration
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return $this->getConfigData('cctypes', ($quote ? $quote->getStoreId() : null))
            && parent::isAvailable($quote);
    }

    /**
     * Whether centinel service is enabled
     *
     * @return bool
     */
    public function getIsCentinelValidationEnabled()
    {
        return false !== Mage::getConfig()->getNode('modules/Mage_Centinel') && 1 == $this->getConfigData('centinel');
    }

    /**
     * Instantiate centinel validator model
     *
     * @return Mage_Centinel_Model_Service
     */
    public function getCentinelValidator()
    {
        $validator = Mage::getSingleton('centinel/service');
        $validator
            ->setIsModeStrict($this->getConfigData('centinel_is_mode_strict'))
            ->setCustomApiEndpointUrl($this->getConfigData('centinel_api_url'))
            ->setStore($this->getStore())
            ->setIsPlaceOrder($this->_isPlaceOrder());
        return $validator;
    }

    /**
     * Return data for Centinel validation
     *
     * @return Varien_Object
     */
    public function getCentinelValidationData()
    {
        $info = $this->getInfoInstance();
        $params = new Varien_Object();
        $params
            ->setPaymentMethodCode($this->getCode())
            ->setCardType($info->getCcType())
            ->setCardNumber($info->getCcNumber())
            ->setCardExpMonth($info->getCcExpMonth())
            ->setCardExpYear($info->getCcExpYear())
            ->setAmount($this->_getAmount())
            ->setCurrencyCode($this->_getCurrencyCode())
            ->setOrderNumber($this->_getOrderId());
        return $params;
    }

    /**
     * Order increment ID getter (either real from order or a reserved from quote)
     *
     * @return string
     */
    private function _getOrderId()
    {
        $info = $this->getInfoInstance();

        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
            if (!$info->getQuote()->getReservedOrderId()) {
                $info->getQuote()->reserveOrderId();
            }
            return $info->getQuote()->getReservedOrderId();
        }
    }

    /**
     * Grand total getter
     *
     * @return string
     */
    private function _getAmount()
    {
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return (double)$info->getOrder()->getQuoteBaseGrandTotal();
        } else {
            return (double)$info->getQuote()->getBaseGrandTotal();
        }
    }

    /**
     * Currency code getter
     *
     * @return string
     */
    private function _getCurrencyCode()
    {
        $info = $this->getInfoInstance();

        if ($this->_isPlaceOrder()) {
        return $info->getOrder()->getBaseCurrencyCode();
        } else {
        return $info->getQuote()->getBaseCurrencyCode();
        }
    }

    /**
     * Whether current operation is order placement
     *
     * @return bool
     */
    private function _isPlaceOrder()
    {
        $info = $this->getInfoInstance();
        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            return false;
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            return true;
        }
    }
	
	// završio iz Mage_Payment_Model_Method_Cc
}
?>
