<?php

declare(strict_types=1);

/**
 * 20i Hosting Module for Blesta 5.x
 *
 * Provisions and manages shared Linux, WordPress, and Windows hosting
 * packages via the 20i Reseller API.
 *
 * @license MIT
 * @link    https://github.com/blesta/twentyi-hosting
 */
class TwentyiHosting extends Module
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct()
    {
        // Language MUST be loaded before loadConfig() so that config name keys
        // are translated immediately.
        Language::loadLang(
            'twentyi_hosting',
            null,
            __DIR__ . DS . 'language' . DS
        );
        $this->loadConfig(__DIR__ . DS . 'config.json');
        $this->loadComponents(['Input']);

        // Load the Composer autoloader for the 20i SDK and our API wrapper.
        $autoloader = __DIR__ . DS . 'vendor' . DS . 'autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        // Register our own API class from the apis/ directory.
        require_once __DIR__ . DS . 'apis' . DS . 'TwentyIApi.php';
    }

    // -------------------------------------------------------------------------
    // Identity methods (required by Blesta)
    // -------------------------------------------------------------------------

    public function getName(): string
    {
        return Language::_('TwentyiHosting.name', true);
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @return array<int,array{name:string,url:string}>
     */
    public function getAuthors(): array
    {
        return [['name' => 'Markus Simpson', 'url' => 'https://github.com/markus-blesta-twentyi']];
    }

    public function moduleRowName(): string
    {
        return Language::_('TwentyiHosting.module_row', true);
    }

    public function moduleRowNamePlural(): string
    {
        return Language::_('TwentyiHosting.module_row_plural', true);
    }

    public function moduleGroupName(): string
    {
        return Language::_('TwentyiHosting.module_group', true);
    }

    public function moduleRowMetaKey(): string
    {
        return 'account_label';
    }

    /**
     * Returns the display name for an active service (used in service lists).
     */
    public function getServiceName(mixed $service): ?string
    {
        if (!isset($service->fields)) {
            return null;
        }
        foreach ($service->fields as $field) {
            if ($field->key === 'twentyi_domain') {
                return $field->value;
            }
        }
        return null;
    }

    /**
     * Returns the display name before provisioning (during order preview).
     *
     * @param array<string,mixed> $vars
     */
    public function getPackageServiceName(mixed $package, array $vars): ?string
    {
        return $vars['twentyi_domain'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Module row management (API credentials)
    // -------------------------------------------------------------------------

    public function manageModule(mixed $module, array &$vars): string
    {
        $view = $this->makeView('manage', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));
        $this->view = $view;
        $this->view->set('module', $module);
        return $this->view->fetch();
    }

    public function manageAddRow(array &$vars): string
    {
        $this->view = $this->makeView('add_row', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));
        $this->view->set('vars', (object) $vars);
        return $this->view->fetch();
    }

    public function manageEditRow(mixed $module_row, array &$vars): string
    {
        $this->view = $this->makeView('edit_row', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));
        if (empty($vars)) {
            $vars = (array) $module_row->meta;
        }
        $this->view->set('vars', (object) $vars);
        return $this->view->fetch();
    }

    /**
     * Validate and save a new module row (API credentials).
     *
     * @param array<string,mixed> $vars
     * @return array<int,array{key:string,value:string,encrypted:int}>
     */
    public function addModuleRow(array &$vars): array
    {
        $rules = $this->getRowRules($vars);
        $this->Input->setRules($rules);

        if ($this->Input->validates($vars)) {
            return [
                ['key' => 'account_label', 'value' => $vars['account_label'], 'encrypted' => 0],
                ['key' => 'api_key',       'value' => $vars['api_key'],       'encrypted' => 1],
            ];
        }
        return [];
    }

    /**
     * Validate and update an existing module row.
     *
     * @param array<string,mixed> $vars
     * @return array<int,array{key:string,value:string,encrypted:int}>
     */
    public function editModuleRow(mixed $module_row, array &$vars): array
    {
        return $this->addModuleRow($vars);
    }

    public function deleteModuleRow(mixed $module_row): void
    {
        // No remote cleanup required when removing API credentials.
    }

    /**
     * Validation rules for a module row.
     *
     * @param array<string,mixed> $vars
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function getRowRules(array &$vars): array
    {
        return [
            'account_label' => [
                'valid' => [
                    'rule'    => ['isEmpty'],
                    'negate'  => true,
                    'message' => Language::_('TwentyiHosting.!error.account_label_valid', true),
                ],
            ],
            'api_key' => [
                'valid' => [
                    'rule'    => ['isEmpty'],
                    'negate'  => true,
                    'message' => Language::_('TwentyiHosting.!error.api_key_valid', true),
                ],
                'connection' => [
                    'rule'    => [[$this, 'validateApiKey']],
                    'message' => Language::_('TwentyiHosting.!error.api_key_connection', true),
                ],
            ],
        ];
    }

    /**
     * Test that a given API key can authenticate with 20i.
     * Used as a validation rule — returns true on success.
     */
    public function validateApiKey(string $apiKey): bool
    {
        try {
            $api = new TwentyIApi($apiKey);
            return $api->testConnection();
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Package configuration
    // -------------------------------------------------------------------------

    /**
     * Returns the fields shown to an admin when creating/editing a Blesta package.
     */
    public function getPackageFields(mixed $vars = null): ModuleFields
    {
        Loader::loadHelpers($this, ['Html']);
        $fields = new ModuleFields();

        $packageTypes  = [];
        $apiError      = null;
        $row           = $this->getModuleRow();

        if ($row) {
            try {
                $api   = $this->getApi($row);
                $types = $api->getPackageTypes();
                foreach ($types as $type) {
                    $id   = (string) ($type->id ?? $type->typeRef ?? '');
                    $name = (string) ($type->display_name ?? $type->name ?? $id);
                    if ($id !== '') {
                        $packageTypes[$id] = $name;
                    }
                }
            } catch (\Throwable $e) {
                $apiError = Language::_('TwentyiHosting.package_fields.api_unavailable', true);
                $this->log('getPackageTypes', $e->getMessage(), 'output', false);
            }
        }

        if ($apiError !== null) {
            $notice = $fields->fieldHidden('api_error', '');
            $notice->attach($fields->label($apiError));
            $fields->setField($notice);
        }

        $currentType  = $vars->meta['twentyi_package_type'] ?? null;
        $packageField = $fields->fieldSelect(
            'meta[twentyi_package_type]',
            $packageTypes,
            $currentType,
            ['id' => 'twentyi_package_type', 'class' => 'form-select']
        );
        $label = $fields->label(
            Language::_('TwentyiHosting.package_fields.package_type', true),
            'twentyi_package_type'
        );
        $packageField->attach($label);
        $fields->setField($packageField);

        return $fields;
    }

    /**
     * Validate and store package meta when admin saves a package.
     *
     * @param array<string,mixed> $vars
     * @return array<int,array{key:string,value:string,encrypted:int}>
     */
    public function addPackage(array $vars): array
    {
        $this->Input->setRules($this->getPackageRules());
        if ($this->Input->validates($vars)) {
            return [
                ['key' => 'twentyi_package_type', 'value' => $vars['meta']['twentyi_package_type'], 'encrypted' => 0],
            ];
        }
        return [];
    }

    /**
     * @param array<string,mixed> $vars
     * @return array<int,array{key:string,value:string,encrypted:int}>
     */
    public function editPackage(mixed $package, array $vars): array
    {
        return $this->addPackage($vars);
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function getPackageRules(): array
    {
        return [
            'meta[twentyi_package_type]' => [
                'valid' => [
                    'rule'    => ['isEmpty'],
                    'negate'  => true,
                    'message' => Language::_('TwentyiHosting.!error.package_type_valid', true),
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Service / order fields (shown at order time)
    // -------------------------------------------------------------------------

    public function getAdminAddFields(mixed $package, mixed $vars = null): ModuleFields
    {
        return $this->buildServiceFields($vars, isAdmin: true, isEdit: false);
    }

    public function getClientAddFields(mixed $package, mixed $vars = null): ModuleFields
    {
        return $this->buildServiceFields($vars, isAdmin: false, isEdit: false);
    }

    public function getAdminEditFields(mixed $package, mixed $service): ModuleFields
    {
        $vars = $this->serviceFieldsToVars($service->fields ?? []);
        return $this->buildServiceFields($vars, isAdmin: true, isEdit: true);
    }

    /**
     * Build the domain + domain-action fields, with an extra package ID field
     * shown only to admins in edit mode (for WHMCS migration / manual linking).
     */
    private function buildServiceFields(mixed $vars, bool $isAdmin, bool $isEdit): ModuleFields
    {
        Loader::loadHelpers($this, ['Html']);
        $fields = new ModuleFields();

        // Domain name
        $domainField = $fields->fieldText(
            'twentyi_domain',
            $vars->twentyi_domain ?? '',
            ['id' => 'twentyi_domain', 'class' => 'form-control', 'placeholder' => Language::_('TwentyiHosting.service_fields.domain_placeholder', true)]
        );
        $domainField->attach($fields->label(Language::_('TwentyiHosting.service_fields.domain', true), 'twentyi_domain'));
        $fields->setField($domainField);

        // Domain action (new or existing) — only on add, not edit
        if (!$isEdit) {
            $actionField = $fields->fieldSelect(
                'twentyi_domain_action',
                [
                    'use_existing' => Language::_('TwentyiHosting.service_fields.domain_action_existing', true),
                    'register_new' => Language::_('TwentyiHosting.service_fields.domain_action_register', true),
                ],
                $vars->twentyi_domain_action ?? 'use_existing',
                ['id' => 'twentyi_domain_action', 'class' => 'form-select']
            );
            $actionField->attach($fields->label(Language::_('TwentyiHosting.service_fields.domain_action', true), 'twentyi_domain_action'));
            $fields->setField($actionField);
        }

        // Package ID — editable only by admins (migration / manual link use case)
        if ($isAdmin && $isEdit) {
            $pkgField = $fields->fieldText(
                'twentyi_package_id',
                $vars->twentyi_package_id ?? '',
                ['id' => 'twentyi_package_id', 'class' => 'form-control']
            );
            $pkgField->attach($fields->label(Language::_('TwentyiHosting.service_fields.package_id', true), 'twentyi_package_id'));
            $fields->setField($pkgField);
        }

        return $fields;
    }

    // -------------------------------------------------------------------------
    // Service lifecycle
    // -------------------------------------------------------------------------

    /**
     * Provision a new hosting package.
     *
     * @param array<string,mixed>|null $vars
     * @return array<int,array{key:string,value:mixed,encrypted:int}>
     */
    public function addService(
        mixed $package,
        ?array $vars = null,
        mixed $parent_package = null,
        mixed $parent_service = null,
        string $status = 'pending'
    ): array {
        $row = $this->getModuleRow();
        if (!$row) {
            $this->Input->setErrors(['module_row' => ['missing' => Language::_('TwentyiHosting.!error.module_row_missing', true)]]);
            return [];
        }

        $this->Input->setRules($this->getServiceRules($vars ?? []));
        if (!$this->Input->validates($vars)) {
            return [];
        }

        $domain       = strtolower(trim((string) ($vars['twentyi_domain'] ?? '')));
        $domainAction = $vars['twentyi_domain_action'] ?? 'use_existing';
        $packageType  = $package->meta->twentyi_package_type ?? '';
        $packageId    = '';

        // Only call the 20i API if Blesta says to use the module.
        if (($vars['use_module'] ?? 'true') !== 'false') {
            $api = $this->getApi($row);

            $payload = [
                'type'        => $packageType,
                'domain_name' => $domain,
            ];
            if ($domainAction === 'register_new') {
                $payload['register_domain'] = true;
            }

            $this->log('addWeb', serialize($api->redactForLog($payload)), 'input', true);

            try {
                $response = $api->addWeb($payload);
            } catch (\RuntimeException $e) {
                $this->log('addWeb', $e->getMessage(), 'output', false);
                $this->Input->setErrors(['api' => ['addweb' => Language::_('TwentyiHosting.!error.api_addweb', true)]]);
                return [];
            }

            $this->log('addWeb', serialize($response), 'output', true);

            // The response id may be a nested array e.g. {"result": [packageId]}
            $rawId = $response->result ?? $response->id ?? null;
            if (is_array($rawId)) {
                $rawId = $rawId[0] ?? null;
            }

            if (empty($rawId)) {
                $this->Input->setErrors(['api' => ['addweb' => Language::_('TwentyiHosting.!error.api_addweb', true)]]);
                return [];
            }

            $packageId = (string) $rawId;
        }

        return [
            ['key' => 'twentyi_domain',        'value' => $domain,       'encrypted' => 0],
            ['key' => 'twentyi_domain_action',  'value' => $domainAction, 'encrypted' => 0],
            ['key' => 'twentyi_package_type',   'value' => $packageType,  'encrypted' => 0],
            ['key' => 'twentyi_package_id',     'value' => $packageId,    'encrypted' => 0],
            ['key' => 'twentyi_username',       'value' => '',            'encrypted' => 0],
        ];
    }

    /**
     * Edit service (domain cannot change post-provisioning; handles package ID
     * updates from the admin edit form for WHMCS migration).
     *
     * @param array<string,mixed>|null $vars
     * @return array<int,array{key:string,value:mixed,encrypted:int}>|null
     */
    public function editService(
        mixed $package,
        mixed $service,
        ?array $vars = null,
        mixed $parent_package = null,
        mixed $parent_service = null
    ): ?array {
        $existing = $this->serviceFieldsToVars($service->fields ?? []);

        // Allow admin to manually update the package ID (migration use case).
        $newPackageId = trim((string) ($vars['twentyi_package_id'] ?? ''));
        if ($newPackageId !== '' && $newPackageId !== ($existing->twentyi_package_id ?? '')) {
            return [
                ['key' => 'twentyi_package_id', 'value' => $newPackageId, 'encrypted' => 0],
            ];
        }

        return null;
    }

    /**
     * Suspend a hosting package.
     */
    public function suspendService(
        mixed $package,
        mixed $service,
        mixed $parent_package = null,
        mixed $parent_service = null
    ): ?array {
        if ($row = $this->getModuleRow()) {
            $fields    = $this->serviceFieldsToVars($service->fields ?? []);
            $packageId = $fields->twentyi_package_id ?? '';

            if ($packageId !== '') {
                $api = $this->getApi($row);
                $this->log("suspend|{$packageId}", '', 'input', true);
                try {
                    $response = $api->suspendPackage($packageId);
                    $this->log("suspend|{$packageId}", serialize($response), 'output', true);
                } catch (\RuntimeException $e) {
                    $this->log("suspend|{$packageId}", $e->getMessage(), 'output', false);
                    $this->Input->setErrors(['api' => ['error' => Language::_('TwentyiHosting.!error.api_error', true)]]);
                }
            }
        }
        return null;
    }

    /**
     * Unsuspend (re-enable) a hosting package.
     */
    public function unsuspendService(
        mixed $package,
        mixed $service,
        mixed $parent_package = null,
        mixed $parent_service = null
    ): ?array {
        if ($row = $this->getModuleRow()) {
            $fields    = $this->serviceFieldsToVars($service->fields ?? []);
            $packageId = $fields->twentyi_package_id ?? '';

            if ($packageId !== '') {
                $api = $this->getApi($row);
                $this->log("unsuspend|{$packageId}", '', 'input', true);
                try {
                    $response = $api->unsuspendPackage($packageId);
                    $this->log("unsuspend|{$packageId}", serialize($response), 'output', true);
                } catch (\RuntimeException $e) {
                    $this->log("unsuspend|{$packageId}", $e->getMessage(), 'output', false);
                    $this->Input->setErrors(['api' => ['error' => Language::_('TwentyiHosting.!error.api_error', true)]]);
                }
            }
        }
        return null;
    }

    /**
     * Terminate (delete) a hosting package.
     */
    public function cancelService(
        mixed $package,
        mixed $service,
        mixed $parent_package = null,
        mixed $parent_service = null
    ): ?array {
        if ($row = $this->getModuleRow()) {
            $fields    = $this->serviceFieldsToVars($service->fields ?? []);
            $packageId = $fields->twentyi_package_id ?? '';

            if ($packageId !== '') {
                $api = $this->getApi($row);
                $this->log("delete|{$packageId}", '', 'input', true);
                try {
                    $response = $api->deletePackage($packageId);
                    $this->log("delete|{$packageId}", serialize($response), 'output', true);
                } catch (\RuntimeException $e) {
                    $this->log("delete|{$packageId}", $e->getMessage(), 'output', false);
                    $this->Input->setErrors(['api' => ['error' => Language::_('TwentyiHosting.!error.api_error', true)]]);
                }
            }
        }
        return null;
    }

    /**
     * Upgrade or downgrade a service to a different package type.
     *
     * @return array<int,array{key:string,value:string,encrypted:int}>|null
     */
    public function changeServicePackage(
        mixed $package_from,
        mixed $package_to,
        mixed $service,
        mixed $parent_package = null,
        mixed $parent_service = null
    ): ?array {
        $row = $this->getModuleRow();
        if (!$row) {
            return null;
        }

        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $newType   = $package_to->meta->twentyi_package_type ?? '';

        if ($packageId !== '' && $newType !== '') {
            $api = $this->getApi($row);
            $this->log("changeType|{$packageId}", "new_type={$newType}", 'input', true);
            try {
                $response = $api->changePackageType($packageId, $newType);
                $this->log("changeType|{$packageId}", serialize($response), 'output', true);
            } catch (\RuntimeException $e) {
                $this->log("changeType|{$packageId}", $e->getMessage(), 'output', false);
                $this->Input->setErrors(['api' => ['error' => Language::_('TwentyiHosting.!error.api_error', true)]]);
                return null;
            }

            // Update the stored package type.
            return [
                ['key' => 'twentyi_package_type', 'value' => $newType, 'encrypted' => 0],
            ];
        }

        return null;
    }

    /**
     * Validate service order fields.
     *
     * @param array<string,mixed> $vars
     */
    public function validateService(mixed $package, array $vars): bool
    {
        $this->Input->setRules($this->getServiceRules($vars));
        return $this->Input->validates($vars);
    }

    /**
     * @param array<string,mixed> $vars
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function getServiceRules(array $vars): array
    {
        return [
            'twentyi_domain' => [
                'valid' => [
                    'rule'    => [[$this, 'validateDomain']],
                    'message' => Language::_('TwentyiHosting.!error.domain_valid', true),
                ],
            ],
            'twentyi_domain_action' => [
                'valid' => [
                    'rule'    => ['in_array', ['use_existing', 'register_new']],
                    'message' => Language::_('TwentyiHosting.!error.domain_action_valid', true),
                    'if_set'  => true,
                ],
            ],
        ];
    }

    /**
     * Validate a domain name submitted at order time.
     */
    public function validateDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return false;
        }
        // Strip a leading 'www.' so that www.example.com is treated as example.com
        $domain = preg_replace('/^www\./i', '', $domain) ?? $domain;
        return (bool) filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    // -------------------------------------------------------------------------
    // Tabs — registration
    // -------------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    public function getAdminTabs(mixed $package): array
    {
        return [
            'tabAccount'     => Language::_('TwentyiHosting.tab_account', true),
            'tabRawApi'      => Language::_('TwentyiHosting.tab_raw_api', true),
            'tabDns'         => Language::_('TwentyiHosting.tab_dns', true),
            'tabEmail'       => Language::_('TwentyiHosting.tab_email', true),
            'tabFtp'         => Language::_('TwentyiHosting.tab_ftp', true),
            'tabCache'       => Language::_('TwentyiHosting.tab_cache', true),
            'tabDomains'     => Language::_('TwentyiHosting.tab_domains', true),
            'tabNameservers' => Language::_('TwentyiHosting.tab_nameservers', true),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getClientTabs(mixed $package): array
    {
        return [
            'tabClientAccount'     => Language::_('TwentyiHosting.tab_client_account', true),
            'tabClientDns'         => Language::_('TwentyiHosting.tab_client_dns', true),
            'tabClientEmail'       => Language::_('TwentyiHosting.tab_client_email', true),
            'tabClientFtp'         => Language::_('TwentyiHosting.tab_client_ftp', true),
            'tabClientDomains'     => Language::_('TwentyiHosting.tab_client_domains', true),
            'tabClientNameservers' => Language::_('TwentyiHosting.tab_client_nameservers', true),
        ];
    }

    // -------------------------------------------------------------------------
    // Admin tabs — implementation
    // -------------------------------------------------------------------------

    /**
     * Account overview: domain, usage stats, SSL status, SSO, sync.
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabAccount(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView('tab_account', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        // Handle sync / SSO POST actions
        if (!empty($post)) {
            if (isset($post['action'])) {
                $this->handleAccountPost($post['action'], $packageId, $fields);
            }
        }

        $packageData = null;
        $sslData     = null;
        $ssoUrl      = null;
        $apiError    = null;

        if ($row = $this->getModuleRow()) {
            $api = $this->getApi($row);
            try {
                $packageData = $api->getPackage($packageId);
            } catch (\RuntimeException $e) {
                $apiError = Language::_('TwentyiHosting.tab_account.package_not_found', true);
                $this->log("getPackage|{$packageId}", $e->getMessage(), 'output', false);
            }

            if ($packageData !== null) {
                try {
                    $sslData = $api->getSslStatus($packageId, $fields->twentyi_domain ?? '');
                } catch (\RuntimeException) {
                    // SSL status is non-critical; silently skip if unavailable.
                }

                try {
                    $ssoUrl = $api->getSsoToken($packageId);
                } catch (\RuntimeException $e) {
                    $this->log("sso|{$packageId}", $e->getMessage(), 'output', false);
                }
            }
        }

        $view->set('fields',      $fields);
        $view->set('packageData', $packageData);
        $view->set('sslData',     $sslData);
        $view->set('ssoUrl',      $ssoUrl);
        $view->set('apiError',    $apiError);
        return $view->fetch();
    }

    /**
     * @param array<string,mixed> $fields
     */
    private function handleAccountPost(string $action, string $packageId, object $fields): void
    {
        if ($action !== 'sync') {
            return;
        }
        // Sync is handled by simply reloading the package data (which happens above).
        // We log the intent for auditability.
        $this->log("sync|{$packageId}", '', 'input', true);
    }

    /**
     * Raw API info: JSON dump of the package object.
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabRawApi(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView('tab_raw_api', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $rawData  = null;
        $apiError = null;

        if ($packageId !== '' && ($row = $this->getModuleRow())) {
            try {
                $rawData = $this->getApi($row)->getPackage($packageId);
            } catch (\RuntimeException $e) {
                $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                $this->log("getRawApi|{$packageId}", $e->getMessage(), 'output', false);
            }
        }

        $view->set('rawData',  $rawData !== null ? json_encode($rawData, JSON_PRETTY_PRINT) : null);
        $view->set('apiError', $apiError);
        return $view->fetch();
    }

    /**
     * DNS management tab (admin).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabDns(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderDnsTab($package, $service, $post, isAdmin: true);
    }

    /**
     * Email management tab (admin).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabEmail(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderEmailTab($package, $service, $post, isAdmin: true);
    }

    /**
     * FTP management tab (admin).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabFtp(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderFtpTab($package, $service, $post, isAdmin: true);
    }

    /**
     * Cache purge tab (admin only).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabCache(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView('tab_cache', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $notice   = null;
        $apiError = null;

        if (!empty($post['action']) && $post['action'] === 'purge_cache' && $packageId !== '') {
            if ($row = $this->getModuleRow()) {
                $this->log("purgeCache|{$packageId}", '', 'input', true);
                try {
                    $response = $this->getApi($row)->purgeCache($packageId);
                    $this->log("purgeCache|{$packageId}", serialize($response), 'output', true);
                    $notice = Language::_('TwentyiHosting.notice.cache_purged', true);
                } catch (\RuntimeException $e) {
                    $this->log("purgeCache|{$packageId}", $e->getMessage(), 'output', false);
                    $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                }
            }
        }

        $view->set('fields',    $fields);
        $view->set('notice',    $notice);
        $view->set('apiError',  $apiError);
        return $view->fetch();
    }

    /**
     * Addon domains tab (admin).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabDomains(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderDomainsTab($package, $service, $post, isAdmin: true);
    }

    /**
     * Nameservers tab (admin).
     *
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabNameservers(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderNameserversTab($package, $service, $post, isAdmin: true);
    }

    // -------------------------------------------------------------------------
    // Client tabs — implementation
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientAccount(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView('tab_client_account', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        // SSO is delivered via a POST action that results in a server-side redirect.
        if (!empty($post['action']) && $post['action'] === 'sso' && $packageId !== '') {
            if ($row = $this->getModuleRow()) {
                try {
                    $ssoUrl = $this->getApi($row)->getSsoToken($packageId);
                    header('Location: ' . $ssoUrl, true, 302);
                    exit;
                } catch (\RuntimeException $e) {
                    $this->log("clientSso|{$packageId}", $e->getMessage(), 'output', false);
                }
            }
        }

        $packageData = null;
        $sslData     = null;
        $apiError    = null;

        if ($row = $this->getModuleRow()) {
            $api = $this->getApi($row);
            try {
                $packageData = $api->getPackage($packageId);
            } catch (\RuntimeException $e) {
                $apiError = Language::_('TwentyiHosting.tab_account.package_not_found', true);
                $this->log("getPackage|{$packageId}", $e->getMessage(), 'output', false);
            }

            if ($packageData !== null) {
                try {
                    $sslData = $api->getSslStatus($packageId, $fields->twentyi_domain ?? '');
                } catch (\RuntimeException) {
                    // Non-critical.
                }
            }
        }

        $view->set('fields',      $fields);
        $view->set('packageData', $packageData);
        $view->set('sslData',     $sslData);
        $view->set('apiError',    $apiError);
        return $view->fetch();
    }

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientDns(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderDnsTab($package, $service, $post, isAdmin: false);
    }

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientEmail(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderEmailTab($package, $service, $post, isAdmin: false);
    }

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientFtp(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderFtpTab($package, $service, $post, isAdmin: false);
    }

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientDomains(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderDomainsTab($package, $service, $post, isAdmin: false);
    }

    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function tabClientNameservers(mixed $package, mixed $service, array $get, array $post, array $files): string
    {
        return $this->renderNameserversTab($package, $service, $post, isAdmin: false);
    }

    // -------------------------------------------------------------------------
    // Shared tab renderers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $post
     */
    private function renderDnsTab(mixed $package, mixed $service, array $post, bool $isAdmin): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $domain    = $fields->twentyi_domain ?? '';
        $view      = $this->makeView($isAdmin ? 'tab_dns' : 'tab_client_dns', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $notice   = null;
        $apiError = null;
        $records  = [];

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        $row = $this->getModuleRow();
        if (!$row) {
            $view->set('apiError', Language::_('TwentyiHosting.!error.module_row_missing', true));
            return $view->fetch();
        }

        $api = $this->getApi($row);

        // Handle form submissions
        if (!empty($post['action'])) {
            switch ($post['action']) {
                case 'add_record':
                    $errors = $this->validateDnsRecord($post);
                    if (empty($errors)) {
                        $record = [
                            'type' => strtoupper($post['type'] ?? ''),
                            'host' => $post['host'] ?? '',
                            'data' => $post['data'] ?? '',
                            'ttl'  => (int) ($post['ttl'] ?? 3600),
                        ];
                        if (isset($post['priority']) && $post['priority'] !== '') {
                            $record['priority'] = (int) $post['priority'];
                        }
                        $this->log("addDns|{$packageId}", serialize($record), 'input', true);
                        try {
                            $response = $api->addDnsRecord($packageId, $domain, $record);
                            $this->log("addDns|{$packageId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.dns_record_added', true);
                        } catch (\RuntimeException $e) {
                            $this->log("addDns|{$packageId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    } else {
                        $this->Input->setErrors($errors);
                    }
                    break;

                case 'delete_record':
                    $recordId = trim((string) ($post['record_id'] ?? ''));
                    if ($recordId !== '') {
                        $this->log("deleteDns|{$packageId}|{$recordId}", '', 'input', true);
                        try {
                            $response = $api->deleteDnsRecord($packageId, $domain, $recordId);
                            $this->log("deleteDns|{$packageId}|{$recordId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.dns_record_deleted', true);
                        } catch (\RuntimeException $e) {
                            $this->log("deleteDns|{$packageId}|{$recordId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    }
                    break;
            }
        }

        // Load current records
        try {
            $records = $api->getDnsRecords($packageId, $domain);
        } catch (\RuntimeException $e) {
            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
            $this->log("getDns|{$packageId}", $e->getMessage(), 'output', false);
        }

        $view->set('fields',   $fields);
        $view->set('records',  $records);
        $view->set('post',     $post);
        $view->set('notice',   $notice);
        $view->set('apiError', $apiError);
        return $view->fetch();
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,array<string,string>>
     */
    private function validateDnsRecord(array $post): array
    {
        $errors = [];
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];

        if (empty($post['host'])) {
            $errors['dns_host'] = ['valid' => Language::_('TwentyiHosting.!error.dns_host_valid', true)];
        }
        if (empty($post['type']) || !in_array(strtoupper($post['type']), $validTypes, true)) {
            $errors['dns_type'] = ['valid' => Language::_('TwentyiHosting.!error.dns_type_valid', true)];
        }
        if (empty($post['data'])) {
            $errors['dns_data'] = ['valid' => Language::_('TwentyiHosting.!error.dns_data_valid', true)];
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function renderEmailTab(mixed $package, mixed $service, array $post, bool $isAdmin): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $domain    = $fields->twentyi_domain ?? '';
        $view      = $this->makeView($isAdmin ? 'tab_email' : 'tab_client_email', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $notice   = null;
        $apiError = null;
        $accounts = [];

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        $row = $this->getModuleRow();
        if (!$row) {
            $view->set('apiError', Language::_('TwentyiHosting.!error.module_row_missing', true));
            return $view->fetch();
        }

        $api = $this->getApi($row);

        if (!empty($post['action'])) {
            switch ($post['action']) {
                case 'create_account':
                    $local    = trim(strtolower((string) ($post['local'] ?? '')));
                    $password = (string) ($post['password'] ?? '');
                    $errors   = $this->validateEmailCredentials($local, $password);
                    if (empty($errors)) {
                        $payload = ['local' => $local, 'password' => $password];
                        $this->log("createEmail|{$packageId}", serialize($api->redactForLog($payload)), 'input', true);
                        try {
                            $response = $api->createEmailAccount($packageId, $domain, $payload);
                            $this->log("createEmail|{$packageId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.email_created', true);
                        } catch (\RuntimeException $e) {
                            $this->log("createEmail|{$packageId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    } else {
                        $this->Input->setErrors($errors);
                    }
                    break;

                case 'delete_account':
                    $local = trim(strtolower((string) ($post['local'] ?? '')));
                    if ($local !== '') {
                        $this->log("deleteEmail|{$packageId}|{$local}", '', 'input', true);
                        try {
                            $response = $api->deleteEmailAccount($packageId, $domain, $local);
                            $this->log("deleteEmail|{$packageId}|{$local}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.email_deleted', true);
                        } catch (\RuntimeException $e) {
                            $this->log("deleteEmail|{$packageId}|{$local}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    }
                    break;

                case 'change_password':
                    $local    = trim(strtolower((string) ($post['local'] ?? '')));
                    $password = (string) ($post['new_password'] ?? '');
                    $errors   = $this->validateEmailCredentials($local, $password);
                    if (empty($errors)) {
                        $this->log("changeEmailPass|{$packageId}|{$local}", '[REDACTED]', 'input', true);
                        try {
                            $response = $api->changeEmailPassword($packageId, $domain, $local, $password);
                            $this->log("changeEmailPass|{$packageId}|{$local}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.email_password_saved', true);
                        } catch (\RuntimeException $e) {
                            $this->log("changeEmailPass|{$packageId}|{$local}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    } else {
                        $this->Input->setErrors($errors);
                    }
                    break;
            }
        }

        try {
            $accounts = $api->getEmailAccounts($packageId, $domain);
        } catch (\RuntimeException $e) {
            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
            $this->log("getEmail|{$packageId}", $e->getMessage(), 'output', false);
        }

        $view->set('fields',   $fields);
        $view->set('accounts', $accounts);
        $view->set('domain',   $domain);
        $view->set('post',     $post);
        $view->set('notice',   $notice);
        $view->set('apiError', $apiError);
        return $view->fetch();
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function validateEmailCredentials(string $local, string $password): array
    {
        $errors = [];
        if (!preg_match('/^[a-z0-9._+\-]{1,64}$/i', $local)) {
            $errors['email_local'] = ['valid' => Language::_('TwentyiHosting.!error.email_local_valid', true)];
        }
        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
            $errors['email_password'] = ['valid' => Language::_('TwentyiHosting.!error.email_password_valid', true)];
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function renderFtpTab(mixed $package, mixed $service, array $post, bool $isAdmin): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView($isAdmin ? 'tab_ftp' : 'tab_client_ftp', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $notice    = null;
        $apiError  = null;
        $ftpStatus = null;

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        $row = $this->getModuleRow();
        if (!$row) {
            $view->set('apiError', Language::_('TwentyiHosting.!error.module_row_missing', true));
            return $view->fetch();
        }

        $api = $this->getApi($row);

        if (!empty($post['action'])) {
            switch ($post['action']) {
                case 'ftp_lock':
                    $locked = isset($post['locked']) && $post['locked'] === '1';
                    $this->log("ftpLock|{$packageId}", 'locked=' . ($locked ? 'true' : 'false'), 'input', true);
                    try {
                        $response = $api->setFtpLock($packageId, $locked);
                        $this->log("ftpLock|{$packageId}", serialize($response), 'output', true);
                        $notice = Language::_($locked ? 'TwentyiHosting.notice.ftp_lock_on' : 'TwentyiHosting.notice.ftp_lock_off', true);
                    } catch (\RuntimeException $e) {
                        $this->log("ftpLock|{$packageId}", $e->getMessage(), 'output', false);
                        $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                    }
                    break;

                case 'reset_password':
                    $password = (string) ($post['ftp_password'] ?? '');
                    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
                        $this->Input->setErrors(['ftp_password' => ['valid' => Language::_('TwentyiHosting.!error.ftp_password_valid', true)]]);
                    } else {
                        $this->log("ftpReset|{$packageId}", '[REDACTED]', 'input', true);
                        try {
                            $response = $api->resetFtpPassword($packageId, $password);
                            $this->log("ftpReset|{$packageId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.ftp_password_reset', true);
                        } catch (\RuntimeException $e) {
                            $this->log("ftpReset|{$packageId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    }
                    break;
            }
        }

        try {
            $ftpStatus = $api->getFtpStatus($packageId);
        } catch (\RuntimeException $e) {
            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
            $this->log("getFtp|{$packageId}", $e->getMessage(), 'output', false);
        }

        $view->set('fields',    $fields);
        $view->set('ftpStatus', $ftpStatus);
        $view->set('post',      $post);
        $view->set('notice',    $notice);
        $view->set('apiError',  $apiError);
        return $view->fetch();
    }

    /**
     * @param array<string,mixed> $post
     */
    private function renderDomainsTab(mixed $package, mixed $service, array $post, bool $isAdmin): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $view      = $this->makeView($isAdmin ? 'tab_domains' : 'tab_client_domains', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        $notice   = null;
        $apiError = null;
        $domains  = [];

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        $row = $this->getModuleRow();
        if (!$row) {
            $view->set('apiError', Language::_('TwentyiHosting.!error.module_row_missing', true));
            return $view->fetch();
        }

        $api = $this->getApi($row);

        if (!empty($post['action'])) {
            switch ($post['action']) {
                case 'add_domain':
                    $addonDomain = strtolower(trim((string) ($post['addon_domain'] ?? '')));
                    if (!$this->validateDomain($addonDomain)) {
                        $this->Input->setErrors(['addon_domain' => ['valid' => Language::_('TwentyiHosting.!error.addon_domain_valid', true)]]);
                    } else {
                        $this->log("addAddon|{$packageId}", "domain={$addonDomain}", 'input', true);
                        try {
                            $response = $api->addAddonDomain($packageId, $addonDomain);
                            $this->log("addAddon|{$packageId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.domain_added', true);
                        } catch (\RuntimeException $e) {
                            $this->log("addAddon|{$packageId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    }
                    break;

                case 'remove_domain':
                    $addonDomain = strtolower(trim((string) ($post['addon_domain'] ?? '')));
                    if ($addonDomain !== '') {
                        $this->log("removeAddon|{$packageId}", "domain={$addonDomain}", 'input', true);
                        try {
                            $response = $api->removeAddonDomain($packageId, $addonDomain);
                            $this->log("removeAddon|{$packageId}", serialize($response), 'output', true);
                            $notice = Language::_('TwentyiHosting.notice.domain_removed', true);
                        } catch (\RuntimeException $e) {
                            $this->log("removeAddon|{$packageId}", $e->getMessage(), 'output', false);
                            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                        }
                    }
                    break;
            }
        }

        // Retrieve addon domains from package info
        try {
            $packageData = $api->getPackage($packageId);
            $domains     = (array) ($packageData->names ?? []);
        } catch (\RuntimeException $e) {
            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
            $this->log("getPackage|{$packageId}", $e->getMessage(), 'output', false);
        }

        $view->set('fields',   $fields);
        $view->set('domains',  $domains);
        $view->set('post',     $post);
        $view->set('notice',   $notice);
        $view->set('apiError', $apiError);
        return $view->fetch();
    }

    /**
     * @param array<string,mixed> $post
     */
    private function renderNameserversTab(mixed $package, mixed $service, array $post, bool $isAdmin): string
    {
        $fields    = $this->serviceFieldsToVars($service->fields ?? []);
        $packageId = $fields->twentyi_package_id ?? '';
        $domain    = $fields->twentyi_domain ?? '';
        $view      = $this->makeView($isAdmin ? 'tab_nameservers' : 'tab_client_nameservers', 'default', ROOTWEBDIR . implode(DS, ['components', 'modules', 'twentyi_hosting', '']));

        // Nameserver management is only relevant for domains registered via 20i.
        if (($fields->twentyi_domain_action ?? 'use_existing') !== 'register_new') {
            $view->set('not_applicable', true);
            return $view->fetch();
        }

        if ($packageId === '') {
            $view->set('no_package_id', true);
            return $view->fetch();
        }

        $notice      = null;
        $apiError    = null;
        $nameservers = [];

        $row = $this->getModuleRow();
        if (!$row) {
            $view->set('apiError', Language::_('TwentyiHosting.!error.module_row_missing', true));
            return $view->fetch();
        }

        $api = $this->getApi($row);

        if (!empty($post['action']) && $post['action'] === 'update_nameservers') {
            $ns = array_filter(array_map('trim', [
                $post['ns1'] ?? '',
                $post['ns2'] ?? '',
                $post['ns3'] ?? '',
                $post['ns4'] ?? '',
            ]));

            if (count($ns) < 2) {
                $this->Input->setErrors(['ns' => ['valid' => Language::_('TwentyiHosting.!error.ns_valid', true)]]);
            } else {
                $this->log("setNs|{$domain}", implode(',', $ns), 'input', true);
                try {
                    $response = $api->setNameservers($domain, array_values($ns));
                    $this->log("setNs|{$domain}", serialize($response), 'output', true);
                    $notice = Language::_('TwentyiHosting.notice.nameservers_updated', true);
                } catch (\RuntimeException $e) {
                    $this->log("setNs|{$domain}", $e->getMessage(), 'output', false);
                    $apiError = Language::_('TwentyiHosting.!error.api_error', true);
                }
            }
        }

        try {
            $nameservers = $api->getNameservers($domain);
        } catch (\RuntimeException $e) {
            $apiError = Language::_('TwentyiHosting.!error.api_error', true);
            $this->log("getNs|{$domain}", $e->getMessage(), 'output', false);
        }

        $view->set('fields',      $fields);
        $view->set('nameservers', $nameservers);
        $view->set('post',        $post);
        $view->set('notice',      $notice);
        $view->set('apiError',    $apiError);
        return $view->fetch();
    }

    // -------------------------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------------------------

    /**
     * Called by Blesta when the module is uninstalled.
     *
     * Does NOT block uninstallation — any active services are logged as a
     * warning so the admin has a record of what was linked before the module
     * data is removed.
     *
     * Important: 20i hosting accounts are unaffected by uninstallation.
     * They remain active in the 20i dashboard and must be managed manually.
     */
    public function uninstall(int $module_id, bool $last_instance): void
    {
        // Find any services still using this module that are active or suspended.
        // We query directly since the Services model API varies across Blesta versions.
        Loader::loadComponents($this, ['Record']);

        /** @var array<int,object> $services */
        $services = $this->Record
            ->select([
                'services.id',
                'services.status',
                'service_fields.value AS domain',
            ])
            ->from('services')
            ->innerJoin('module_rows', 'module_rows.id', '=', 'services.module_row_id', false)
            ->leftJoin(
                'service_fields',
                "service_fields.service_id = services.id AND service_fields.key = 'twentyi_domain'",
                false,
                false
            )
            ->where('module_rows.module_id', '=', $module_id)
            ->where('services.status', 'in', ['active', 'suspended'])
            ->fetchAll();

        /** @var array<int,object> $packageIds */
        $packageIds = $this->Record
            ->select([
                'services.id AS service_id',
                'service_fields.value AS package_id',
            ])
            ->from('services')
            ->innerJoin('module_rows', 'module_rows.id', '=', 'services.module_row_id', false)
            ->innerJoin(
                'service_fields',
                "service_fields.service_id = services.id AND service_fields.key = 'twentyi_package_id'",
                false,
                false
            )
            ->where('module_rows.module_id', '=', $module_id)
            ->where('services.status', 'in', ['active', 'suspended'])
            ->fetchAll();

        // Build a lookup of service_id → package_id for the log
        $pkgMap = [];
        foreach ($packageIds as $row) {
            $pkgMap[(int) $row->service_id] = $row->package_id;
        }

        $count = count($services);

        if ($count === 0) {
            // Nothing linked — clean uninstall, just note it happened.
            $this->log(
                'uninstall',
                "Module uninstalled cleanly. No active or suspended services were linked.",
                'output',
                true
            );
            return;
        }

        // Build a human-readable summary of every affected service.
        $lines   = [];
        $lines[] = "WARNING: {$count} active/suspended service(s) were still linked to this module at the time of uninstallation.";
        $lines[] = "These hosting accounts remain active in your hosting provider dashboard and must be managed manually.";
        $lines[] = "Record the following details before they are lost:";
        $lines[] = str_repeat('-', 60);

        foreach ($services as $service) {
            $serviceId = (int) $service->id;
            $pkgId     = $pkgMap[$serviceId] ?? '(not recorded)';
            $domain    = $service->domain ?? '(unknown)';
            $status    = $service->status ?? '(unknown)';

            $lines[] = sprintf(
                "  Blesta Service ID: %d | Domain: %s | Hosting Package ID: %s | Status: %s",
                $serviceId,
                $domain,
                $pkgId,
                $status
            );
        }

        $lines[] = str_repeat('-', 60);
        $lines[] = "Action required: log into your hosting provider control panel and";
        $lines[] = "either manage these accounts directly or cancel them if no longer needed.";
        $lines[] = "This warning is also written to: " . __DIR__ . DS . 'uninstall_warning.log';

        $logMessage = implode("\n", $lines);

        // Write to the Blesta module log (Admin → Tools → Logs → Module).
        $this->log('uninstall', $logMessage, 'output', true);

        // Also write to a plain-text file in the module directory so the admin
        // has a persistent copy even after Blesta rotates its module log.
        $logFile = __DIR__ . DS . 'uninstall_warning.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(
            $logFile,
            "[{$timestamp}]\n{$logMessage}\n\n",
            FILE_APPEND | LOCK_EX
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a TwentyIApi instance from a module row's meta.
     */
    private function getApi(mixed $row): TwentyIApi
    {
        $apiKey = $row->meta->api_key ?? '';
        return new TwentyIApi($apiKey);
    }

    /**
     * Convert a service fields array (objects with ->key / ->value) into a
     * plain object for easy property access.
     *
     * @param array<int,object>|object[] $fields
     */
    private function serviceFieldsToVars(array $fields): object
    {
        $vars = new \stdClass();
        foreach ($fields as $field) {
            $key         = $field->key ?? null;
            $value       = $field->value ?? null;
            if ($key !== null) {
                $vars->{$key} = $value;
            }
        }
        return $vars;
    }
}
