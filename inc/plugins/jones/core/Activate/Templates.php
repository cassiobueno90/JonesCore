<?php

class JB_Activate_Templates extends JB_Activate_Base
{
	/**
	 * {@inheritdoc}
	 */
	static function activate($codename)
	{
		require JB_Packages::i()->getPath($codename)."install/template_edits.php";

		// Template Edits
		if(!empty($edits))
		{
			require_once MYBB_ROOT."inc/adminfunctions_templates.php";

			foreach($edits as $template => $edit)
			{
				foreach($edit as $find => $replace)
				{
					find_replace_templatesets($template, "#".preg_quote($find)."#i", $replace);
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	static function deactivate($codename)
	{
		require JB_Packages::i()->getPath($codename)."install/template_edits.php";

		// Template Edits
		if(!empty($edits))
		{
			require_once MYBB_ROOT."inc/adminfunctions_templates.php";

			foreach($edits as $template => $edit)
			{
				foreach($edit as $find => $replace)
				{
					find_replace_templatesets($template, "#".preg_quote($replace)."#i", $find, 0);
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	static function isNeeded($codename)
	{
		return file_exists(JB_Packages::i()->getPath($codename)."install/template_edits.php");
	}
}
