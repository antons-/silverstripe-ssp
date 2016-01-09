<?php
/**
 * ADFS authenticator for silverstripe-ssp
 * 
 * AdfsAuthenticator expects the ADFS server to be sending the following claims:
 *  
 * - E-Mail Address
 * - Windows account name
 * - Given Name
 * - Surname
 * 
 * Has been thoroughly tested with ADFS 2.0, but should work with ADFS 3.0
 *
 * @see https://groups.google.com/forum/#!topic/simplesamlphp/I8IiDpeKSvY
 * @see http://technet.microsoft.com/en-us/library/dd807115.aspx
 * @see http://technet.microsoft.com/en-us/library/dd807068.aspx
 * @see http://technet.microsoft.com/en-us/library/ee913589.aspx
 * @package silverstripe-ssp
 * @author Anton Smith <anton.smith@op.ac.nz>
 */
class AdfsAuthenticator extends SSPAuthenticator
{

    /**
     * Provide custom Silverstripe authentication logic for a SimpleSAMLphp authentication source
     * to authenticate a user
     */
    public function authenticate()
    {
        $attributes = $this->getAttributes();
        
        $email = $attributes["http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress"][0];
        
        $member = Member::get()->filter('Email', $email)->first();
        
        //If the member does not exist in Silverstripe, create them
        if (!$member) {
            $member = new Member();
            $member->Username = $attributes["http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname"][0];
            $member->FirstName =  $attributes["http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname"][0];
            $member->Surname =  $attributes["http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname"][0];
            $member->Email =  $attributes["http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress"][0];
            
            $member->write();
        }
        
        return $member;
    }
}
