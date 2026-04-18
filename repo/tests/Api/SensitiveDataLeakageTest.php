<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Response-body hygiene: API JSON must never leak secrets, and endpoints
 * that *do* legitimately echo sensitive-ish identifiers (device
 * fingerprints are a good example — guardians are expected to see them
 * so they can recognise their child's phone) must do so in the clearly
 * documented, least-surprising shape.
 *
 * The shopping list here:
 *  - No password / password_hash / passwordHash in any response.
 *  - Device session tokens stay server-side; only the guardian-visible
 *    fingerprint is echoed back.
 *  - Profile reads don't leak the KEK/DEK envelope material.
 *  - Admin booking list includes the auditable override metadata but
 *    doesn't bleed into unrelated entities.
 */
final class SensitiveDataLeakageTest extends ApiTestCase
{
    private const SECRETS = [
        'passwordHash',
        'password_hash',
        '"password"',
        'kekBytes',
        'kek_bytes',
        'encryptedDek',
        'encrypted_dek',
    ];

    private function assertNoSecrets(array|string|null $body, string $context): void
    {
        $raw = is_string($body) ? $body : (string) json_encode($body);
        foreach (self::SECRETS as $needle) {
            self::assertStringNotContainsString($needle, $raw, "{$context} leaked '{$needle}'");
        }
    }

    public function testAuthResponsesDoNotLeakPasswordHash(): void
    {
        $admin = $this->seedAdmin();
        $login = $this->call('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'admin-pass-1']);
        self::assertSame(200, $login->getStatus());
        $this->assertNoSecrets($login->getBody(), 'POST /api/auth/login');

        $me = $this->call('GET', '/api/auth/me', [], $admin);
        self::assertSame(200, $me->getStatus());
        $this->assertNoSecrets($me->getBody(), 'GET /api/auth/me');

        $select = $this->call('POST', '/api/auth/select-role', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
            'role' => Roles::ADMIN,
        ]);
        self::assertSame(200, $select->getStatus());
        $this->assertNoSecrets($select->getBody(), 'POST /api/auth/select-role');
    }

    public function testProfileReadDoesNotLeakEncryptionEnvelope(): void
    {
        $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $aliceToken = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);

        $this->call('PUT', '/api/profile', ['profile' => ['favouriteColour' => 'blue']], $aliceToken);
        $get = $this->call('GET', '/api/profile', [], $aliceToken);
        self::assertSame(200, $get->getStatus());
        $this->assertNoSecrets($get->getBody(), 'GET /api/profile');
        // The plaintext profile fields still come back through decryption.
        self::assertSame('blue', $get->getBody()['profile']['favouriteColour']);
    }

    public function testDeviceListReturnsFingerprintButNotSessionToken(): void
    {
        // Fingerprint exposure is explicit contract — guardians need it to
        // recognise the device on the list. The session token never
        // appears in any serialised shape.
        $admin = $this->seedAdmin();
        $this->createUser('guard', 'pw-12345', [Roles::GUARDIAN]);
        $this->createUser('child', 'pw-12345', [Roles::TRAINEE]);
        $guardToken = $this->loginAs('guard', 'pw-12345', Roles::GUARDIAN);
        $childId = $this->kernel->users->findByUsername('child')->getId();
        $this->call('POST', '/api/guardians/links', [
            'guardianId' => $this->kernel->users->findByUsername('guard')->getId(),
            'childId' => $childId,
        ], $admin);

        $fp = str_repeat('d', 40);
        $approve = $this->call('POST', '/api/guardians/devices', [
            'childId' => $childId,
            'deviceName' => 'Phone',
            'fingerprint' => $fp,
        ], $guardToken);
        $body = $approve->getBody();
        self::assertSame($fp, $body['fingerprint'], 'fingerprint must round-trip to the guardian view');
        self::assertArrayNotHasKey('sessionToken', $body, 'session token must never leave the server');
        self::assertArrayNotHasKey('session_token', $body);
        $raw = (string) json_encode($body);
        // The token generated inside GuardianService is 32 hex chars; ensure
        // no 32-char hex substring tagged as "sessionToken" leaks out.
        self::assertDoesNotMatchRegularExpression('/session[_A-Za-z]*token/i', $raw);

        $list = $this->call('GET', "/api/guardians/children/{$childId}/devices", [], $guardToken);
        foreach ($list->getBody()['devices'] as $device) {
            self::assertArrayHasKey('fingerprint', $device);
            self::assertArrayNotHasKey('sessionToken', $device);
        }
    }

    public function testAuditHistoryExcludesPasswordEntries(): void
    {
        $admin = $this->seedAdmin();
        $adminId = $this->kernel->users->findByUsername('admin')->getId();
        $this->call('POST', '/api/auth/change-password', [
            'oldPassword' => 'admin-pass-1',
            'newPassword' => 'admin-pass-2',
        ], $admin);

        // The audit row for password change should record the event, not
        // the password itself.
        $historyCalls = $this->call('GET', "/api/admin/audit/user/{$adminId}", [], $admin);
        $this->assertNoSecrets($historyCalls->getBody(), 'admin audit history');
        $json = (string) json_encode($historyCalls->getBody());
        self::assertStringNotContainsString('admin-pass-1', $json);
        self::assertStringNotContainsString('admin-pass-2', $json);
    }

    public function testBookingListResponseShapeDoesNotLeakOtherEntities(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('t', 'pw-12345', [Roles::TRAINEE]);
        $t = $this->loginAs('t', 'pw-12345', Roles::TRAINEE);

        $sessRes = $this->call('POST', '/api/sessions', [
            'title' => 's',
            'startsAt' => '2026-06-01T09:00:00+00:00',
            'endsAt' => '2026-06-01T10:00:00+00:00',
            'capacity' => 2,
        ], $sup);
        $this->call('POST', '/api/bookings', ['sessionId' => $sessRes->getBody()['id']], $t);

        $res = $this->call('GET', '/api/admin/bookings', [], $admin);
        self::assertSame(200, $res->getStatus());
        $this->assertNoSecrets($res->getBody(), 'GET /api/admin/bookings');
        // Booking rows must include traineeId but not trainee username/pw.
        foreach ($res->getBody()['bookings'] as $b) {
            self::assertArrayHasKey('traineeId', $b);
            self::assertArrayNotHasKey('username', $b);
            self::assertArrayNotHasKey('passwordHash', $b);
        }
    }
}
