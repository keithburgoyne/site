<?php

require_once 'Site/SiteSessionModule.php';
require_once 'Site/dataobjects/SiteAccount.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Swat/SwatDate.php';
require_once 'Swat/SwatForm.php';

/**
 * Web application module for sessions with accounts.
 *
 * Provides methods for account login/logout and accessing dataobjects in the
 * session.
 *
 * @package   Site
 * @copyright 2006-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAccountSessionModule extends SiteSessionModule
{
	// {{{ protected properties

	/**
	 * @var array
	 */
	protected $login_callbacks = array();

	/**
	 * @var array
	 */
	protected $logout_callbacks = array();

	// }}}
	// {{{ public function depends()

	/**
	 * Gets the module features this module depends on
	 *
	 * The site account session module depends on the SiteDatabaseModule
	 * feature.
	 *
	 * @return array an array of {@link SiteApplicationModuleDependency}
	 *                        objects defining the features this module
	 *                        depends on.
	 */
	public function depends()
	{
		$depends = parent::depends();
		$depends[] = new SiteApplicationModuleDependency('SiteDatabaseModule');
		return $depends;
	}

	// }}}
	// {{{ public function login()

	/**
	 * Logs the current session into a {@link SiteAccount}
	 *
	 * @param string $email the email address of the account to login.
	 * @param string $password the password of the account to login.
	 * @param boolean $regenerate_id optional. Whether or not to regenerate
	 *                                the session identifier on successful
	 *                                login. Defaults to
	 *                                {@link SiteSessionModule::REGENERATE_ID}.
	 *
	 * @return boolean true if the account was successfully logged in and false
	 *                       if the email/password pair did not match an
	 *                       account.
	 */
	public function login($email, $password,
		$regenerate_id = SiteSessionModule::REGENERATE_ID)
	{
		if ($this->isLoggedIn())
			$this->logout();

		$account = $this->getNewAccountObject();

		$instance = ($this->app->hasModule('SiteMultipleInstanceModule')) ?
			$this->app->instance->getInstance() : null;

		if ($account->loadWithEmail($email, $instance) &&
			$account->isCorrectPassword($password)) {

			// No Crypt?! Crypt!
			if (!$account->isCryptPassword()) {
				$account->setPassword($password);
				$account->save();
			}

			$this->activate();
			$this->account = $account;

			if ($regenerate_id) {
				$this->regenerateId();
			}

			$this->setAccountCookie();
			$this->runLoginCallbacks();

			// save last login date
			$now = new SwatDate();
			$now->toUTC();
			$this->account->updateLastLoginDate($now);
		}

		return $this->isLoggedIn();
	}

	// }}}
	// {{{ public function loginById()

	/**
	 * Logs the current session into a {@link SiteAccount} using an id
	 *
	 * @param integer $id The id of the {@link SiteAccount} to log into.
	 * @param boolean $regenerate_id optional. Whether or not to regenerate
	 *                                the session identifier on successful
	 *                                login. Defaults to
	 *                                {@link SiteSessionModule::REGENERATE_ID}.
	 *
	 * @return boolean true if the account was successfully logged in and false
	 *                       if the id does not match an account.
	 */
	public function loginById($id,
		$regenerate_id = SiteSessionModule::REGENERATE_ID)
	{
		if ($this->isLoggedIn())
			$this->logout();

		$account = $this->getNewAccountObject();

		if ($account->load($id)) {
			$this->activate();
			$this->account = $account;

			if ($regenerate_id) {
				$this->regenerateId();
			}

			$this->setAccountCookie();
			$this->runLoginCallbacks();

			// save last login date
			$now = new SwatDate();
			$now->toUTC();
			$this->account->updateLastLoginDate($now);
		}

		return $this->isLoggedIn();
	}

	// }}}
	// {{{ public function loginByAccount()

	/**
	 * Logs the current session into the specified {@link SiteAccount}
	 *
	 * @param SiteAccount $account The account to log into.
	 * @param boolean $regenerate_id optional. Whether or not to regenerate
	 *                                the session identifier on successful
	 *                                login. Defaults to
	 *                                {@link SiteSessionModule::REGENERATE_ID}.
	 *
	 * @return boolean true.
	 */
	public function loginByAccount(SiteAccount $account,
		$regenerate_id = SiteSessionModule::REGENERATE_ID)
	{
		if ($this->isLoggedIn())
			$this->logout();

		$this->activate();
		$this->account = $account;

		if ($regenerate_id) {
			$this->regenerateId();
		}

		$this->setAccountCookie();
		$this->runLoginCallbacks();

		// save last login date
		$now = new SwatDate();
		$now->toUTC();
		$this->account->updateLastLoginDate($now);

		return true;
	}

	// }}}
	// {{{ public function logout()

	/**
	 * Logs the current user out
	 */
	public function logout()
	{
		// Check isActive() instead of isLoggedIn() because we sometimes
		// call logout() to clear registered session objects even when
		// users are not logged in.
		if ($this->isActive()) {
			unset($this->account);
			unset($this->_authentication_token);
			parent::unsetRegisteredObjects();
		}

		$this->runLogoutCallbacks();
		$this->removeAccountCookie();
	}

	// }}}
	// {{{ public function isLoggedIn()

	/**
	 * Checks the current user's logged-in status
	 *
	 * @return boolean true if user is logged in, false if the user is not
	 *                  logged in.
	 */
	public function isLoggedIn()
	{
		if (!$this->isActive())
			return false;

		if (!isset($this->account))
			return false;

		if ($this->account === null)
			return false;

		if ($this->account->id === null)
			return false;

		return true;
	}

	// }}}
	// {{{ public function getAccountId()

	/**
	 * Retrieves the current account Id
	 *
	 * @return integer the current account ID, or null if not logged in.
	 */
	public function getAccountId()
	{
		if (!$this->isLoggedIn())
			return null;

		return $this->account->id;
	}

	// }}}
	// {{{ public function registerLoginCallback()

	/**
	 * Registers a callback function that is executed when a successful session
	 * login is performed
	 *
	 * @param callback $callback the callback to call when a successful login
	 *                            is performed.
	 * @param array $parameters optional. The paramaters to pass to the
	 *                           callback. Use an empty array for no parameters.
	 */
	public function registerLoginCallback($callback,
		array $parameters = array())
	{
		if (!is_callable($callback))
			throw new SiteException('Cannot register invalid callback.');

		$this->login_callbacks[] = array(
			'callback' => $callback,
			'parameters' => $parameters
		);
	}

	// }}}
	// {{{ public function registerLogoutCallback()

	/**
	 * Registers a callback function that is executed when a logout is
	 * performed
	 *
	 * @param callback $callback the callback to call when a logout is
	 *                            performed.
	 * @param array $parameters optional. The paramaters to pass to the
	 *                           callback. Use an empty array for no parameters.
	 */
	public function registerLogoutCallback($callback,
		array $parameters = array())
	{
		if (!is_callable($callback))
			throw new SiteException('Cannot register invalid callback.');

		$this->logout_callbacks[] = array(
			'callback' => $callback,
			'parameters' => $parameters
		);
	}

	// }}}
	// {{{ protected function startSession()

	/**
	 * Starts a session
	 */
	protected function startSession()
	{
		parent::startSession();
		if (isset($this->_authentication_token)) {
			SwatForm::setAuthenticationToken($this->_authentication_token);
		}
	}

	// }}}
	// {{{ protected function getNewAccountObject()

	protected function getNewAccountObject()
	{
		$class_name = SwatDBClassMap::get('SiteAccount');
		$account = new $class_name();
		$account->setDatabase($this->app->db);

		return $account;
	}

	// }}}
	// {{{ protected function runLoginCallbacks()

	protected function runLoginCallbacks()
	{
		foreach ($this->login_callbacks as $login_callback) {
			$callback = $login_callback['callback'];
			$parameters = $login_callback['parameters'];
			call_user_func_array($callback, $parameters);
		}
	}

	// }}}
	// {{{ protected function runLogoutCallbacks()

	protected function runLogoutCallbacks()
	{
		foreach ($this->logout_callbacks as $logout_callback) {
			$callback = $logout_callback['callback'];
			$parameters = $logout_callback['parameters'];
			call_user_func_array($callback, $parameters);
		}
	}

	// }}}
	// {{{ protected function setAccountCookie()

	protected function setAccountCookie()
	{
		if (!isset($this->app->cookie))
			return;

		$this->app->cookie->setCookie('account_id', $this->getAccountId());
	}

	// }}}
	// {{{ protected function removeAccountCookie()

	protected function removeAccountCookie()
	{
		if (!isset($this->app->cookie))
			return;

		$this->app->cookie->removeCookie('account_id');
	}

	// }}}

	// deprecated
	// {{{ public function registerDataObject()

	/**
	 * Registers an object class for a session variable
	 *
	 * @param string $name the name of the session variable.
	 * @param string $class the object class name.
	 * @param boolean $destroy_on_logout whether or not to destroy the object
	 *                                    on logout.
	 *
	 * @deprecated Use {@link SiteSessionModule::registerObject()} instead.
	 */
	public function registerDataObject($name, $class,
		$destroy_on_logout = true)
	{
		parent::registerObject($name, $class, $destroy_on_logout);
	}

	// }}}
	// {{{ public function unsetRegisteredDataObjects()

	/**
	 * Unsets objects registered in the session and marked as
	 * destroy-on-logout
	 *
	 * @deprecated Use {@link SiteSessionModule::usetRegisteredObjects()}
	 *             instead.
	 */
	public function unsetRegisteredDataObjects()
	{
		parent::unsetRegisteredObjects();
	}

	// }}}
}

?>
