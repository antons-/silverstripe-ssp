<?php

//If $force_ss_auth is true, override SSPSecurity and return the default SS authentication controller
if(!Config::inst()->get('SSPSecurity', 'enable_ssp_auth')) {
    Config::inst()->update('Director', 'rules', $rule = array('Security//$Action/$ID/$OtherID' => 'Security'));
}