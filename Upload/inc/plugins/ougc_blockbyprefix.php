<?php

/***************************************************************************
 *
 *   OUGC Block By Thread Prefix plugin (/inc/plugins/ougc_blockbyprefix.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2013 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *   Block threads by thread prefix.
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

// Tell MyBB when to run the hook
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

// Plugin API
function ougc_blockbyprefix_info()
{
	global $lang;
	$lang->load('ougc_blockbyprefix');

	return array(
		'name'			=> 'OUGC Block By Thread Prefix',
		'description'	=> $lang->ougc_blockbyprefix_d,
		'website'		=> 'http://mods.mybb.com/view/ougc-block-by-thread-prefix',
		'author'		=> 'Omar Gonzalez',
		'authorsite'	=> 'http://community.mybb.com/user-25096.html',
		'version'		=> '1.0',
		'guid' 			=> '6507442d095476f59e2843954e7f93b3',
		'compatibility' => '16*'
	);
}

// Install the plugin.
function ougc_blockbyprefix_install()
{
	global $db;

	if(!$db->field_exists('vgroups', 'threadprefixes'))
	{
		$db->add_column('threadprefixes', 'vgroups', 'text NOT NULL');
	}
}

// Check if plugin is installed.
function ougc_blockbyprefix_is_installed()
{
	global $db;

	return (bool)$db->field_exists('vgroups', 'threadprefixes');
}

// Uninstall the plugin.
function ougc_blockbyprefix_uninstall()
{
	global $db;

	if($db->field_exists('vgroups', 'threadprefixes'))
	{
		$db->drop_column('threadprefixes', 'vgroups');
	}
}

// Add our box code into the page
function ougc_blockbyprefix_container()
{
	global $run_module, $form_container, $lang;

	if($run_module == 'config' && !empty($form_container->_title) && !empty($lang->prefix_options) && $form_container->_title == $lang->prefix_options)
	{
		global $form, $mybb;
		isset($lang->ougc_blockbyprefix) or $lang->load('ougc_blockbyprefix');

		if($mybb->input['action'] != 'add_prefix')
		{
			global $db;

			$query = $db->simple_select('threadprefixes', 'vgroups', 'pid=\''.(int)$mybb->input['pid'].'\'');
			$vgroups = $db->fetch_field($query, 'vgroups');
			if($vgroups)
			{
				$mybb->input['vgroup_1_vgroups'] = explode(',', $vgroups);
			}
		}

		isset($mybb->input['vgroup_1_vgroups']) or ($mybb->input['vgroup_1_vgroups'] = array());
		$checked = array('all' => 'checked="checked"', 'select' => '');
		if(!empty($mybb->input['vgroup_1_vgroups']))
		{
			$checked = array('all' => '', 'select' => ' checked="checked"');
		}

		$actions = '<dl style="margin-top: 0; margin-bottom: 0; width: 100%;">
		<dt><label style="display: block;"><input type="radio" name="vgroup_type" value="1" class="vgroups_check" onclick="checkAction(\'vgroup\');" style="vertical-align: middle;"'.$checked['all'].' /> <strong>'.$lang->ougc_blockbyprefix_all.'</strong></label></dt>
			<dt><label style="display: block;"><input type="radio" name="vgroup_type" value="2" class="vgroups_check" onclick="checkAction(\'vgroup\');" style="vertical-align: middle;"'.$checked['select'].' /> <strong>'.$lang->ougc_blockbyprefix_selected.'</strong></label></dt>
			<dd style="margin-top: 4px;" id="vgroup_2" class="vgroups">
				<table cellpadding="4">
					<tr>
						<td valign="top"><small>'.$lang->ougc_blockbyprefix_groups.'</small></td>
						<td>'.$form->generate_group_select('vgroup_1_vgroups[]', $mybb->input['vgroup_1_vgroups'], array('multiple' => true, 'size' => 5)).'</td>
					</tr>
				</table>
			</dd>
		</dl>
		<script type="text/javascript">
		checkAction(\'vgroup\');
		</script>';
		$form_container->output_row($lang->ougc_blockbyprefix_container.' <em>*</em>', '', $actions);
	}
}

// Save a prefix groups
function ougc_blockbyprefix_commit()
{
	global $mybb;

	if($mybb->request_method != 'post')
	{
		return;
	}

	global $db;

	$cleangroups = '';
	if((int)$mybb->input['vgroup_type'] != 1 && $groups = array_filter(array_unique(array_map('intval', (array)$mybb->input['vgroup_1_vgroups']))))
	{
		$cleangroups = implode(',', $groups);
	}

	$db->update_query('threadprefixes', array('vgroups' => $db->escape_string($cleangroups)), 'pid=\''.(int)($mybb->input['action'] == 'add_prefix' ? $GLOBALS['pid'] : $mybb->input['pid']).'\'');
}

// Actual magic
function ougc_blockbyprefix_hack()
{
	global $thread, $ismod, $mybb;

	if(!isset($thread))
	{
		$thread = get_thread((int)$mybb->input['tid']);
	}

	if(!isset($ismod))
	{
		$ismod = is_moderator($thread['fid']);
	}

	if($thread['uid'] == $mybb->user['uid'] || $ismod || !$thread['prefix'])
	{
		return;
	}

	$prefix = build_prefixes($thread['prefix']);

	if(!empty($prefix['vgroups']) && !ougc_get_unviewable_check_groups($prefix['vgroups']))
	{
		if(defined('IN_ARCHIVE'))
		{
			archive_error_no_permission();
		}
		error_no_permission();
	}
}

// Check for user group permissions
function ougc_get_unviewable_check_groups($groups)
{
	if(empty($groups))
	{
		return true;
	}

	global $mybb;

	$usergroups = explode(',', $mybb->user['additionalgroups']);
	$usergroups[] = $mybb->user['usergroup'];

	return (bool)array_intersect(array_map('intval', explode(',', $groups)), array_map('intval', $usergroups));
}

// Return a comma separate list of prefixes the current user is not allowed to see
function ougc_get_unviewable_prefixes($string=true)
{
	static $pids;

	if(!isset($pids))
	{
		$prefixes = build_prefixes();

		$pids = array();
		if($prefixes)
		{
			foreach($prefixes as $prefix)
			{
				if(!empty($prefix['vgroups']) && !ougc_get_unviewable_check_groups($prefix['vgroups']))
				{
					$pids[(int)$prefix['pid']] = 0;
				}
			}
		}
		$pids = array_keys($pids);
	}

	if($string === true)
	{
		$pids = implode(',', $pids);
	}

	return $pids;
}