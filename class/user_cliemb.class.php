<?php
/* Copyright (C) 2023 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    cliemb/class/actions_cliemb.class.php
 * \ingroup cliemb
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsCliemb
 */
class UserCliEmb extends User
{

	/**
	 *  Send a new password (or instructions to reset it) by email
	 *
	 *  @param	User	$user           Object user that send the email (not the user we send to) @todo object $user is not used !
	 *  @param	string	$password       New password
	 *	@param	int		$changelater	0=Send clear passwod into email, 1=Change password only after clicking on confirm email. @todo Add method 2 = Send link to reset password
	 *  @return int 		            < 0 si erreur, > 0 si ok
	 */
	public function send_password($user, $password = '', $changelater = 0)
	{
		// phpcs:enable
		global $conf, $langs, $mysoc;
		global $dolibarr_main_url_root;

		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

		$msgishtml = 0;

		// Define $msg
		$mesg = '';

		$outputlangs = new Translate("", $conf);

		if (isset($this->conf->MAIN_LANG_DEFAULT)
			&& $this->conf->MAIN_LANG_DEFAULT != 'auto') {	// If user has defined its own language (rare because in most cases, auto is used)
			$outputlangs->getDefaultLang($this->conf->MAIN_LANG_DEFAULT);
		}

		if ($this->conf->MAIN_LANG_DEFAULT) {
			$outputlangs->setDefaultLang($this->conf->MAIN_LANG_DEFAULT);
		} else {	// If user has not defined its own language, we used current language
			$outputlangs = $langs;
		}

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "errors", "users", "other"));

		$appli = constant('DOL_APPLICATION_TITLE');
		if (!empty($conf->global->MAIN_APPLICATION_TITLE)) {
			$appli = $conf->global->MAIN_APPLICATION_TITLE;
		}

		$subject = '['.$mysoc->name.'] '.$outputlangs->transnoentitiesnoconv("SubjectNewPassword", $appli);

		// Define $urlwithroot
		$urlwithouturlroot = preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
		$urlwithroot = $urlwithouturlroot.DOL_URL_ROOT; // This is to use external domain name found into config file

		if (!$changelater) {
			$url = $urlwithroot.'/';
			if (!empty($conf->global->URL_REDIRECTION_AFTER_CHANGEPASSWORD)) {
				$url = $conf->global->URL_REDIRECTION_AFTER_CHANGEPASSWORD;
			}

			dol_syslog(get_class($this)."::send_password changelater is off, url=".$url);

			$mesg .= $outputlangs->transnoentitiesnoconv("RequestToResetPasswordReceived").".\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("NewKeyIs")." :\n\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("Login")." = ".$this->login."\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("Password")." = ".$password."\n\n";
			$mesg .= "\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("ClickHereToGoTo", "espace client").': '.$url."\n\n";
			$mesg .= "--\n";
			$mesg .= $user->getFullName($outputlangs); // Username that send the email (not the user for who we want to reset password)
		} else {
			//print $password.'-'.$this->id.'-'.$conf->file->instance_unique_id;
			$url = $urlwithroot.'/user/passwordforgotten.php?action=validatenewpassword';
			$url .= '&username='.urlencode($this->login)."&passworduidhash=".urlencode(dol_hash($password.'-'.$this->id.'-'.$conf->file->instance_unique_id));
			if (isModEnabled('multicompany')) {
				$url .= '&entity='.(!empty($this->entity) ? $this->entity : 1);
			}

			dol_syslog(get_class($this)."::send_password changelater is on, url=".$url);

			$msgishtml = 1;

			$mesg .= $outputlangs->transnoentitiesnoconv("RequestToResetPasswordReceived")."<br>\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("NewKeyWillBe")." :<br>\n<br>\n";
			$mesg .= '<strong>'.$outputlangs->transnoentitiesnoconv("Login")."</strong> = ".$this->login."<br>\n";
			$mesg .= '<strong>'.$outputlangs->transnoentitiesnoconv("Password")."</strong> = ".$password."<br>\n<br>\n";
			$mesg .= "<br>\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("YouMustClickToChange")." :<br>\n";
			$mesg .= '<a href="'.$url.'" rel="noopener">'.$outputlangs->transnoentitiesnoconv("ConfirmPasswordChange").'</a>'."<br>\n<br>\n";
			$mesg .= $outputlangs->transnoentitiesnoconv("ForgetIfNothing")."<br>\n<br>\n";
		}

		$trackid = 'use'.$this->id;
		$sendcontext = 'password';

		$mailfile = new CMailFile(
			$subject,
			$this->email,
			$conf->global->MAIN_MAIL_EMAIL_FROM,
			$mesg,
			array(),
			array(),
			array(),
			'',
			'',
			0,
			$msgishtml,
			'',
			'',
			$trackid,
			'',
			$sendcontext
		);

		if ($mailfile->sendfile()) {
			return 1;
		} else {
			$langs->trans("errors");
			$this->error = $langs->trans("ErrorFailedToSendPassword").' '.$mailfile->error;
			return -1;
		}
	}
}
