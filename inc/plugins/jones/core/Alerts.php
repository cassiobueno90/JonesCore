<?php

class JB_Alerts
{
	/**
	 * @var bool
	 */
	private static $installed = null;
	/**
	 * @var bool
	 */
	private static $activated = null;
	/**
	 * @var array
	 */
	private static $types = null;

	public static function init()
	{
		global $plugins;

		if(!static::isInstalled())
		{
			// Not installed so add our nice hook to add our alerts when installing
			$plugins->add_hook("myalerts_install", array("JB_Alerts", "onInstall"));
		}

		// Nothing to do if MyAlerts is deactivated
		if(!static::isActivated())
			return;

		// Need to do this after MyAlerts created the managers (on global_start)
		$plugins->add_hook("global_start", array("JB_Alerts", "registerFormatters"), 11);
	}

	public static function registerFormatters()
	{
		global $mybb, $lang;

		// Formatters aren't registered for guests. However we can't add that check before the add_hook call as the session isn't loaded then
		if($mybb->user['uid'] == 0)
			return;

		// Loop through all types and register the correct formatter
		foreach(static::getTypes() as $codename => $types)
		{
			// Make sure that the language is loaded!
			$lang->load($codename);
			foreach($types as $type)
			{
				// Do we have a custom formatter for this type?
				if(class_exists(JB_Packages::i()->getPrefixForCodename($codename)."_{$codename}_Alerts_{$type}Formatter"))
				{
					$formatter = JB_Packages::i()->getPrefixForCodename($codename)."_{$codename}_Alerts_{$type}Formatter";
					$formatter = new $formatter($mybb, $lang, JB_Packages::i()->getPrefixForCodename($codename)."_{$codename}_{$type}");
				}
				// Otherweise use our base formatter
				else
				{
					$formatter = new JB_Alerts_BaseFormatter($mybb, $lang, JB_Packages::i()->getPrefixForCodename($codename)."_{$codename}_{$type}");
				}
				MybbStuff_MyAlerts_AlertFormatterManager::getInstance()->registerFormatter($formatter);
			}
		}
	}

	/**
	 * @param string    $codename
	 * @param string    $alert
	 * @param array|int $to
	 * @param array     $extra
	 * @param array|int $from
	 */
	public static function trigger($codename, $alert, $to, array $extra=array(), $from=false)
	{
		// Nothing to do if MyAlerts is deactivated
		if(!static::isActivated())
			return;

		$name = JB_Packages::i()->getPrefixForCodename($codename)."_{$codename}_{$alert}";
		$type = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($name);
		if($type == null)
			return;

		if(!is_array($to))
			$to = array($to);
		$to = array_unique($to);

		foreach($to as $id)
		{
			// Skip guests
			if($id == 0)
				continue;

			$alert = MybbStuff_MyAlerts_Entity_Alert::make($id, $type, 0, $extra);
			if($from !== false)
			{
				if(is_array($from))
					$alert->setFromUser($from);
				else
					$alert->setFromUser(get_user($from));
			}
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		}
	}

	/**
	 * @param string    $codename
	 * @param string    $alert
	 * @param array|int $to
	 * @param array     $extra
	 * @param array|int $from
	 */
	public static function triggerGroup($codename, $alert, $to, array $extra=array(), $from=false)
	{
		// Nothing to do if MyAlerts is deactivated
		if(!static::isActivated())
			return;

		if(!is_array($to))
			$to = array($to);
		$to = array_unique($to);

		$users = array();
		foreach($to as $gid)
		{
			$gusers = JB_Helpers::getUsersInGroup($gid);
			foreach($gusers as $user)
				$users[] = $user['uid'];
		}

		// Now trigger the alert for all users. The trigger function will also handle duplicated users
		static::trigger($codename, $alert, $users, $extra, $from);
	}

	/**
	 * @return array
	 */
	public static function getTypes()
	{
		if(static::$types !== null)
			return static::$types;

		global $cache;

		$jb_plugins = $cache->read("jb_plugins");
		$active = $cache->read("plugins");
		$active = $active['active'];

		static::$types = array();

		foreach(array_keys($jb_plugins) as $codename)
		{
			// Only activated plugins!
			if(!in_array($codename, $active))
				continue;

			if(!file_exists(JB_Packages::i()->getPath($codename)."install/alerts.php"))
				continue;

			$alerts = array();
			require JB_Packages::i()->getPath($codename)."install/alerts.php";

			if(!empty($alerts))
			{
				static::$types[$codename] = $alerts;
			}
		}

		return static::$types;
	}

	public static function onInstall()
	{
		global $cache;

		// Flash our cache - myalerts is installed now
		static::$installed = true;

		$jb_plugins = $cache->read("jb_plugins");
		$active = $cache->read("plugins");
		$active = $active['active'];

		foreach(array_keys($jb_plugins) as $codename)
		{
			// Always install 
			if(JB_Installer_Alerts::isNeeded($codename))
				JB_Installer_Alerts::install($codename);

			// If the plugin is also activated we'll also activate our alerts
			if(in_array($codename, $active))
			{
				if(JB_Activate_Alerts::isNeeded($codename))
					JB_Activate_Alerts::activate($codename);
			}
		}
	}

	/**
	 * @return bool
	 */
	public static function isInstalled()
	{
		if(static::$installed !== null)
			return static::$installed;

		// File not uploaded? -> Not installed
		if(!file_exists(JB_PLUGINS."myalerts.php"))
		{
			static::$installed = false;
			return false;
		}

		// Don't add myalerts hooks here! If it's installed they were already but if it isn't we'd create a lot of issues
		global $plugins;
		$hooks = $plugins->hooks;
		require_once JB_PLUGINS."myalerts.php";

		$func = "myalerts_is_installed";

		// Trying to fool us with a wrong file?
		if(!function_exists($func))
		{
			static::$installed = false;
			$plugins->hooks = $hooks;
			return false;
		}

		static::$installed = $func();
		if(!static::$installed)
			$plugins->hooks = $hooks;
		return static::$installed;
	}

	/**
	 * @return bool
	 */
	public static function isActivated()
	{
		if(static::$activated !== null)
			return static::$activated;

		// Not installed? -> Not activated!
		if(!static::isInstalled())
		{
			static::$activated = false;
			return false;
		}

		if(!function_exists("myalerts_is_activated"))
		{
			static::$activated = false;
			return false;
		}

		static::$activated = myalerts_is_activated();

		return static::$activated;
	}
}
