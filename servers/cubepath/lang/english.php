<?php
/**
 * CubePath Cloud WHMCS Module - English Language File
 *
 * All user-facing strings for the CubePath server module.
 */

/**
 * Backups
 */
$_LANG['backups']['index']['panel_title'] = 'Backup Manager';
$_LANG['backups']['index']['name'] = 'Name';
$_LANG['backups']['index']['status'] = 'Status';
$_LANG['backups']['index']['size'] = 'Size';
$_LANG['backups']['index']['notes'] = 'Notes';
$_LANG['backups']['index']['created'] = 'Created';
$_LANG['backups']['index']['actions'] = 'Actions';
$_LANG['backups']['index']['confirm'] = 'Are you sure you want to restore this backup?';
$_LANG['backups']['index']['restore'] = 'Restore';
$_LANG['backups']['index']['delete'] = 'Delete';
$_LANG['backups']['index']['confirm_delete'] = 'Are you sure you want to delete this backup?';
$_LANG['backups']['index']['vm_not_found'] = 'VPS not found';
$_LANG['backups']['index']['not_found'] = 'No backups found';
$_LANG['backups']['index']['create_new'] = 'Create Backup';
$_LANG['backups']['restore']['success'] = 'Backup restoration has started';
$_LANG['backups']['delete']['success'] = 'Backup has been deleted';
$_LANG['backups']['create']['success'] = 'Backup has been created';
$_LANG['backups']['other']['not_available'] = 'Backups are not available for your plan';
$_LANG['backups']['settings']['panel_title'] = 'Backup Settings';
$_LANG['backups']['settings']['enabled'] = 'Auto Backups';
$_LANG['backups']['settings']['schedule_hour'] = 'Schedule Hour (UTC)';
$_LANG['backups']['settings']['retention_days'] = 'Retention Days';
$_LANG['backups']['settings']['max_backups'] = 'Maximum Backups';
$_LANG['backups']['settings']['save'] = 'Save Settings';
$_LANG['backups']['settings']['success'] = 'Backup settings updated';

/**
 * DNS
 */
$_LANG['dns']['index']['panel_title'] = 'DNS Manager';
$_LANG['dns']['index']['add'] = '+';
$_LANG['dns']['index']['domain'] = 'Domain';
$_LANG['dns']['index']['created'] = 'Date Created';
$_LANG['dns']['index']['actions'] = 'Actions';
$_LANG['dns']['index']['manage'] = 'Manage';
$_LANG['dns']['index']['confirm'] = 'Are you sure you want to delete this zone?';
$_LANG['dns']['index']['delete'] = 'Delete';
$_LANG['dns']['index']['not_found'] = 'No DNS zones found';
$_LANG['dns']['create']['panel_title'] = 'DNS Manager - Add Domain';
$_LANG['dns']['create']['input_domain'] = 'Domain Name';
$_LANG['dns']['create']['ip'] = 'IP Address';
$_LANG['dns']['create']['create'] = 'Add DNS Zone';
$_LANG['dns']['create']['vm_not_found'] = 'Please create a VPS first';
$_LANG['dns']['create']['connection_error'] = 'Connection error';
$_LANG['dns']['create']['add_domain'] = 'Domain has been successfully added';
$_LANG['dns']['create']['success'] = 'DNS zone has been created';
$_LANG['dns']['delete']['delete_success'] = 'Domain has been successfully removed';
$_LANG['dns']['delete']['success'] = 'DNS zone has been deleted';
$_LANG['dns']['delete']['delete_error'] = 'Error deleting domain, please try again';
$_LANG['dns']['delete']['error'] = 'Error deleting zone, please try again';
$_LANG['dns']['manage']['panel_title'] = 'DNS Manager';
$_LANG['dns']['manage']['delete'] = 'Delete';
$_LANG['dns']['manage']['type'] = 'Type';
$_LANG['dns']['manage']['name'] = 'Name';
$_LANG['dns']['manage']['data'] = 'Data';
$_LANG['dns']['manage']['priority'] = 'Priority';
$_LANG['dns']['manage']['ttl'] = 'TTL (seconds)';
$_LANG['dns']['manage']['update'] = 'Update';
$_LANG['dns']['manage']['add_new'] = 'Add New Record';
$_LANG['dns']['manage']['add'] = 'Add';
$_LANG['dns']['manage']['delete_success'] = 'Record %var% has been deleted';
$_LANG['dns']['manage']['update_success'] = 'Record %var% has been updated';
$_LANG['dns']['manage']['add_success'] = 'Record has been successfully added';
$_LANG['dns']['manage']['error'] = 'You cannot manage this domain';
$_LANG['dns']['manage']['not_found'] = 'No records found';

/**
 * Main / Control Panel
 */
$_LANG['main']['index']['control_panel'] = 'Control Panel';
$_LANG['main']['index']['boot'] = 'Start';
$_LANG['main']['index']['reboot'] = 'Restart';
$_LANG['main']['index']['stop'] = 'Stop';
$_LANG['main']['index']['reinstall'] = 'Reinstall';
$_LANG['main']['index']['console'] = 'Console';
$_LANG['main']['index']['vps_name'] = 'VPS Name';
$_LANG['main']['index']['cpus'] = 'CPU Count';
$_LANG['main']['index']['memory'] = 'Memory';
$_LANG['main']['index']['confirm_reinstall'] = 'Are you sure you want to reinstall your server? Any data on your server will be permanently lost.';
$_LANG['main']['index']['hdd'] = 'Storage';
$_LANG['main']['index']['template'] = 'Operating System';
$_LANG['main']['index']['ipaddress'] = 'IP Address';
$_LANG['main']['index']['root_password'] = 'Root Password';
$_LANG['main']['index']['root_pass_show'] = 'Show';
$_LANG['main']['index']['root_pass_hide'] = 'Hide';
$_LANG['main']['index']['stats'] = 'Stats';
$_LANG['main']['index']['bandwidth'] = 'Bandwidth';
$_LANG['main']['index']['details'] = 'Details';
$_LANG['main']['index']['status'] = 'Status';
$_LANG['main']['index']['vm_not_found'] = 'VPS not found';
$_LANG['main']['index']['vm_status_is'] = 'VPS status is: ';
$_LANG['main']['index']['change_os'] = 'Change OS';
$_LANG['main']['index']['change_iso'] = 'Change ISO';
$_LANG['main']['index']['detach_iso'] = 'Detach ISO';
$_LANG['main']['index']['power_status'] = 'Power Status';
$_LANG['main']['index']['server_status'] = 'Server State';
$_LANG['main']['index']['main_ip'] = 'Main IP';
$_LANG['main']['index']['networks'] = 'Networks';
$_LANG['main']['index']['netmask'] = 'Netmask';
$_LANG['main']['index']['address'] = 'Address';
$_LANG['main']['index']['gateway'] = 'Gateway';
$_LANG['main']['index']['vm_status'] = 'VPS Status';
$_LANG['main']['index']['reverse_dns'] = 'Reverse DNS';
$_LANG['main']['index']['rev_dns_success'] = 'Reverse DNS updated successfully';
$_LANG['main']['index']['detach_iso_success'] = 'ISO file was successfully detached';
$_LANG['main']['index']['label_change'] = 'Change';
$_LANG['main']['index']['save'] = 'Save';
$_LANG['main']['index']['label_change_cancel'] = 'Cancel';
$_LANG['main']['index']['label_success'] = 'Label changed successfully';
$_LANG['main']['index']['label_error'] = 'Please enter a different label';
$_LANG['main']['index']['plan'] = 'Plan';
$_LANG['main']['index']['location'] = 'Location';
$_LANG['main']['index']['connection_error'] = 'Connection error';
$_LANG['main']['index']['power_start_success'] = 'Server start command sent';
$_LANG['main']['index']['power_stop_success'] = 'Server stop command sent';
$_LANG['main']['index']['power_reboot_success'] = 'Server reboot command sent';
$_LANG['main']['index']['password_success'] = 'Password changed successfully';
$_LANG['main']['index']['password_error'] = 'Password must be at least 8 characters';

$_LANG['main']['create']['hostname_empty'] = 'Please enter a hostname';
$_LANG['main']['create']['hostname_not_valid'] = 'Hostname is not valid';
$_LANG['main']['create']['panel_title'] = 'Setup Your Server';
$_LANG['main']['create']['server_label'] = 'Server Label:';
$_LANG['main']['create']['server_label_placeholder'] = 'Enter your server label';
$_LANG['main']['create']['server_hostname'] = 'Hostname:';
$_LANG['main']['create']['server_hostname_placeholder'] = 'Enter your server hostname';
$_LANG['main']['create']['create'] = 'Create';
$_LANG['main']['create']['ssh_cert'] = 'Select SSH Key:';
$_LANG['main']['create']['ssh_install'] = 'Add SSH Key:';
$_LANG['main']['create']['manage'] = 'Manage';
$_LANG['main']['create']['system'] = 'Select OS:';
$_LANG['main']['create']['location'] = 'Select Location:';
$_LANG['main']['create']['created_success'] = 'VPS creation has started';
$_LANG['main']['create']['no_ssh_found'] = 'No SSH keys found, please create one first';
$_LANG['main']['create']['reload_info'] = 'When the VPS is ready, the page will reload automatically.';

/**
 * SSH Keys
 */
$_LANG['sshkeys']['index']['panel_title'] = 'SSH Key Manager';
$_LANG['sshkeys']['index']['id'] = 'ID';
$_LANG['sshkeys']['index']['name'] = 'Name';
$_LANG['sshkeys']['index']['created'] = 'Created';
$_LANG['sshkeys']['index']['actions'] = 'Actions';
$_LANG['sshkeys']['index']['add'] = '+';
$_LANG['sshkeys']['index']['delete'] = 'Delete';
$_LANG['sshkeys']['index']['confirm'] = 'Are you sure you want to delete this SSH key?';
$_LANG['sshkeys']['index']['not_found'] = 'No SSH keys found';
$_LANG['sshkeys']['delete']['delete_success'] = 'SSH key has been successfully deleted';
$_LANG['sshkeys']['delete']['success'] = 'SSH key has been successfully deleted';
$_LANG['sshkeys']['delete']['delete_error'] = 'Error deleting SSH key, please try again';
$_LANG['sshkeys']['delete']['error'] = 'Error deleting SSH key, please try again';
$_LANG['sshkeys']['add']['success'] = 'SSH key has been successfully created';
$_LANG['sshkeys']['add']['error'] = 'Error creating SSH key';
$_LANG['sshkeys']['add']['name'] = 'Name';
$_LANG['sshkeys']['add']['ssh_key'] = 'SSH Key';
$_LANG['sshkeys']['add']['create'] = 'Create';
$_LANG['sshkeys']['add']['panel_title'] = 'SSH Key Manager - Create New';
$_LANG['sshkeys']['add']['add_success'] = 'SSH key has been successfully created';
$_LANG['sshkeys']['add']['add_error'] = 'Error creating SSH key';

/**
 * Firewall
 */
$_LANG['firewall']['index']['panel_title'] = 'Firewall Manager';
$_LANG['firewall']['index']['group'] = 'Group';
$_LANG['firewall']['index']['rules'] = 'Rules';
$_LANG['firewall']['index']['actions'] = 'Actions';
$_LANG['firewall']['index']['manage'] = 'Manage';
$_LANG['firewall']['index']['not_found'] = 'No firewall groups found';
$_LANG['firewall']['assign']['success'] = 'Firewall groups updated successfully';

/**
 * OS Change
 */
$_LANG['oschange']['index']['panel_title'] = 'Change Operating System';
$_LANG['oschange']['index']['label'] = 'Operating Systems';
$_LANG['oschange']['index']['change'] = 'Change';
$_LANG['oschange']['index']['confirm_os'] = 'Are you sure you want to change the operating system? All data will be permanently lost.';
$_LANG['oschange']['index']['current'] = 'Current operating system: ';
$_LANG['oschange']['index']['success'] = 'Operating system successfully changed';
$_LANG['oschange']['index']['no_available_oses'] = 'There are no operating systems available to change to';
$_LANG['oschange']['index']['vm_not_found'] = 'VPS not found';
$_LANG['oschange']['index']['back'] = 'Back';

/**
 * ISO Change
 */
$_LANG['isochange']['index']['panel_title'] = 'Change ISO';
$_LANG['isochange']['index']['label'] = 'Select ISO File';
$_LANG['isochange']['index']['current'] = 'Current ISO: ';
$_LANG['isochange']['index']['success'] = 'ISO successfully changed';
$_LANG['isochange']['index']['no_available_isos'] = 'There are no ISO files available';
$_LANG['isochange']['index']['mount'] = 'Mount';
$_LANG['isochange']['index']['unmount'] = 'Unmount';
$_LANG['isochange']['index']['mount_success'] = 'ISO has been mounted successfully';
$_LANG['isochange']['index']['unmount_success'] = 'ISO has been unmounted successfully';

/**
 * Console
 */
$_LANG['console']['index']['not_available'] = 'VNC console access is not yet available via the API. Please check back later.';

/**
 * Navigation Buttons
 */
$_LANG['elements']['buttons']['main_page'] = 'Main Page';
$_LANG['elements']['buttons']['backups'] = 'Backups';
$_LANG['elements']['buttons']['ssh_keys'] = 'SSH Keys';
$_LANG['elements']['buttons']['dns'] = 'DNS';
$_LANG['elements']['buttons']['firewall'] = 'Firewall';
$_LANG['elements']['buttons']['console'] = 'Console';

/**
 * Core Messages
 */
$_LANG['core']['client']['create_vm_first'] = 'Please create a VPS first';
$_LANG['core']['client']['action_not_found'] = 'Action Not Found (404)';
$_LANG['core']['client']['controller_not_found'] = 'Module Not Found (404)';
$_LANG['core']['client']['api_connection_error'] = 'API connection error';

/**
 * AJAX Actions
 */
$_LANG['core']['ajax']['start_success'] = 'Server is running';
$_LANG['core']['ajax']['reboot_success'] = 'Restart initiated';
$_LANG['core']['ajax']['stop_success'] = 'Server has been stopped';
$_LANG['core']['ajax']['reinstall_success'] = 'Operating system reinstallation has begun';
$_LANG['core']['ajax']['checkStatus'] = 'Server status: ';
$_LANG['core']['ajax']['service_not_active'] = 'Service is not active';

/**
 * Admin Hooks
 */
$_LANG['core']['hook']['connection_error'] = 'Connection error: please check your API token';
$_LANG['core']['hook']['save_key'] = 'Please save your API token';
$_LANG['core']['hook']['configurable_options_success'] = 'Configurable Options successfully created, please configure pricing.';
$_LANG['core']['hook']['custom_field_success'] = 'Custom fields have been created successfully';
$_LANG['core']['hook']['custom_field_exist'] = 'Custom fields already exist';

/**
 * Core Actions
 */
$_LANG['core']['action']['action_not_supported'] = 'Action not supported';
$_LANG['core']['action']['not_found_vps_id'] = 'VPS ID not found';
$_LANG['core']['action']['no_upgrades_available'] = 'There are currently no upgrades available for this plan';
$_LANG['core']['action']['cant_upgrade'] = 'This plan upgrade is not available';
$_LANG['core']['action']['template_not_found'] = 'Template file not found';
