<?php
/**
 * Replaces the default SilverStripe {@link Security} class for dealing with SimpleSAMLphp
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
     * Authenticators can be SilverStripe environment specific
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
     * Redirect the user to the URL after logout is complete. If the session contains
     * a BackURL this is used instead
     * @var string
     */
    private static $default_logged_out_url;

    /**
     * Replace the default SilverStripe Security class
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
        self::force_ssl();
        
        return $this->redirect(BASE_URL . 'simplesaml/module.php/core/frontpage_welcome.php');
    }
    
    /**
     * This action is available as a keep alive, so user
     * sessions don't timeout. A common use is in the admin.
     */
	public function ping() {
		return 1;
	}
    
    /**
     * Log the current user into the identity provider, and then SilverStripe
     * @see SimpleSAML_Auth_Simple->login()
     */
    public function login() {
        self::force_ssl();
        
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
        
        return $this->redirect($dest);
    }
    
    /**
     * Log the currently logged in user out of the identity provider
     * @see SimpleSAML_Auth_Simple->logout()
     */
    public function logout() {
        self::force_ssl();
        
        if(isset($_GET['BackURL'])) Session::set("BackURL", $_GET['BackURL']);
        
        $auth = SSPAuthFactory::get_authenticator();
        
        $auth->logout(array(
            'ReturnTo' => '/Security/loggedout'
        ));
    }

    /**
     * Log the currently logged in user out of the local SilverStripe website.
     * This function should only be called after logging out of the identity provider.
     *
     * @see logout()
     */
    public function loggedout() {
        self::force_ssl();
        
        //Log out SilverStripe members
        if($member = Member::currentUser()) {
            $member->logout();
        }
        
        Cookie::force_expiry('SimpleSAMLAuthToken');
        
        //Use the BackURL for redirection if avaiable, or use the default logged out URL
        $backUrl = Session::get('BackURL');
        
        $dest = !empty($backUrl) ? $backUrl :  $this->config()->default_logged_out_url;
        
        Session::clear('BackURL');
    
        return $this->redirect($dest);
    }

    /**

     * Attempt to passively authenticate the user with the identity provider, then SilverStripe.
     * 
     * If the user is not authenticated in SilverStripe but is on the identity provider, this will 
     * passively authenticate them in SilverStripe and redirect them to the current page. If the 
     * user is already authenticated in SilverStripe this function returns nothing.
     * 
     * Passive authentication is not enabled by default and an explicit call to 'SSPSecurity::passive_login()'
     * needs to be added to your 'Page_Controller->init()' function.
     * 
     * Please note that passive login only works with the default SSPAuthenticator. Currently it
     * is not possible to specify a custom SSPAuthenticator on demand.
     * 
     * @see SSPSecurity->login()
     */
    public static function passive_login() {
        self::force_ssl();
        
        $auth = self::get_authenticator();
        
        $attempted = Session::get('ssp_passive_attempted');
        
        if (!$auth->isAuthenticated() && !isset($attempted)) {
            Session::set('ssp_passive_attempted', 1);
            $auth->login(array(
                'isPassive' => TRUE,
                'ErrorURL' => $_SERVER['REQUEST_URI'],
                'ReturnTo' => '/Security/login',
                'ReturnCallback' => $auth->loginComplete()
            ));
        }
    }

    /**
     * Redirects the user to the identity provider portal for login
     */
    public function LoginForm() {
        self::force_ssl();
        
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
    private static function force_ssl() {
        $mode = self::config()->force_ssl;
        
        if(!is_bool($mode)) {
            user_error("Expected boolean in SSPSecurity::force_ssl", E_USER_ERROR);
        }
        
        if($mode) {
            Director::forceSSL();
        }
    }
}
