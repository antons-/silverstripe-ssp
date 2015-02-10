<?php
/**
 * Replaces the default Silverstripe {@link Security} class for dealing with SimpleSAMLphp
 * authentication
 * 
 * @package silverstripe-ssp
 * @author Anton Smith <anton.smith@op.ac.nz>
 */
class SSPSecurity extends Controller {

    /**
     * A list of all the authenticators
     * @var array
     */
    private static $authenticators;
    
    /**
     * Use this authenticator as the default when an authentication source isn't specified.
     * Authenticators can be Silverstripe environment specific
     * @var mixed
     */
    private static $default_authenticator;
    
    /**
     * Redirect the user to the URL after login is complete. If the session contains
     * a BackURL this is used instead
     * @var string
     */
    private static $default_logged_in_url;

    /**
     * Replace the default Silverstripe Security class
     * @var boolean
     */
    private static $enable_ssp_auth = true;
    
    /**
     * Force HTTPS mode when executing authentication functions
     * @var boolean
     */
    private static $force_ssl = true;
    
    private static $allowed_actions = array( 
        'index',
        'login',
        'logout',
        'loggedout',
        'ping',
        'LoginForm'
    );

    public function init() {
        parent::init();

        // Prevent clickjacking, see https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
        $this->response->addHeader('X-Frame-Options', 'SAMEORIGIN');
        
        $this->extend('onAfterInit');
    }

    public function index() {
        $this->forceSSL();
        
        return $this->redirect(BASE_URL . 'simplesaml/module.php/core/frontpage_welcome.php');
    }
    
    /**
     * Log the current user into the identity provider, and then Silverstripe
     * @see SimpleSAML_Auth_Simple->login()
     */
    public function login() {
        $this->forceSSL();
        
        if(isset($_GET['BackURL'])) Session::set("BackURL", $_GET['BackURL']);

        $auth = SSPAuthFactory::get_authenticator();
        
        $auth->requireAuth(array(
            'ReturnTo' => '/Security/login',
            'ReturnCallback' => $auth->loginComplete()
        ));
        
        //SSPAuthenticator->authenticate() must return a Member
        $member = $auth->authenticate();

        if(!$member instanceof Member) {
            user_error(get_class($auth) . ' does not return a valid Member');
        }

        $member->login();
        
        //Use the BackURL for redirection if avaiable, or use the default logged in URL
        $backUrl = Session::get('BackURL');
        
        $dest = !empty($backUrl) ? $backUrl :  $this->config()->default_logged_in_url;
        
        Session::clear('BackURL');
        
        //Callback for after login
        $auth->onAfterLogin();
        
        return $this->redirect($dest);
    }
    
    /**
     * Log the currently logged in user out of the identity provider
     * @see SimpleSAML_Auth_Simple->logout()
     */
    public function logout() {
        $this->forceSSL();
        
        $auth = SSPAuthFactory::get_authenticator();
        
        $auth->logout(array(
            'ReturnTo' => '/Security/loggedout'
        ));
    }

    /**
     * Log the currently logged in user out of the local Silverstripe website.
     * This function should only be called after logging out of the identity provider.
     *
     * @see logout()
     */
    public function loggedout() {
        $this->forceSSL();
        
        //Log out Silverstripe members
        if($member = Member::currentUser()) {
            $member->logout();
        }
        
        $auth = SSPAuthFactory::get_authenticator();
        
        $auth->logoutComplete();
        
        //Callback for after logout
        $auth->onAfterLogout();;
    
        return $this->redirect(str_replace('https', 'http', Director::absoluteBaseURL()));
    }
    
    /**
     * Redirects the user to the identity provider portal for login
     */
    public function LoginForm() {
        $this->forceSSL();
        
        if(isset($_SERVER['HTTP_REFERER'])) Session::set("BackURL", $_SERVER['HTTP_REFERER']);
        
        $auth = SSPAuthFactory::get_authenticator();
        
        $auth->login(array(
            'ForceAuthn' => TRUE,
            'ReturnTo' => '/Security/login',
            'ReturnCallback' => $auth->loginComplete()
        ));
    }
    
    /**
     * Forces HTTPS mode if set in the configuration
     */
    private function forceSSL() {
        $mode = $this->config()->force_ssl;
        
        if(!is_bool($mode)) {
            user_error("Expected boolean in SSPSecurity::force_ssl", E_USER_ERROR);
        }
        
        if($mode) {
            Director::forceSSL();
        }
    }
}
