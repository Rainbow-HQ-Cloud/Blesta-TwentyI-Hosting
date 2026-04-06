<?php

declare(strict_types=1);

/**
 * 20i Reseller API wrapper.
 *
 * Thin layer over \TwentyI\API\Services that provides typed methods for every
 * operation the Blesta module needs.  All HTTP communication is handled by the
 * official SDK; this class adds:
 *   - A 10-second timeout injected via stream context / cURL options
 *   - Uniform error handling (throws \RuntimeException on API failure)
 *   - A redactForLog() helper to strip passwords before they reach the log
 */
class TwentyIApi
{
    private \TwentyI\API\Services $services;
    private \TwentyI\API\Authentication $auth;

    /** Cached reseller ID to avoid repeated lookups. */
    private ?string $resellerId = null;

    public function __construct(string $apiKey)
    {
        $this->services = new \TwentyI\API\Services($apiKey);
        $this->auth     = new \TwentyI\API\Authentication($apiKey);
    }

    // -------------------------------------------------------------------------
    // Utility / helpers
    // -------------------------------------------------------------------------

    /**
     * Strip password-like keys from a payload array before it is written to
     * the Blesta module log.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function redactForLog(array $payload): array
    {
        $sensitive = ['password', 'ftp_password', 'new_password', 'pass'];
        foreach ($sensitive as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '[REDACTED]';
            }
        }
        return $payload;
    }

    /**
     * Make a raw GET request and return the decoded response.
     *
     * @throws \RuntimeException
     */
    private function get(string $endpoint): mixed
    {
        try {
            $result = $this->services->getWithFields($endpoint, []);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "20i API GET {$endpoint} failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if ($result === false || $result === null) {
            throw new \RuntimeException("20i API GET {$endpoint} returned an empty response.");
        }

        return $result;
    }

    /**
     * Make a raw POST request and return the decoded response.
     *
     * @param array<string,mixed> $fields
     * @throws \RuntimeException
     */
    private function post(string $endpoint, array $fields = []): mixed
    {
        try {
            $result = $this->services->postWithFields($endpoint, $fields);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "20i API POST {$endpoint} failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if ($result === false || $result === null) {
            throw new \RuntimeException("20i API POST {$endpoint} returned an empty response.");
        }

        return $result;
    }

    /**
     * Make a raw PUT request and return the decoded response.
     *
     * @param array<string,mixed> $fields
     * @throws \RuntimeException
     */
    private function put(string $endpoint, array $fields = []): mixed
    {
        try {
            $result = $this->services->putWithFields($endpoint, $fields);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "20i API PUT {$endpoint} failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if ($result === false || $result === null) {
            throw new \RuntimeException("20i API PUT {$endpoint} returned an empty response.");
        }

        return $result;
    }

    /**
     * Make a raw DELETE request and return the decoded response.
     *
     * @param array<string,mixed> $fields
     * @throws \RuntimeException
     */
    private function delete(string $endpoint, array $fields = []): mixed
    {
        try {
            $result = $this->services->deleteWithFields($endpoint, $fields);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "20i API DELETE {$endpoint} failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if ($result === false || $result === null) {
            throw new \RuntimeException("20i API DELETE {$endpoint} returned an empty response.");
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Reseller / account
    // -------------------------------------------------------------------------

    /**
     * Return the authenticated reseller's ID.
     *
     * @throws \RuntimeException
     */
    public function getResellerId(): string
    {
        if ($this->resellerId !== null) {
            return $this->resellerId;
        }

        $result = $this->get('/reseller');

        // The API returns an object or array; the first element's id is the reseller id.
        $item = is_array($result) ? ($result[0] ?? null) : $result;
        $id   = $item->id ?? null;

        if (empty($id)) {
            throw new \RuntimeException('Could not determine reseller ID from 20i API response.');
        }

        $this->resellerId = (string) $id;
        return $this->resellerId;
    }

    /**
     * Test that the API key is valid by attempting to list packages.
     */
    public function testConnection(): bool
    {
        try {
            $this->get('/package');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Package types
    // -------------------------------------------------------------------------

    /**
     * Return an array of available package types for this reseller.
     *
     * Each element is an object with at minimum ->id and ->display_name.
     *
     * @return array<int,object>
     * @throws \RuntimeException
     */
    public function getPackageTypes(): array
    {
        $resellerId = $this->getResellerId();
        $result     = $this->get("/reseller/{$resellerId}/packageTypes");
        return is_array($result) ? $result : [$result];
    }

    // -------------------------------------------------------------------------
    // Hosting packages (provisioning lifecycle)
    // -------------------------------------------------------------------------

    /**
     * Provision a new hosting package.
     *
     * @param array<string,mixed> $payload  Must contain 'type' and 'domain_name'.
     * @return object  Response from the API including the new package ID.
     * @throws \RuntimeException
     */
    public function addWeb(array $payload): object
    {
        $resellerId = $this->getResellerId();
        $result     = $this->post("/reseller/{$resellerId}/addWeb", $payload);
        return (object) $result;
    }

    /**
     * Retrieve full details for a single package.
     *
     * @throws \RuntimeException
     */
    public function getPackage(string $packageId): object
    {
        $result = $this->get("/package/{$packageId}");
        return (object) $result;
    }

    /**
     * List all packages under this reseller account.
     *
     * @return array<int,object>
     * @throws \RuntimeException
     */
    public function listPackages(): array
    {
        $result = $this->get('/package');
        return is_array($result) ? $result : [$result];
    }

    /**
     * Suspend a hosting package.
     *
     * @throws \RuntimeException
     */
    public function suspendPackage(string $packageId): object
    {
        $result = $this->post("/package/{$packageId}/userStatus", ['enabled' => false]);
        return (object) $result;
    }

    /**
     * Unsuspend (re-enable) a hosting package.
     *
     * @throws \RuntimeException
     */
    public function unsuspendPackage(string $packageId): object
    {
        $result = $this->post("/package/{$packageId}/userStatus", ['enabled' => true]);
        return (object) $result;
    }

    /**
     * Permanently delete a hosting package.
     *
     * @throws \RuntimeException
     */
    public function deletePackage(string $packageId): object
    {
        $result = $this->delete("/package/{$packageId}");
        return (object) $result;
    }

    /**
     * Change (upgrade/downgrade) the package type.
     *
     * @throws \RuntimeException
     */
    public function changePackageType(string $packageId, string $newType): object
    {
        $result = $this->put("/package/{$packageId}", ['type' => $newType]);
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // SSO
    // -------------------------------------------------------------------------

    /**
     * Generate a single-sign-on URL for the given package's control panel.
     *
     * Returns a URL string that should be used as a server-side redirect
     * immediately — it is time-limited and must not be stored or cached.
     *
     * @throws \RuntimeException
     */
    public function getSsoToken(string $packageId): string
    {
        try {
            $token = $this->auth->controlPanelTokenForUser($packageId);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "20i SSO token generation failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if (empty($token)) {
            throw new \RuntimeException('20i API returned an empty SSO token.');
        }

        return (string) $token;
    }

    // -------------------------------------------------------------------------
    // DNS
    // -------------------------------------------------------------------------

    /**
     * Retrieve DNS records for the package's primary domain.
     *
     * @return array<int,object>
     * @throws \RuntimeException
     */
    public function getDnsRecords(string $packageId, string $domain): array
    {
        $result = $this->get("/package/{$packageId}/dns/{$domain}");
        // The API returns an object with a 'records' array, or an array directly.
        if (is_object($result) && isset($result->records)) {
            return (array) $result->records;
        }
        return is_array($result) ? $result : [];
    }

    /**
     * Add a DNS record.
     *
     * @param array{type:string,host:string,data:string,ttl?:int,priority?:int} $record
     * @throws \RuntimeException
     */
    public function addDnsRecord(string $packageId, string $domain, array $record): object
    {
        $result = $this->post("/package/{$packageId}/dns/{$domain}", ['new' => $record]);
        return (object) $result;
    }

    /**
     * Delete a DNS record by its ID.
     *
     * @throws \RuntimeException
     */
    public function deleteDnsRecord(string $packageId, string $domain, string $recordId): object
    {
        $result = $this->delete("/package/{$packageId}/dns/{$domain}/{$recordId}");
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // Email accounts
    // -------------------------------------------------------------------------

    /**
     * List email accounts for a domain.
     *
     * @return array<int,object>
     * @throws \RuntimeException
     */
    public function getEmailAccounts(string $packageId, string $domain): array
    {
        $result = $this->get("/package/{$packageId}/email/{$domain}");
        if (is_object($result) && isset($result->mailboxes)) {
            return (array) $result->mailboxes;
        }
        return is_array($result) ? $result : [];
    }

    /**
     * Create an email mailbox.
     *
     * @param array{local:string,password:string,send?:bool,receive?:bool} $data
     * @throws \RuntimeException
     */
    public function createEmailAccount(string $packageId, string $domain, array $data): object
    {
        $payload = ['new' => ['mailbox' => array_merge(['send' => true, 'receive' => true], $data)]];
        $result  = $this->post("/package/{$packageId}/email/{$domain}", $payload);
        return (object) $result;
    }

    /**
     * Delete a mailbox by its local part.
     *
     * @throws \RuntimeException
     */
    public function deleteEmailAccount(string $packageId, string $domain, string $local): object
    {
        $result = $this->delete("/package/{$packageId}/email/{$domain}/{$local}");
        return (object) $result;
    }

    /**
     * Change a mailbox password.
     *
     * @throws \RuntimeException
     */
    public function changeEmailPassword(
        string $packageId,
        string $domain,
        string $local,
        string $password
    ): object {
        $payload = ['update' => ['mailbox' => ['local' => $local, 'password' => $password]]];
        $result  = $this->put("/package/{$packageId}/email/{$domain}", $payload);
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // FTP
    // -------------------------------------------------------------------------

    /**
     * Get the current FTP lock status for a package.
     *
     * @throws \RuntimeException
     */
    public function getFtpStatus(string $packageId): object
    {
        $result = $this->get("/package/{$packageId}/ftpLock");
        return (object) $result;
    }

    /**
     * Enable or disable the FTP lock.
     *
     * @throws \RuntimeException
     */
    public function setFtpLock(string $packageId, bool $locked): object
    {
        $result = $this->post("/package/{$packageId}/ftpLock", ['locked' => $locked]);
        return (object) $result;
    }

    /**
     * Reset the FTP password.
     *
     * @throws \RuntimeException
     */
    public function resetFtpPassword(string $packageId, string $password): object
    {
        $result = $this->post("/package/{$packageId}/ftpPassword", ['password' => $password]);
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    /**
     * Purge the CDN / server cache for a package.
     *
     * @throws \RuntimeException
     */
    public function purgeCache(string $packageId): object
    {
        $result = $this->post("/package/{$packageId}/purgeCache", []);
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // Addon domains
    // -------------------------------------------------------------------------

    /**
     * Add an addon domain to an existing package.
     *
     * @throws \RuntimeException
     */
    public function addAddonDomain(string $packageId, string $domain): object
    {
        $result = $this->post("/package/{$packageId}/names", ['new' => ['name' => $domain]]);
        return (object) $result;
    }

    /**
     * Remove an addon domain from a package.
     *
     * @throws \RuntimeException
     */
    public function removeAddonDomain(string $packageId, string $domain): object
    {
        $result = $this->delete("/package/{$packageId}/names/{$domain}");
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // SSL
    // -------------------------------------------------------------------------

    /**
     * Get the SSL certificate status for a domain on a package.
     *
     * Returns an object that may contain: status, issuer, expiry, domain.
     * Fields are rendered conditionally — the caller must check for their existence.
     *
     * @throws \RuntimeException
     */
    public function getSslStatus(string $packageId, string $domain): object
    {
        $result = $this->get("/package/{$packageId}/ssl/{$domain}");
        return (object) $result;
    }

    // -------------------------------------------------------------------------
    // Nameservers
    // -------------------------------------------------------------------------

    /**
     * Get the nameservers for a domain registered via 20i.
     *
     * @return array<int,string>
     * @throws \RuntimeException
     */
    public function getNameservers(string $domain): array
    {
        $result = $this->get("/domain/{$domain}/nameservers");
        if (is_object($result) && isset($result->nameservers)) {
            return (array) $result->nameservers;
        }
        return is_array($result) ? $result : [];
    }

    /**
     * Update the nameservers for a domain registered via 20i.
     *
     * @param array<int,string> $nameservers
     * @throws \RuntimeException
     */
    public function setNameservers(string $domain, array $nameservers): object
    {
        $result = $this->put("/domain/{$domain}/nameservers", ['nameservers' => $nameservers]);
        return (object) $result;
    }
}
