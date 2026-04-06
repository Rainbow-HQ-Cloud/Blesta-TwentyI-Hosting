<?php

/**
 * Language strings for the 20i Hosting Blesta module.
 *
 * Keys prefixed with '!' are treated as error messages by the Blesta Input
 * component and are used with Input::setErrors().
 */

// Module identity
$lang['TwentyiHosting.name']             = '20i Hosting';
$lang['TwentyiHosting.description']      = 'Provision and manage 20i reseller hosting packages (shared Linux, WordPress, and Windows hosting).';
$lang['TwentyiHosting.module_row']       = '20i Account';
$lang['TwentyiHosting.module_row_plural'] = '20i Accounts';
$lang['TwentyiHosting.module_group']     = '20i Account Group';

// -------------------------------------------------------------------------
// Module row (API credentials)
// -------------------------------------------------------------------------
$lang['TwentyiHosting.row_fields.account_label']       = 'Account Label';
$lang['TwentyiHosting.row_fields.account_label_note']  = 'A friendly name for this set of credentials, e.g. "My 20i Reseller Account".';
$lang['TwentyiHosting.row_fields.api_key']             = 'API Key';
$lang['TwentyiHosting.row_fields.api_key_note']        = 'Your 20i General API key, available at my.20i.com/reseller/api.';
$lang['TwentyiHosting.row_fields.add_btn']             = 'Add Account';
$lang['TwentyiHosting.row_fields.edit_btn']            = 'Update Account';

// -------------------------------------------------------------------------
// Package fields
// -------------------------------------------------------------------------
$lang['TwentyiHosting.package_fields.package_type']      = 'Package Type';
$lang['TwentyiHosting.package_fields.package_type_note'] = 'Select the 20i hosting plan to provision when this Blesta package is ordered.';
$lang['TwentyiHosting.package_fields.loading']           = 'Loading package types…';
$lang['TwentyiHosting.package_fields.api_unavailable']   = 'Could not load package types from 20i. Please verify the API key in the module settings.';

// -------------------------------------------------------------------------
// Service / order fields
// -------------------------------------------------------------------------
$lang['TwentyiHosting.service_fields.domain']                  = 'Domain Name';
$lang['TwentyiHosting.service_fields.domain_placeholder']      = 'example.com';
$lang['TwentyiHosting.service_fields.domain_action']           = 'Domain Action';
$lang['TwentyiHosting.service_fields.domain_action_existing']  = 'Use an existing domain';
$lang['TwentyiHosting.service_fields.domain_action_register']  = 'Register a new domain';
$lang['TwentyiHosting.service_fields.package_id']              = '20i Package ID';
$lang['TwentyiHosting.service_fields.package_id_note']         = 'Leave blank when provisioning a new account. Paste an existing 20i package ID here when migrating from another platform.';

// -------------------------------------------------------------------------
// Tab labels
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_account']          = 'Account Overview';
$lang['TwentyiHosting.tab_raw_api']          = 'API Info';
$lang['TwentyiHosting.tab_dns']              = 'DNS Management';
$lang['TwentyiHosting.tab_email']            = 'Email Accounts';
$lang['TwentyiHosting.tab_ftp']              = 'FTP';
$lang['TwentyiHosting.tab_cache']            = 'Cache';
$lang['TwentyiHosting.tab_domains']          = 'Addon Domains';
$lang['TwentyiHosting.tab_nameservers']      = 'Nameservers';
$lang['TwentyiHosting.tab_client_account']   = 'My Account';
$lang['TwentyiHosting.tab_client_dns']       = 'DNS';
$lang['TwentyiHosting.tab_client_email']     = 'Email';
$lang['TwentyiHosting.tab_client_ftp']       = 'FTP';
$lang['TwentyiHosting.tab_client_domains']   = 'Addon Domains';
$lang['TwentyiHosting.tab_client_nameservers'] = 'Nameservers';

// -------------------------------------------------------------------------
// Account overview tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_account.domain']            = 'Primary Domain';
$lang['TwentyiHosting.tab_account.package_id']        = 'Package ID';
$lang['TwentyiHosting.tab_account.package_type']      = 'Package Type';
$lang['TwentyiHosting.tab_account.status']            = 'Status';
$lang['TwentyiHosting.tab_account.disk_usage']        = 'Disk Usage';
$lang['TwentyiHosting.tab_account.bandwidth_usage']   = 'Bandwidth Usage';
$lang['TwentyiHosting.tab_account.ssl_status']        = 'SSL Certificate';
$lang['TwentyiHosting.tab_account.ssl_valid']         = 'Valid';
$lang['TwentyiHosting.tab_account.ssl_invalid']       = 'Invalid / Not Issued';
$lang['TwentyiHosting.tab_account.ssl_pending']       = 'Pending';
$lang['TwentyiHosting.tab_account.ssl_expiry']        = 'Expires';
$lang['TwentyiHosting.tab_account.ssl_issuer']        = 'Issuer';
$lang['TwentyiHosting.tab_account.sso_btn']           = 'Login to Control Panel';
$lang['TwentyiHosting.tab_account.admin_sso_btn']     = 'Open in 20i';
$lang['TwentyiHosting.tab_account.sync_btn']          = 'Sync from 20i';
$lang['TwentyiHosting.tab_account.no_package_id']     = 'This service is not yet linked to a hosting account. Please contact support.';
$lang['TwentyiHosting.tab_account.package_not_found'] = 'This package was not found in 20i. It may have been deleted manually. Use the sync button or update the package ID via the service edit form.';

// -------------------------------------------------------------------------
// DNS tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_dns.title']       = 'DNS Records';
$lang['TwentyiHosting.tab_dns.add_record']  = 'Add DNS Record';
$lang['TwentyiHosting.tab_dns.host']        = 'Host';
$lang['TwentyiHosting.tab_dns.type']        = 'Type';
$lang['TwentyiHosting.tab_dns.data']        = 'Value / Data';
$lang['TwentyiHosting.tab_dns.ttl']         = 'TTL';
$lang['TwentyiHosting.tab_dns.priority']    = 'Priority';
$lang['TwentyiHosting.tab_dns.actions']     = 'Actions';
$lang['TwentyiHosting.tab_dns.delete_btn']  = 'Delete';
$lang['TwentyiHosting.tab_dns.add_btn']     = 'Add Record';
$lang['TwentyiHosting.tab_dns.no_records']  = 'No DNS records found.';

// -------------------------------------------------------------------------
// Email tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_email.title']           = 'Email Accounts';
$lang['TwentyiHosting.tab_email.add_account']     = 'Create Email Account';
$lang['TwentyiHosting.tab_email.address']         = 'Email Address';
$lang['TwentyiHosting.tab_email.local']           = 'Username';
$lang['TwentyiHosting.tab_email.password']        = 'Password';
$lang['TwentyiHosting.tab_email.password_change'] = 'Change Password';
$lang['TwentyiHosting.tab_email.last_changed']    = 'Password Changed';
$lang['TwentyiHosting.tab_email.new_password']    = 'New Password';
$lang['TwentyiHosting.tab_email.actions']         = 'Actions';
$lang['TwentyiHosting.tab_email.delete_btn']      = 'Delete';
$lang['TwentyiHosting.tab_email.create_btn']      = 'Create Account';
$lang['TwentyiHosting.tab_email.save_password']   = 'Save Password';
$lang['TwentyiHosting.tab_email.no_accounts']     = 'No email accounts found.';

// -------------------------------------------------------------------------
// FTP tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_ftp.title']             = 'FTP Access';
$lang['TwentyiHosting.tab_ftp.lock_status']       = 'FTP Lock';
$lang['TwentyiHosting.tab_ftp.locked']            = 'Locked (FTP disabled)';
$lang['TwentyiHosting.tab_ftp.unlocked']          = 'Unlocked (FTP enabled)';
$lang['TwentyiHosting.tab_ftp.enable_btn']        = 'Enable FTP';
$lang['TwentyiHosting.tab_ftp.disable_btn']       = 'Disable FTP';
$lang['TwentyiHosting.tab_ftp.reset_password']    = 'Reset FTP Password';
$lang['TwentyiHosting.tab_ftp.new_password']      = 'New FTP Password';
$lang['TwentyiHosting.tab_ftp.reset_btn']         = 'Reset Password';

// -------------------------------------------------------------------------
// Cache tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_cache.title']       = 'Cache Management';
$lang['TwentyiHosting.tab_cache.description'] = 'Purge the CDN and server-side cache for this hosting package.';
$lang['TwentyiHosting.tab_cache.purge_btn']   = 'Purge Cache';
$lang['TwentyiHosting.tab_cache.purged']      = 'Cache purged successfully.';

// -------------------------------------------------------------------------
// Addon domains tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_domains.title']       = 'Addon Domains';
$lang['TwentyiHosting.tab_domains.add_domain']  = 'Add Addon Domain';
$lang['TwentyiHosting.tab_domains.domain']      = 'Domain Name';
$lang['TwentyiHosting.tab_domains.actions']     = 'Actions';
$lang['TwentyiHosting.tab_domains.add_btn']     = 'Add Domain';
$lang['TwentyiHosting.tab_domains.delete_btn']  = 'Remove';
$lang['TwentyiHosting.tab_domains.no_domains']  = 'No addon domains attached to this package.';

// -------------------------------------------------------------------------
// Nameservers tab
// -------------------------------------------------------------------------
$lang['TwentyiHosting.tab_nameservers.title']       = 'Nameservers';
$lang['TwentyiHosting.tab_nameservers.description'] = 'Update the nameservers for your registered domain.';
$lang['TwentyiHosting.tab_nameservers.ns1']         = 'Nameserver 1';
$lang['TwentyiHosting.tab_nameservers.ns2']         = 'Nameserver 2';
$lang['TwentyiHosting.tab_nameservers.ns3']         = 'Nameserver 3 (optional)';
$lang['TwentyiHosting.tab_nameservers.ns4']         = 'Nameserver 4 (optional)';
$lang['TwentyiHosting.tab_nameservers.save_btn']    = 'Update Nameservers';
$lang['TwentyiHosting.tab_nameservers.not_applicable'] = 'Nameserver management is only available for domains registered through your hosting provider.';

// -------------------------------------------------------------------------
// Success notices
// -------------------------------------------------------------------------
$lang['TwentyiHosting.notice.dns_record_added']     = 'DNS record added successfully.';
$lang['TwentyiHosting.notice.dns_record_deleted']   = 'DNS record deleted.';
$lang['TwentyiHosting.notice.email_created']        = 'Email account created successfully.';
$lang['TwentyiHosting.notice.email_deleted']        = 'Email account deleted.';
$lang['TwentyiHosting.notice.email_password_saved'] = 'Email password updated successfully.';
$lang['TwentyiHosting.notice.ftp_lock_on']          = 'FTP has been disabled (locked).';
$lang['TwentyiHosting.notice.ftp_lock_off']         = 'FTP has been enabled (unlocked).';
$lang['TwentyiHosting.notice.ftp_password_reset']   = 'FTP password reset successfully.';
$lang['TwentyiHosting.notice.cache_purged']         = 'Cache purged successfully.';
$lang['TwentyiHosting.notice.domain_added']         = 'Addon domain added successfully.';
$lang['TwentyiHosting.notice.domain_removed']       = 'Addon domain removed.';
$lang['TwentyiHosting.notice.nameservers_updated']  = 'Nameservers updated successfully.';
$lang['TwentyiHosting.notice.synced']               = 'Account details synced from 20i.';

// -------------------------------------------------------------------------
// Validation errors
// -------------------------------------------------------------------------
$lang['TwentyiHosting.!error.account_label_valid']   = 'Please enter an account label.';
$lang['TwentyiHosting.!error.api_key_valid']         = 'Please enter a 20i API key.';
$lang['TwentyiHosting.!error.api_key_connection']    = 'Could not connect to the 20i API with the provided key. Please check the key and try again.';
$lang['TwentyiHosting.!error.package_type_valid']    = 'Please select a package type.';
$lang['TwentyiHosting.!error.domain_valid']          = 'Please enter a valid domain name (e.g. example.com).';
$lang['TwentyiHosting.!error.domain_action_valid']   = 'Please select a domain action.';
$lang['TwentyiHosting.!error.module_row_missing']    = 'No 20i account is configured. Please add a module server in the module settings.';
$lang['TwentyiHosting.!error.api_addweb']            = 'Failed to provision your hosting account. Please contact support if this problem persists.';
$lang['TwentyiHosting.!error.api_timeout']           = 'The request timed out. Please try again.';
$lang['TwentyiHosting.!error.api_rate_limited']      = 'Too many requests. Please wait a moment and try again.';
$lang['TwentyiHosting.!error.api_error']             = 'An error occurred processing your request. Please contact support if this problem persists.';
$lang['TwentyiHosting.!error.dns_host_valid']        = 'Please enter a valid hostname for the DNS record.';
$lang['TwentyiHosting.!error.dns_type_valid']        = 'Please select a valid DNS record type.';
$lang['TwentyiHosting.!error.dns_data_valid']        = 'Please enter a value for the DNS record.';
$lang['TwentyiHosting.!error.email_local_valid']     = 'Please enter a valid email username (letters, numbers, dots, hyphens, and underscores only).';
$lang['TwentyiHosting.!error.email_password_valid']  = 'Password must be at least 8 characters and contain at least one number.';
$lang['TwentyiHosting.!error.ftp_password_valid']    = 'FTP password must be at least 8 characters and contain at least one number.';
$lang['TwentyiHosting.!error.addon_domain_valid']    = 'Please enter a valid domain name to add.';
$lang['TwentyiHosting.!error.ns_valid']              = 'Please enter at least two valid nameservers.';
