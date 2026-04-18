<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\AssessmentTemplate;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class AssessmentServiceTest extends TestCase
{
    public function testCreateTemplateAndRank(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 10);
        self::assertSame('Push', $t->getName());
        $r = $k->assessmentService->createRank('Bronze', 0, 0, 1);
        self::assertSame('Bronze', $r->getName());
    }

    public function testTemplateNameRequired(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->assessmentService->createTemplate('', AssessmentTemplate::MODE_REP, 10);
    }

    public function testRankThresholdsMustBeNonNegative(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->assessmentService->createRank('Bad', -1, 0, 1);
    }

    public function testRecordRepMode(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 10);
        $k->assessmentService->createRank('Bronze', 0, 0, 1);
        $k->assessmentService->createRank('Silver', 10, 0, 2);
        $a = $k->assessmentService->record($t->getId(), 'u1', 'sup1', 15, 0);
        self::assertNotNull($a->getRankAchieved());
        $prog = $k->assessmentService->progress('u1');
        self::assertSame(15, $prog['reps']);
        self::assertSame(1, $prog['assessments']);
        self::assertNotNull($prog['currentRank']);
    }

    public function testRecordTimeMode(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Run', AssessmentTemplate::MODE_TIME, 0, 60);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 0, 90);
        $prog = $k->assessmentService->progress('u1');
        self::assertSame(90, $prog['seconds']);
        self::assertNull($prog['currentRank']);
        self::assertNull($prog['nextRank']);
    }

    public function testRecordCombinedMode(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Combo', AssessmentTemplate::MODE_COMBINED, 5, 30);
        $a = $k->assessmentService->record($t->getId(), 'u1', 'sup1', 10, 60);
        self::assertSame(10, $a->getReps());
        self::assertSame(60, $a->getSeconds());
    }

    public function testRecordMissingTemplate(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->assessmentService->record('nope', 'u1', 'sup1', 5, 10);
    }

    public function testRecordNegative(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 10);
        $this->expectException(ValidationException::class);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', -1, 0);
    }

    public function testRecordRepZeroInvalid(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 10);
        $this->expectException(ValidationException::class);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 0, 0);
    }

    public function testRecordTimeZeroInvalid(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Run', AssessmentTemplate::MODE_TIME, 0, 60);
        $this->expectException(ValidationException::class);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 1, 0);
    }

    public function testRecordCombinedMissingOne(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Combo', AssessmentTemplate::MODE_COMBINED, 5, 30);
        $this->expectException(ValidationException::class);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 5, 0);
    }

    public function testProgressAccumulatesRankAndNext(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 5);
        $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $k->assessmentService->createRank('Silver', 20, 0, 2);
        $k->assessmentService->createRank('Gold', 50, 0, 3);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 10, 0);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 15, 0);
        $prog = $k->assessmentService->progress('u1');
        self::assertSame(25, $prog['reps']);
        self::assertSame(2, $prog['assessments']);
        self::assertNotNull($prog['nextRank']);
    }

    public function testProgressHandlesMissingRankRef(): void
    {
        $k = Factory::kernel();
        $t = $k->assessmentService->createTemplate('Push', AssessmentTemplate::MODE_REP, 5);
        $rank = $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $k->assessmentService->record($t->getId(), 'u1', 'sup1', 10, 0);
        $k->ranks->delete($rank->getId());
        $prog = $k->assessmentService->progress('u1');
        self::assertSame(10, $prog['reps']);
    }
}
