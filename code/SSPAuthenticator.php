<?php

/**
 * Extends the SimpleSAML_Auth_Simple class to allow custom authentication logic depending on
 * the authentication source
 * 
 * @package silverstripe-ssp
 * @author Anton Smith <anton.smith@op.ac.nz>
 */
abstract class SSPAuthenticator extends SimpleSAML_Auth_Simple {
    
    /**
     * Provide custom Silverstripe authentication logic for a SimpleSAMLphp authentication source
     * to authenticate a user
     */
    abstract function authenticate();
    
    /**
     * When login is complete, save the SSPAuthentication object to the session
     */
    final public function loginComplete() {
        
        //Use the same session as SimpleSAMLphp to avoid session state loss
        Session::start(SimpleSAML_Session::getInstance()->getSessionId());

        Session::set('ssp_current_auth', serialize($this));

        Session::save();
    }
}
