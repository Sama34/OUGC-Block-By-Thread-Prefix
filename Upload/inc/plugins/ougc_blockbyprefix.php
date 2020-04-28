<?php

/***************************************************************************
 *
 *	OUGC Block By Thread Prefix plugin (/inc/plugins/ougc_blockbyprefix.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2013-2020 Omar Gonzalez
 *
 *	Website: https://ougc.network
 *
 *	Blocks groups from viewing threads with specific thread prefixes.
 *
 ***************************************************************************

****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('Direct initialization of this file is not allowed.');

// Run/Add Hooks
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_config_thread_prefixes_begin', create_function('', '
		global $plugins;
		$plugins->add_hook(\'admin_formcontainer_end\', \'ougc_blockbyprefix_container\');
	'));
	
	$plugins->add_hook('admin_config_thread_prefixes_add_prefix_commit', 'ougc_blockbyprefix_commit');
	$plugins->add_hook('admin_config_thread_prefixes_edit_prefix_commit', 'ougc_blockbyprefix_commit');
}
else
{
	$plugins->add_hook('showthread_start', 'ougc_blockbyprefix_hack');
	$plugins->add_hook('printthread_start', 'ougc_blockbyprefix_hack');
	$plugins->add_hook('archive_thread_start', 'ougc_blockbyprefix_hack');
	$plugins->add_hook('newreply_do_newreply_start', 'ougc_blockbyprefix_hack');
	$plugins->add_hook('newreply_start', 'ougc_blockbyprefix_hack');
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Plugin API
function ougc_blockbyprefix_info()
{
	global $lang;
	ougc_blockbyprefix_lang_load();

	return array(
		'name'			=> 'OUGC Block By Thread Prefix',
		'description'	=> $lang->ougc_blockbyprefix_desc,
		'website'		=> 'https://ougc.network',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'https://ougc.network',
		'version'		=> '1.8.22',
		'versioncode'	=> 1822,
		'compatibility'	=> '18*',
		'pl'			=> array(
			'version'	=> 13,
			'url'		=> 'https://community.mybb.com/mods.php?action=view&pid=573'
		)
	);
}

// _activate() routine
function ougc_blockbyprefix_activate()
{
	global $cache;
	ougc_blockbyprefix_pl_check();

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_blockbyprefix_info();

	if(!isset($plugins['blockbyprefix']))
	{
		$plugins['blockbyprefix'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/
	if($plugins['blockbyprefix'] <= 1100)
	{
		$db->modify_column('threadprefixes', 'vgroups', 'varchar(200) NOT NULL DEFAULT \'\'');
	}
	/*~*~* RUN UPDATES END *~*~*/

	$plugins['blockbyprefix'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _install() routine
function ougc_blockbyprefix_install()
{
	global $db;
	ougc_blockbyprefix_pl_check();

	// Add DB entries
	if(!$db->field_exists('vgroups', 'threadprefixes'))
	{
		$db->add_column('threadprefixes', 'vgroups', 'varchar(200) NOT NULL DEFAULT \'\'');
	}
}

// _is_installed() routine
function ougc_blockbyprefix_is_installed()
{
	global $db;

	return (bool)$db->field_exists('vgroups', 'threadprefixes');
}

// _uninstall() routine
function ougc_blockbyprefix_uninstall()
{
	global $db, $PL, $cache;
	ougc_blockbyprefix_pl_check();

	// Drop DB entries
	if($db->field_exists('vgroups', 'threadprefixes'))
	{
		$db->drop_column('threadprefixes', 'vgroups');
	}

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['blockbyprefix']))
	{
		unset($plugins['blockbyprefix']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$PL->cache_delete('ougc_plugins');
	}
}

// Loads language strings
function ougc_blockbyprefix_lang_load()
{
	global $lang;

	isset($lang->ougc_blockbyprefix) or $lang->load('ougc_blockbyprefix');
}

// PluginLibrary dependency check & load
function ougc_blockbyprefix_pl_check()
{
	global $lang;
	ougc_blockbyprefix_lang_load();
	$info = ougc_blockbyprefix_info();

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message($lang->sprintf($lang->ougc_blockbyprefix_pl_required, $info['pl']['url'], $info['pl']['version']), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}

	global $PL;

	$PL or require_once PLUGINLIBRARY;

	if($PL->version < $info['pl']['version'])
	{
		flash_message($lang->sprintf($lang->ougc_blockbyprefix_pl_old, $info['pl']['url'], $info['pl']['version'], $PL->version), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}
}

// Add code into ACP Thread Prefixes page
function ougc_blockbyprefix_container()
{
	global $run_module, $form_container, $lang;

	if($run_module == 'config' && !empty($form_container->_title) && !empty($lang->prefix_options) && $form_container->_title == $lang->prefix_options)
	{
		global $form, $mybb;
		ougc_blockbyprefix_lang_load();

		if($mybb->input['action'] != 'add_prefix')
		{
			global $db;

			$query = $db->simple_select('threadprefixes', 'vgroups', 'pid=\''.(int)$mybb->input['pid'].'\'');
			$vgroups = $db->fetch_field($query, 'vgroups');

			$mybb->input['vgroup_1_vgroups'] = ($vgroups ? explode(',', $vgroups) : array());
		}

		$checked = (!empty($mybb->input['vgroup_1_vgroups']) ? array('all' => '', 'select' => ' checked="checked"') : array('all' => 'checked="checked"', 'select' => ''));

		$form_container->output_row($lang->ougc_blockbyprefix_container.' <em>*</em>', '', '<dl style="margin-top: 0; margin-bottom: 0; width: 100%;">
		<dt><label style="display: block;"><input type="radio" name="vgroup_type" value="1" class="vgroups_check" onclick="checkAction(\'vgroup\');" style="vertical-align: middle;"'.$checked['all'].' /> <strong>'.$lang->all_groups.'</strong></label></dt>
			<dt><label style="display: block;"><input type="radio" name="vgroup_type" value="2" class="vgroups_check" onclick="checkAction(\'vgroup\');" style="vertical-align: middle;"'.$checked['select'].' /> <strong>'.$lang->select_groups.'</strong></label></dt>
			<dd style="margin-top: 4px;" id="vgroup_2" class="vgroups">
				<table cellpadding="4">
					<tr>
						<td valign="top"><small>'.$lang->groups_colon.'</small></td>
						<td>'.$form->generate_group_select('vgroup_1_vgroups[]', (array)$mybb->input['vgroup_1_vgroups'], array('multiple' => true, 'size' => 5)).'</td>
					</tr>
				</table>
			</dd>
		</dl>
		<script type="text/javascript">
		checkAction(\'vgroup\');
		</script>');
	}
}

// Commit prefix changes
function ougc_blockbyprefix_commit()
{
	global $mybb;

	if($mybb->request_method != 'post')
	{
		return;
	}

	global $db, $pid;

	$cleangroups = '';
	$groups = array_filter(array_unique(array_map('intval', (array)$mybb->input['vgroup_1_vgroups'])));
	if((int)$mybb->input['vgroup_type'] == 2 && $groups)
	{
		$cleangroups = implode(',', $groups);
	}

	$db->update_query('threadprefixes', array('vgroups' => $db->escape_string($cleangroups)), 'pid=\''.(int)($mybb->input['action'] == 'add_prefix' ? $pid : $mybb->input['pid']).'\'');
}

// Dark Magic
function ougc_blockbyprefix_hack()
{
	global $thread, $ismod, $mybb;

	isset($thread) or $thread = get_thread((int)$mybb->input['tid']);
	isset($ismod) or $ismod = is_moderator($thread['fid']);

	if($thread['uid'] == $mybb->user['uid'] || $ismod || !$thread['prefix'])
	{
		return;
	}

	if(!($prefix = build_prefixes($thread['prefix'])))
	{
		return;
	}

	global $PL;
	$PL or require_once PLUGINLIBRARY;

	if(!$PL->is_member($prefix['vgroups']))
	{
		if(defined('IN_ARCHIVE'))
		{
			archive_error_no_permission();
		}
		error_no_permission();
	}
}

// Hide threads from portal, because content is visible there
function ougc_blockbyprefix_portal()
{
	$unviewable_prefixes = ougc_get_unviewable_prefixes();
	$unviewable_prefixes = implode('\\\',\\\'', $unviewable_prefixes);

	control_object($GLOBALS['db'], '
		function query($string, $hide_errors=0, $write_query=0)
		{
			if(!$write_query && strpos($string, \'ORDER BY t.dateline DESC\'))
			{
				$string = strtr($string, array(
					\'t.closed\' => \'t.prefix NOT IN (\\\''.$unviewable_prefixes.'\\\') AND t.closed\'
				));
			}
			if(!$write_query && strpos($string, \'OUNT(t.tid) AS thread\'))
			{
				$string = strtr($string, array(
					\'t.visible\' => \'t.prefix NOT IN (\\\''.$unviewable_prefixes.'\\\') AND t.visible\'
				));
			}
			return parent::query($string, $hide_errors, $write_query);
		}
	');
}

/**
 * Get a list of the unviewable prefixes for the current user
 *
 * @return string Comma separated values list of prefix IDs which the user cannot view
**/
function ougc_get_unviewable_prefixes()
{
	static $cachedpids;

	if(!isset($cachedpids))
	{
		$prefixes = build_prefixes();

		$cachedpids = array();
		if($prefixes)
		{
			global $PL;
			$PL or require_once PLUGINLIBRARY;

			foreach($prefixes as $prefix)
			{
				if(!$PL->is_member($prefix['vgroups']))
				{
					$cachedpids[(int)$prefix['pid']] = 0;
				}
			}
		}
		$cachedpids = array_keys($cachedpids);
	}

	return $cachedpids;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
	{
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
		{
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v)
			{
				if($p = strrpos($k, "\0"))
				{
					$k = substr($k, $p+1);
				}
				$vars[$k] = $v;
			}
			if(!empty($vars))
			{
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			}
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
			{
				$obj->___setvars($vars);
			}
		}
		// else not a valid object or PHP serialize has changed
	}
}