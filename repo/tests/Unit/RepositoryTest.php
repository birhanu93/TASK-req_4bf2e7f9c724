<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Assessment;
use App\Entity\AssessmentTemplate;
use App\Entity\AuditLog;
use App\Entity\Booking;
use App\Entity\Certificate;
use App\Entity\Device;
use App\Entity\GuardianLink;
use App\Entity\ModerationItem;
use App\Entity\Rank;
use App\Entity\TrainingSession;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherClaim;
use App\Repository\AssessmentRepository;
use App\Repository\AssessmentTemplateRepository;
use App\Repository\AuditLogRepository;
use App\Repository\BookingRepository;
use App\Repository\CertificateRepository;
use App\Repository\DeviceRepository;
use App\Repository\GuardianLinkRepository;
use App\Repository\ModerationRepository;
use App\Repository\RankRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherClaimRepository;
use App\Repository\VoucherRepository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    public function testUserRepository(): void
    {
        $repo = new UserRepository();
        $u = new User('u1', 'alice', 'h', ['trainee']);
        $repo->save($u);
        self::assertSame($u, $repo->find('u1'));
        self::assertNull($repo->find('missing'));
        self::assertSame($u, $repo->findByUsername('alice'));
        self::assertNull($repo->findByUsername('bob'));
        self::assertCount(1, $repo->findAll());
        $repo->delete('u1');
        self::assertNull($repo->find('u1'));
    }

    public function testSessionRepository(): void
    {
        $repo = new SessionRepository();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $s = new TrainingSession('s1', 'sup1', 't', $start, $start->modify('+1 hour'), 5);
        $s2 = new TrainingSession('s2', 'sup2', 't', $start, $start->modify('+1 hour'), 5);
        $repo->save($s);
        $repo->save($s2);
        self::assertCount(1, $repo->findBySupervisor('sup1'));
    }

    public function testBookingRepository(): void
    {
        $repo = new BookingRepository();
        $b = new Booking('b1', 's1', 't1', new \DateTimeImmutable());
        $b2 = new Booking('b2', 's1', 't2', new \DateTimeImmutable());
        $b2->cancel('c');
        $repo->save($b);
        $repo->save($b2);
        self::assertCount(1, $repo->findActiveBySession('s1'));
        self::assertCount(1, $repo->findByTrainee('t1'));
        self::assertCount(1, $repo->findByTrainee('t2'));
        self::assertCount(2, $repo->findAll());
    }

    public function testAssessmentRepos(): void
    {
        $templates = new AssessmentTemplateRepository();
        $tpl = new AssessmentTemplate('t1', 'n', AssessmentTemplate::MODE_REP, 10);
        $templates->save($tpl);
        self::assertSame($tpl, $templates->find('t1'));

        $repo = new AssessmentRepository();
        $a = new Assessment('a1', 't1', 'u1', 'sup', 5, 10, new \DateTimeImmutable());
        $repo->save($a);
        self::assertCount(1, $repo->findByTrainee('u1'));
        self::assertCount(0, $repo->findByTrainee('u2'));
    }

    public function testRankRepository(): void
    {
        $repo = new RankRepository();
        $repo->save(new Rank('r1', 'B', 0, 0, 2));
        $repo->save(new Rank('r2', 'A', 0, 0, 1));
        $ordered = $repo->findAllOrdered();
        self::assertSame('r2', $ordered[0]->getId());
        self::assertSame('r1', $ordered[1]->getId());
    }

    public function testVoucherRepo(): void
    {
        $repo = new VoucherRepository();
        $v = new Voucher('v1', 'C', 100, 500, 1, new \DateTimeImmutable('+1 day'));
        $repo->save($v);
        self::assertSame($v, $repo->findByCode('C'));
        self::assertNull($repo->findByCode('X'));
    }

    public function testVoucherClaimRepo(): void
    {
        $repo = new VoucherClaimRepository();
        $c = new VoucherClaim('c1', 'v1', 'u1', 'idem', new \DateTimeImmutable());
        $repo->save($c);
        self::assertSame($c, $repo->findByIdempotencyKey('idem'));
        self::assertNull($repo->findByIdempotencyKey('other'));
        self::assertCount(1, $repo->findByVoucher('v1'));
    }

    public function testGuardianLinkRepo(): void
    {
        $repo = new GuardianLinkRepository();
        $l = new GuardianLink('l1', 'g1', 'c1', new \DateTimeImmutable());
        $repo->save($l);
        self::assertCount(1, $repo->findByGuardian('g1'));
        self::assertSame($l, $repo->findLink('g1', 'c1'));
        self::assertNull($repo->findLink('g1', 'c2'));
    }

    public function testDeviceRepo(): void
    {
        $repo = new DeviceRepository();
        $d = new Device('d1', 'u1', 'name', 'fp', new \DateTimeImmutable());
        $repo->save($d);
        self::assertCount(1, $repo->findByUser('u1'));
        self::assertSame($d, $repo->findByFingerprint('u1', 'fp'));
        self::assertNull($repo->findByFingerprint('u1', 'other'));
    }

    public function testModerationRepo(): void
    {
        $repo = new ModerationRepository();
        $m = new ModerationItem('m1', 'a', 'note', 'c', 'chk', new \DateTimeImmutable());
        $repo->save($m);
        self::assertCount(1, $repo->findPending());
        self::assertSame($m, $repo->findByChecksum('chk'));
        self::assertNull($repo->findByChecksum('other'));
        $m->approve('r', 50);
        $repo->save($m);
        self::assertCount(0, $repo->findPending());
    }

    public function testAuditRepo(): void
    {
        $repo = new AuditLogRepository();
        $log = new AuditLog('l1', 'a', 'act', 'T', 'id', new \DateTimeImmutable());
        $repo->save($log);
        self::assertCount(1, $repo->findByEntity('T', 'id'));
        self::assertCount(0, $repo->findByEntity('X', 'id'));
    }

    public function testCertificateRepo(): void
    {
        $repo = new CertificateRepository();
        $c = new Certificate('c1', 'u1', 'r1', 'CODE', '/p', new \DateTimeImmutable());
        $repo->save($c);
        self::assertSame($c, $repo->findByVerificationCode('CODE'));
        self::assertNull($repo->findByVerificationCode('NO'));
    }
}
