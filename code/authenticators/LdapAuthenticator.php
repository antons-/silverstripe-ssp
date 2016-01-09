<?php
/**
 * LDAP authenticator for silverstripe-ssp
 * @package silverstripe-ssp
 * @author Anton Smith <anton.smith@op.ac.nz>
 */
class LdapAuthenticator extends SSPAuthenticator
{

    /**
     * Provide custom Silverstripe authentication logic for a SimpleSAMLphp authentication source
     * to authenticate a user
     */
    public function authenticate()
    {
        $attributes = $this->getAttributes();
 
        $email = $attributes['mail'][0];

        $member = Member::get()->filter('Email', $email)->first();
        
        //If the member does not exist in Silverstripe, create them
        if (!$member) {
            $member = new Member();
            $member->Username = $attributes['sAMAccountName'][0];
            $member->FirstName =  $attributes['givenName'][0];
            $member->Surname =  $attributes['sn'][0];
            $member->Email =  $attributes['mail'][0];
            
            $member->write();
        }
        
        return $member;
    }
}
