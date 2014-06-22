<?php
class Jakopec_InchooPayDay_Block_Form_InchooPayDay extends Mage_Payment_Block_Form_Ccsave
{
	
	
	
	public function getCcAvailableTypes()
    {
    
		
        $types = $this->_getConfig()->getCcTypes();
        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
            	//ovo bi trebalo vući iz konfiguracije, probao sam, ne ide a ne da mi se :)
                $availableTypes = explode(',', "VI,DC");
				
                foreach ($types as $code=>$name) {
                	 
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
		 
		 
    }
	
}