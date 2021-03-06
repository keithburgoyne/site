<?php

require_once 'Swat/SwatDate.php';
require_once 'Site/pages/SitePage.php';
require_once 'Site/dataobjects/SiteAccount.php';
require_once 'Site/dataobjects/SiteApiCredential.php';
require_once 'Site/dataobjects/SiteSignOnToken.php';
require_once 'Site/exceptions/SiteSingleSignOnException.php';

/**
 * Page for logging into an account via a sign-on token
 *
 * @package   Site
 * @copyright 2013-2015 silverorange
 * @see       SiteSignOnToken
 */
abstract class SiteApiSignOnPage extends SitePage
{
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$ident         = $this->getIdent();
		$key           = $this->getVar('key');
		$token_string  = $this->getVar('token');

		$credentials = $this->getCredentials($key);
		$token = $this->getToken($ident, $token_string, $credentials);

		$account = $this->getAccount($token);
		$this->loginByAccount($account);
		$this->relocate($account);
	}

	// }}}
	// {{{ protected function getVar()

	protected function getVar($name)
	{
		return SiteApplication::initVar($name, null,
			SiteApplication::VAR_GET);
	}

	// }}}
	// {{{ protected function getIdent()

	protected function getIdent()
	{
		return $this->getVar('id');
	}

	// }}}
	// {{{ protected function getCredentials()

	protected function getCredentials($api_key)
	{
		$class_name = SwatDBClassMap::get('SiteApiCredential');
		$credentials = new $class_name();
		$credentials->setDatabase($this->app->db);

		if (!$credentials->loadByApiKey($api_key)) {
			throw new SiteSingleSignOnException(sprintf(
				'Unable to load credentials with the “%s” API key.', $api_key));
		}

		return $credentials;
	}

	// }}}
	// {{{ protected function getToken()

	protected function getToken($ident, $token_string,
		SiteApiCredential $credentials)
	{
		$class_name = SwatDBClassMap::get('SiteSignOnToken');
		$sign_on_token = new $class_name();
		$sign_on_token->setDatabase($this->app->db);

		if (!$sign_on_token->loadByIdentAndToken($ident,
			$token_string, $credentials)) {

			throw new SiteSingleSignOnException(
				sprintf(
					'A sign on token with the ident “%s” '.
					'and token “%s” does not exist.',
					$ident,
					$token_string
				)
			);
		} else {
			// Some browsers send a HEAD request followed by a GET request.
			// We don't want to delete the token until the GET request or else
			// the token will be prematurely deleted.
			if ($_SERVER['REQUEST_METHOD'] === 'GET') {
				$sign_on_token->delete();
			}
		}

		return $sign_on_token;
	}

	// }}}
	// {{{ protected function loginByAccount()

	protected function loginByAccount(SiteAccount $account)
	{
		$this->app->session->logout();
		$this->app->session->loginByAccount($account);
	}

	// }}}
	// {{{ abtract protected function getAccount()

	abstract protected function getAccount(SiteSignOnToken $token);

	// }}}
	// {{{ abstract protected function relocate()

	abstract protected function relocate(SiteAccount $account);

	// }}}
}

?>
