<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assessment;
use App\Entity\AssessmentTemplate;
use App\Entity\Rank;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\AssessmentRepositoryInterface;
use App\Repository\Contract\AssessmentTemplateRepositoryInterface;
use App\Repository\Contract\RankRepositoryInterface;

final class AssessmentService
{
    public function __construct(
        private AssessmentTemplateRepositoryInterface $templates,
        private AssessmentRepositoryInterface $assessments,
        private RankRepositoryInterface $ranks,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
    ) {
    }

    public function createTemplate(string $name, string $mode, int $targetReps = 0, int $targetSeconds = 0, string $actorId = 'system'): AssessmentTemplate
    {
        if ($name === '') {
            throw new ValidationException('name is required');
        }
        $template = new AssessmentTemplate($this->ids->generate(), $name, $mode, $targetReps, $targetSeconds);
        $this->templates->save($template);
        $this->audit->record($actorId, 'assessment.template.create', 'assessmentTemplate', $template->getId(), [], [
            'name' => $name,
            'mode' => $mode,
        ]);
        return $template;
    }

    public function record(string $templateId, string $traineeId, string $supervisorId, int $reps, int $seconds): Assessment
    {
        $template = $this->templates->find($templateId);
        if ($template === null) {
            throw new NotFoundException('template not found');
        }
        if ($reps < 0 || $seconds < 0) {
            throw new ValidationException('reps and seconds must be non-negative');
        }

        switch ($template->getMode()) {
            case AssessmentTemplate::MODE_REP:
                if ($reps === 0) {
                    throw new ValidationException('reps required for rep-based assessment');
                }
                break;
            case AssessmentTemplate::MODE_TIME:
                if ($seconds === 0) {
                    throw new ValidationException('seconds required for time-based assessment');
                }
                break;
            case AssessmentTemplate::MODE_COMBINED:
                if ($reps === 0 || $seconds === 0) {
                    throw new ValidationException('reps and seconds required for combined assessment');
                }
                break;
        }

        $assessment = new Assessment(
            $this->ids->generate(),
            $templateId,
            $traineeId,
            $supervisorId,
            $reps,
            $seconds,
            $this->clock->now(),
        );
        $rank = $this->resolveRank($reps, $seconds);
        if ($rank !== null) {
            $assessment->setRankAchieved($rank->getId());
        }
        $this->assessments->save($assessment);
        $this->audit->record($supervisorId, 'assessment.record', 'assessment', $assessment->getId(), [], [
            'traineeId' => $traineeId,
            'templateId' => $templateId,
            'reps' => $reps,
            'seconds' => $seconds,
            'rankAchieved' => $assessment->getRankAchieved(),
        ]);
        return $assessment;
    }

    /**
     * @return array{reps:int,seconds:int,assessments:int,currentRank:?string,nextRank:?string}
     */
    public function progress(string $traineeId): array
    {
        $records = $this->assessments->findByTrainee($traineeId);
        $reps = 0;
        $seconds = 0;
        $currentRankId = null;
        $currentOrder = -1;
        foreach ($records as $r) {
            $reps += $r->getReps();
            $seconds += $r->getSeconds();
            if ($r->getRankAchieved() !== null) {
                $rank = $this->ranks->find($r->getRankAchieved());
                if ($rank !== null && $rank->getOrder() > $currentOrder) {
                    $currentOrder = $rank->getOrder();
                    $currentRankId = $rank->getId();
                }
            }
        }
        $next = null;
        foreach ($this->ranks->findAllOrdered() as $rank) {
            if ($rank->getOrder() > $currentOrder) {
                $next = $rank->getId();
                break;
            }
        }
        return [
            'reps' => $reps,
            'seconds' => $seconds,
            'assessments' => count($records),
            'currentRank' => $currentRankId,
            'nextRank' => $next,
        ];
    }

    /** @return Rank[] */
    public function listRanks(): array
    {
        return $this->ranks->findAllOrdered();
    }

    public function createRank(string $name, int $minReps, int $minSeconds, int $order, string $actorId = 'system'): Rank
    {
        if ($minReps < 0 || $minSeconds < 0) {
            throw new ValidationException('rank thresholds must be non-negative');
        }
        $rank = new Rank($this->ids->generate(), $name, $minReps, $minSeconds, $order);
        $this->ranks->save($rank);
        $this->audit->record($actorId, 'assessment.rank.create', 'rank', $rank->getId(), [], [
            'name' => $name,
            'minReps' => $minReps,
            'minSeconds' => $minSeconds,
            'order' => $order,
        ]);
        return $rank;
    }

    private function resolveRank(int $reps, int $seconds): ?Rank
    {
        $best = null;
        foreach ($this->ranks->findAllOrdered() as $rank) {
            if ($reps >= $rank->getMinReps() && $seconds >= $rank->getMinSeconds()) {
                $best = $rank;
            }
        }
        return $best;
    }
}
