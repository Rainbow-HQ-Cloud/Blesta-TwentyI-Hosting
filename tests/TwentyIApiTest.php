<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TwentyIApi.
 *
 * \TwentyI\API\Services is mocked so these tests run without a real API key
 * or network connection.
 */
class TwentyIApiTest extends TestCase
{
    /** @var \TwentyI\API\Services&MockObject */
    private MockObject $servicesMock;

    private TwentyIApi $api;

    protected function setUp(): void
    {
        // Bootstrap the API class (no autoloader available in this test context)
        if (!class_exists('TwentyIApi')) {
            require_once __DIR__ . '/../apis/TwentyIApi.php';
        }

        // Create a partial mock of \TwentyI\API\Services so we can intercept
        // getWithFields / postWithFields / putWithFields / deleteWithFields.
        $this->servicesMock = $this->createMock(\TwentyI\API\Services::class);

        // Inject the mock via reflection (the real constructor takes a string key)
        $this->api = new TwentyIApi('test-api-key');
        $ref = new ReflectionClass($this->api);

        $servicesProp = $ref->getProperty('services');
        $servicesProp->setAccessible(true);
        $servicesProp->setValue($this->api, $this->servicesMock);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function testRedactForLog_removesPasswordFields(): void
    {
        $payload = ['local' => 'user', 'password' => 'secret123', 'other' => 'value'];
        $result  = $this->api->redactForLog($payload);

        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('user', $result['local']);
        $this->assertSame('value', $result['other']);
    }

    public function testRedactForLog_handlesMultipleSensitiveKeys(): void
    {
        $payload = ['ftp_password' => 'ftp123', 'new_password' => 'new456'];
        $result  = $this->api->redactForLog($payload);

        $this->assertSame('[REDACTED]', $result['ftp_password']);
        $this->assertSame('[REDACTED]', $result['new_password']);
    }

    public function testRedactForLog_leavesMissingKeyUntouched(): void
    {
        $payload = ['local' => 'user@example.com'];
        $result  = $this->api->redactForLog($payload);

        $this->assertArrayNotHasKey('password', $result);
    }

    // -------------------------------------------------------------------------
    // Connection test
    // -------------------------------------------------------------------------

    public function testTestConnection_returnsTrueOnSuccess(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn([]);

        $this->assertTrue($this->api->testConnection());
    }

    public function testTestConnection_returnsFalseOnException(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->assertFalse($this->api->testConnection());
    }

    // -------------------------------------------------------------------------
    // Reseller ID
    // -------------------------------------------------------------------------

    public function testGetResellerId_returnsIdFromResponse(): void
    {
        $response = [(object) ['id' => '12345']];
        $this->servicesMock
            ->method('getWithFields')
            ->with('/reseller', [])
            ->willReturn($response);

        $this->assertSame('12345', $this->api->getResellerId());
    }

    public function testGetResellerId_cachesResult(): void
    {
        $response = [(object) ['id' => '99']];
        $this->servicesMock
            ->expects($this->once())  // Should only call API once
            ->method('getWithFields')
            ->willReturn($response);

        $this->api->getResellerId();
        $this->api->getResellerId(); // Second call uses cache
    }

    public function testGetResellerId_throwsWhenIdMissing(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn([(object) []]);

        $this->expectException(\RuntimeException::class);
        $this->api->getResellerId();
    }

    // -------------------------------------------------------------------------
    // Package provisioning
    // -------------------------------------------------------------------------

    public function testAddWeb_returnsPackageObject(): void
    {
        // Mock reseller ID lookup
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn([(object) ['id' => '42']]);

        $expectedResponse = (object) ['result' => ['pkg-001']];
        $this->servicesMock
            ->method('postWithFields')
            ->willReturn($expectedResponse);

        $result = $this->api->addWeb(['type' => '811', 'domain_name' => 'example.com']);

        $this->assertIsObject($result);
    }

    public function testAddWeb_throwsOnEmptyResponse(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn([(object) ['id' => '42']]);

        $this->servicesMock
            ->method('postWithFields')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->api->addWeb(['type' => '811', 'domain_name' => 'example.com']);
    }

    // -------------------------------------------------------------------------
    // Suspend / unsuspend / delete
    // -------------------------------------------------------------------------

    public function testSuspendPackage_sendsEnabledFalse(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-123/userStatus', ['enabled' => false])
            ->willReturn((object) ['result' => true]);

        $this->api->suspendPackage('pkg-123');
    }

    public function testUnsuspendPackage_sendsEnabledTrue(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-123/userStatus', ['enabled' => true])
            ->willReturn((object) ['result' => true]);

        $this->api->unsuspendPackage('pkg-123');
    }

    public function testDeletePackage_callsDeleteEndpoint(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('deleteWithFields')
            ->with('/package/pkg-123', [])
            ->willReturn((object) ['result' => true]);

        $this->api->deletePackage('pkg-123');
    }

    // -------------------------------------------------------------------------
    // DNS
    // -------------------------------------------------------------------------

    public function testGetDnsRecords_returnsRecordsArray(): void
    {
        $mockResponse = (object) ['records' => [(object) ['type' => 'A', 'host' => '@', 'data' => '1.2.3.4']]];
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn($mockResponse);

        $records = $this->api->getDnsRecords('pkg-001', 'example.com');
        $this->assertCount(1, $records);
        $this->assertSame('A', $records[0]->type);
    }

    public function testAddDnsRecord_wrapsRecordInNewKey(): void
    {
        $record = ['type' => 'A', 'host' => '@', 'data' => '1.2.3.4', 'ttl' => 3600];
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/dns/example.com', ['new' => $record])
            ->willReturn((object) ['result' => true]);

        $this->api->addDnsRecord('pkg-001', 'example.com', $record);
    }

    public function testDeleteDnsRecord_callsCorrectEndpoint(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('deleteWithFields')
            ->with('/package/pkg-001/dns/example.com/rec-99', [])
            ->willReturn((object) ['result' => true]);

        $this->api->deleteDnsRecord('pkg-001', 'example.com', 'rec-99');
    }

    // -------------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------------

    public function testCreateEmailAccount_wrapsPayloadCorrectly(): void
    {
        $data = ['local' => 'info', 'password' => 'Secure123'];
        $expectedPayload = ['new' => ['mailbox' => ['send' => true, 'receive' => true, 'local' => 'info', 'password' => 'Secure123']]];

        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/email/example.com', $expectedPayload)
            ->willReturn((object) ['result' => true]);

        $this->api->createEmailAccount('pkg-001', 'example.com', $data);
    }

    public function testChangeEmailPassword_usesUpdateKey(): void
    {
        $expectedPayload = ['update' => ['mailbox' => ['local' => 'info', 'password' => 'NewPass1']]];

        $this->servicesMock
            ->expects($this->once())
            ->method('putWithFields')
            ->with('/package/pkg-001/email/example.com', $expectedPayload)
            ->willReturn((object) ['result' => true]);

        $this->api->changeEmailPassword('pkg-001', 'example.com', 'info', 'NewPass1');
    }

    // -------------------------------------------------------------------------
    // FTP
    // -------------------------------------------------------------------------

    public function testSetFtpLock_sendsLockedFlag(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/ftpLock', ['locked' => true])
            ->willReturn((object) ['result' => true]);

        $this->api->setFtpLock('pkg-001', true);
    }

    public function testResetFtpPassword_callsCorrectEndpoint(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/ftpPassword', ['password' => 'FtpPass1'])
            ->willReturn((object) ['result' => true]);

        $this->api->resetFtpPassword('pkg-001', 'FtpPass1');
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    public function testPurgeCache_callsCorrectEndpoint(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/purgeCache', [])
            ->willReturn((object) ['result' => true]);

        $this->api->purgeCache('pkg-001');
    }

    // -------------------------------------------------------------------------
    // Addon domains
    // -------------------------------------------------------------------------

    public function testAddAddonDomain_wrapsInNewKey(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('postWithFields')
            ->with('/package/pkg-001/names', ['new' => ['name' => 'extra.com']])
            ->willReturn((object) ['result' => true]);

        $this->api->addAddonDomain('pkg-001', 'extra.com');
    }

    public function testRemoveAddonDomain_callsDeleteEndpoint(): void
    {
        $this->servicesMock
            ->expects($this->once())
            ->method('deleteWithFields')
            ->with('/package/pkg-001/names/extra.com', [])
            ->willReturn((object) ['result' => true]);

        $this->api->removeAddonDomain('pkg-001', 'extra.com');
    }

    // -------------------------------------------------------------------------
    // Nameservers
    // -------------------------------------------------------------------------

    public function testGetNameservers_returnsArray(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn((object) ['nameservers' => ['ns1.example.com', 'ns2.example.com']]);

        $ns = $this->api->getNameservers('example.com');
        $this->assertCount(2, $ns);
        $this->assertSame('ns1.example.com', $ns[0]);
    }

    public function testSetNameservers_sendsPutRequest(): void
    {
        $ns = ['ns1.example.com', 'ns2.example.com'];
        $this->servicesMock
            ->expects($this->once())
            ->method('putWithFields')
            ->with('/domain/example.com/nameservers', ['nameservers' => $ns])
            ->willReturn((object) ['result' => true]);

        $this->api->setNameservers('example.com', $ns);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function testGet_throwsRuntimeExceptionOnException(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willThrowException(new \Exception('Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');
        $this->api->getPackage('pkg-001');
    }

    public function testGet_throwsOnNullResponse(): void
    {
        $this->servicesMock
            ->method('getWithFields')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->api->getPackage('pkg-001');
    }
}
