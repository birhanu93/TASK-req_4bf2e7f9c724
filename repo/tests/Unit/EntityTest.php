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
use PHPUnit\Framework\TestCase;

final class EntityTest extends TestCase
{
    public function testUserRolesLifecycle(): void
    {
        $user = new User('u1', 'alice', 'hash', ['trainee', 'trainee', 'guardian']);
        self::assertSame('u1', $user->getId());
        self::assertSame('alice', $user->getUsername());
        self::assertSame('hash', $user->getPasswordHash());
        self::assertSame(['trainee', 'guardian'], $user->getRoles());
        self::assertTrue($user->hasRole('guardian'));
        self::assertTrue($user->isActive());
        $user->addRole('admin');
        $user->addRole('admin');
        self::assertTrue($user->hasRole('admin'));
        $user->removeRole('trainee');
        self::assertFalse($user->hasRole('trainee'));
        $user->setPasswordHash('new');
        self::assertSame('new', $user->getPasswordHash());
        $user->deactivate();
        self::assertFalse($user->isActive());
        $user->activate();
        self::assertTrue($user->isActive());
        self::assertNull($user->getEncryptedProfile());
        $user->setEncryptedProfile('blob');
        self::assertSame('blob', $user->getEncryptedProfile());
    }

    public function testTrainingSession(): void
    {
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $end = $start->modify('+1 hour');
        $session = new TrainingSession('s1', 'sup1', 'Lift', $start, $end, 5);
        self::assertSame('s1', $session->getId());
        self::assertSame('sup1', $session->getSupervisorId());
        self::assertSame('Lift', $session->getTitle());
        self::assertSame($start, $session->getStartsAt());
        self::assertSame($end, $session->getEndsAt());
        self::assertSame(5, $session->getCapacity());
        self::assertSame(10, $session->getBufferMinutes());
        self::assertTrue($session->isOpen());
        self::assertSame('open', $session->getStatus());
        $session->close();
        self::assertFalse($session->isOpen());
        self::assertSame('closed', $session->getStatus());
    }

    public function testBooking(): void
    {
        $b = new Booking('b1', 's1', 't1', new \DateTimeImmutable('2026-04-18T10:00:00+00:00'));
        self::assertSame('b1', $b->getId());
        self::assertSame('s1', $b->getSessionId());
        self::assertSame('t1', $b->getTraineeId());
        self::assertSame('reserved', $b->getStatus());
        self::assertTrue($b->isActive());
        self::assertNull($b->getCancellationReason());
        self::assertNull($b->getOverrideActorId());
        self::assertNotNull($b->getCreatedAt());
        $b->confirm();
        self::assertSame('confirmed', $b->getStatus());
        self::assertTrue($b->isActive());
        $b->cancel('reason', 'admin1');
        self::assertSame('cancelled', $b->getStatus());
        self::assertSame('reason', $b->getCancellationReason());
        self::assertSame('admin1', $b->getOverrideActorId());
        self::assertFalse($b->isActive());
        $b2 = new Booking('b2', 's1', 't2', new \DateTimeImmutable('now'));
        $b2->expire();
        self::assertSame('expired', $b2->getStatus());
    }

    public function testAssessmentTemplateValidations(): void
    {
        $t = new AssessmentTemplate('t1', 'Push', AssessmentTemplate::MODE_REP, 20);
        self::assertSame('t1', $t->getId());
        self::assertSame('Push', $t->getName());
        self::assertSame('rep', $t->getMode());
        self::assertSame(20, $t->getTargetReps());
        self::assertSame(0, $t->getTargetSeconds());
        new AssessmentTemplate('t2', 'Run', AssessmentTemplate::MODE_TIME, 0, 60);
        new AssessmentTemplate('t3', 'Combo', AssessmentTemplate::MODE_COMBINED, 5, 30);

        $this->expectException(\InvalidArgumentException::class);
        new AssessmentTemplate('x', 'Bad', 'garbage');
    }

    public function testAssessmentTemplateRepRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AssessmentTemplate('x', 'Bad', AssessmentTemplate::MODE_REP, 0);
    }

    public function testAssessmentTemplateTimeRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AssessmentTemplate('x', 'Bad', AssessmentTemplate::MODE_TIME, 0, 0);
    }

    public function testAssessmentTemplateCombinedRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AssessmentTemplate('x', 'Bad', AssessmentTemplate::MODE_COMBINED, 0, 5);
    }

    public function testAssessment(): void
    {
        $at = new \DateTimeImmutable('2026-04-18T12:00:00+00:00');
        $a = new Assessment('a1', 't1', 'u1', 'sup', 10, 30, $at);
        self::assertSame('a1', $a->getId());
        self::assertSame('t1', $a->getTemplateId());
        self::assertSame('u1', $a->getTraineeId());
        self::assertSame('sup', $a->getSupervisorId());
        self::assertSame(10, $a->getReps());
        self::assertSame(30, $a->getSeconds());
        self::assertSame($at, $a->getRecordedAt());
        self::assertNull($a->getRankAchieved());
        $a->setRankAchieved('r1');
        self::assertSame('r1', $a->getRankAchieved());
    }

    public function testRank(): void
    {
        $r = new Rank('r1', 'Gold', 100, 60, 3);
        self::assertSame('r1', $r->getId());
        self::assertSame('Gold', $r->getName());
        self::assertSame(100, $r->getMinReps());
        self::assertSame(60, $r->getMinSeconds());
        self::assertSame(3, $r->getOrder());
    }

    public function testVoucherLifecycle(): void
    {
        $exp = new \DateTimeImmutable('2026-12-31T23:59:59+00:00');
        $v = new Voucher('v1', 'SAVE25', 2500, 15000, 2, $exp);
        self::assertSame('v1', $v->getId());
        self::assertSame('SAVE25', $v->getCode());
        self::assertSame(2500, $v->getDiscountCents());
        self::assertSame(15000, $v->getMinSpendCents());
        self::assertSame(2, $v->getClaimLimit());
        self::assertSame(0, $v->getClaimed());
        self::assertSame($exp, $v->getExpiresAt());
        self::assertSame('active', $v->getStatus());
        self::assertTrue($v->isActive(new \DateTimeImmutable('2026-01-01')));
        self::assertFalse($v->isActive(new \DateTimeImmutable('2027-01-01')));
        self::assertSame(2, $v->remaining());
        $v->incrementClaimed();
        self::assertSame(1, $v->remaining());
        $v->decrementClaimed();
        $v->decrementClaimed();
        self::assertSame(2, $v->remaining());
        $v->voidVoucher();
        self::assertSame('void', $v->getStatus());
        self::assertFalse($v->isActive(new \DateTimeImmutable('2026-06-01')));
    }

    public function testVoucherClaim(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $c = new VoucherClaim('c1', 'v1', 'u1', 'idem-1', $at);
        self::assertSame('c1', $c->getId());
        self::assertSame('v1', $c->getVoucherId());
        self::assertSame('u1', $c->getUserId());
        self::assertSame('idem-1', $c->getIdempotencyKey());
        self::assertSame($at, $c->getCreatedAt());
        self::assertSame('locked', $c->getStatus());
        self::assertNull($c->getRedeemedAt());
        $redeemAt = new \DateTimeImmutable('2026-04-19');
        $c->redeem($redeemAt, 'rk-1', 20000, 2500);
        self::assertSame('redeemed', $c->getStatus());
        self::assertSame($redeemAt, $c->getRedeemedAt());
        self::assertSame('rk-1', $c->getRedemptionIdempotencyKey());
        self::assertSame(20000, $c->getRedeemedOrderAmountCents());
        self::assertSame(2500, $c->getRedeemedDiscountCents());

        $c2 = new VoucherClaim('c2', 'v1', 'u2', 'idem-2', $at);
        $c2->voidClaim();
        self::assertSame('void', $c2->getStatus());
    }

    public function testGuardianLink(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $l = new GuardianLink('l1', 'g1', 'c1', $at);
        self::assertSame('l1', $l->getId());
        self::assertSame('g1', $l->getGuardianId());
        self::assertSame('c1', $l->getChildId());
        self::assertSame($at, $l->getLinkedAt());
        self::assertSame(5, GuardianLink::MAX_CHILDREN);
    }

    public function testDevice(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $d = new Device('d1', 'u1', 'iPad', 'fp', $at);
        self::assertSame('d1', $d->getId());
        self::assertSame('u1', $d->getUserId());
        self::assertSame('iPad', $d->getDeviceName());
        self::assertSame('fp', $d->getFingerprint());
        self::assertSame($at, $d->getApprovedAt());
        self::assertSame('approved', $d->getStatus());
        self::assertTrue($d->isApproved());
        self::assertNull($d->getSessionToken());
        $d->setSessionToken('tok');
        self::assertSame('tok', $d->getSessionToken());
        $d->revoke();
        self::assertSame('revoked', $d->getStatus());
        self::assertFalse($d->isApproved());
        self::assertNull($d->getSessionToken());
    }

    public function testModerationItemLifecycle(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $m = new ModerationItem('m1', 'author', 'note', 'content', 'chksum', $at);
        self::assertSame('m1', $m->getId());
        self::assertSame('author', $m->getAuthorId());
        self::assertSame('note', $m->getKind());
        self::assertSame('content', $m->getContent());
        self::assertSame('chksum', $m->getChecksum());
        self::assertSame($at, $m->getSubmittedAt());
        self::assertSame('pending', $m->getStatus());
        self::assertNull($m->getReviewerId());
        self::assertNull($m->getReason());
        self::assertNull($m->getQualityScore());
        $m->approve('rev', 90, 'nice');
        self::assertSame('approved', $m->getStatus());
        self::assertSame('rev', $m->getReviewerId());
        self::assertSame(90, $m->getQualityScore());
        self::assertSame('nice', $m->getReason());

        $m2 = new ModerationItem('m2', 'author2', 'evidence', 'c', 'csum2', $at);
        $m2->reject('rev', 'bad');
        self::assertSame('rejected', $m2->getStatus());
        self::assertSame('bad', $m2->getReason());
    }

    public function testAuditLog(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $log = new AuditLog('l1', 'actor', 'do', 'booking', 'b1', $at, ['a' => 1], ['a' => 2]);
        self::assertSame('l1', $log->getId());
        self::assertSame('actor', $log->getActorId());
        self::assertSame('do', $log->getAction());
        self::assertSame('booking', $log->getEntityType());
        self::assertSame('b1', $log->getEntityId());
        self::assertSame($at, $log->getOccurredAt());
        self::assertSame(['a' => 1], $log->getBefore());
        self::assertSame(['a' => 2], $log->getAfter());
    }

    public function testCertificate(): void
    {
        $at = new \DateTimeImmutable('2026-04-18');
        $c = new Certificate('c1', 'u1', 'r1', 'VCODE', '/path.pdf', $at);
        self::assertSame('c1', $c->getId());
        self::assertSame('u1', $c->getTraineeId());
        self::assertSame('r1', $c->getRankId());
        self::assertSame('VCODE', $c->getVerificationCode());
        self::assertSame('/path.pdf', $c->getPdfPath());
        self::assertSame($at, $c->getIssuedAt());
        self::assertSame('active', $c->getStatus());
        self::assertTrue($c->isValid());
        $c->revoke();
        self::assertSame('revoked', $c->getStatus());
        self::assertFalse($c->isValid());
    }
}
