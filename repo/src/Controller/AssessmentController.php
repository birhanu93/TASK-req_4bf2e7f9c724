<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AssessmentService;
use App\Service\AuthorizationService;
use App\Service\AuthService;
use App\Service\RbacService;
use App\Service\SessionContext;

final class AssessmentController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private AuthorizationService $authz,
        private AssessmentService $assessments,
    ) {
    }

    public function createTemplate(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'assessment.record');
        $template = $this->assessments->createTemplate(
            (string) $req->input('name', ''),
            (string) $req->input('mode', ''),
            (int) $req->input('targetReps', 0),
            (int) $req->input('targetSeconds', 0),
            $ctx->getUserId(),
        );
        return Response::json([
            'id' => $template->getId(),
            'name' => $template->getName(),
            'mode' => $template->getMode(),
            'targetReps' => $template->getTargetReps(),
            'targetSeconds' => $template->getTargetSeconds(),
        ], 201);
    }

    public function record(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'assessment.record');
        $traineeId = (string) $req->input('traineeId', '');
        // Object-level guard: supervisors must already be working with this
        // trainee (at least one booking in a session they own) before they
        // can record an assessment against them. Admins bypass.
        $this->authz->assertSupervisorActsOnKnownTrainee($ctx, $traineeId);
        $assessment = $this->assessments->record(
            (string) $req->input('templateId', ''),
            $traineeId,
            $ctx->getUserId(),
            (int) $req->input('reps', 0),
            (int) $req->input('seconds', 0),
        );
        return Response::json([
            'id' => $assessment->getId(),
            'templateId' => $assessment->getTemplateId(),
            'traineeId' => $assessment->getTraineeId(),
            'supervisorId' => $assessment->getSupervisorId(),
            'reps' => $assessment->getReps(),
            'seconds' => $assessment->getSeconds(),
            'rankAchieved' => $assessment->getRankAchieved(),
            'recordedAt' => $assessment->getRecordedAt()->format(DATE_ATOM),
        ], 201);
    }

    public function progress(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'assessment.view.self');
        $traineeId = (string) $vars['traineeId'];
        $this->authz->assertAssessmentProgressAccess($ctx, $traineeId);
        return Response::json($this->assessments->progress($traineeId));
    }

    public function listRanks(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'assessment.view.self');
        $out = [];
        foreach ($this->assessments->listRanks() as $r) {
            $out[] = [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'minReps' => $r->getMinReps(),
                'minSeconds' => $r->getMinSeconds(),
                'order' => $r->getOrder(),
            ];
        }
        return Response::json(['ranks' => $out]);
    }

    public function createRank(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'assessment.record');
        $rank = $this->assessments->createRank(
            (string) $req->input('name', ''),
            (int) $req->input('minReps', 0),
            (int) $req->input('minSeconds', 0),
            (int) $req->input('order', 0),
            $ctx->getUserId(),
        );
        return Response::json([
            'id' => $rank->getId(),
            'name' => $rank->getName(),
            'minReps' => $rank->getMinReps(),
            'minSeconds' => $rank->getMinSeconds(),
            'order' => $rank->getOrder(),
        ], 201);
    }

    private function context(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }
}
