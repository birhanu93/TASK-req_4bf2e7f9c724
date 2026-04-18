<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ModerationAttachment;
use App\Entity\ModerationItem;
use App\Exception\AuthException;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthService;
use App\Service\ContentChecker;
use App\Service\ModerationService;
use App\Service\RbacService;
use App\Service\SessionContext;

final class ModerationController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private ModerationService $moderation,
        private ContentChecker $checker,
    ) {
    }

    public function submit(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.submit');
        $item = $this->moderation->submit(
            $ctx->getUserId(),
            (string) $req->input('kind', ''),
            (string) $req->input('content', ''),
        );
        return Response::json($this->serialize($item), 201);
    }

    public function attach(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.submit');
        $mimeType = (string) $req->input('mimeType', '');
        $filename = (string) $req->input('filename', '');
        $contentB64 = (string) $req->input('contentBase64', '');
        if ($contentB64 === '') {
            throw new ValidationException('contentBase64 required');
        }
        $content = base64_decode($contentB64, true);
        if ($content === false) {
            throw new ValidationException('invalid base64 payload');
        }
        $attachment = $this->moderation->attach(
            (string) $vars['id'],
            $ctx->getUserId(),
            $filename,
            $mimeType,
            $content,
        );
        return Response::json($this->serializeAttachment($attachment), 201);
    }

    public function approve(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.review');
        $item = $this->moderation->approve(
            $vars['id'],
            $ctx->getUserId(),
            (int) $req->input('score', 0),
            $req->input('reason') === null ? null : (string) $req->input('reason'),
        );
        return Response::json($this->serialize($item));
    }

    public function reject(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.review');
        $item = $this->moderation->reject(
            $vars['id'],
            $ctx->getUserId(),
            (string) $req->input('reason', ''),
        );
        return Response::json($this->serialize($item));
    }

    public function bulkApprove(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.review');
        $ids = array_map('strval', (array) $req->input('ids', []));
        $result = $this->moderation->bulkApprove($ids, $ctx->getUserId(), (int) $req->input('score', 0));
        return Response::json([
            'approved' => array_map(fn ($i) => $this->serialize($i), $result['approved']),
            'failed' => $result['failed'],
        ]);
    }

    public function bulkReject(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.review');
        $ids = array_map('strval', (array) $req->input('ids', []));
        $result = $this->moderation->bulkReject($ids, $ctx->getUserId(), (string) $req->input('reason', ''));
        return Response::json([
            'rejected' => array_map(fn ($i) => $this->serialize($i), $result['rejected']),
            'failed' => $result['failed'],
        ]);
    }

    public function pending(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'moderation.review');
        $items = array_map(fn ($i) => $this->serialize($i), $this->moderation->pending());
        return Response::json(['items' => $items]);
    }

    private function context(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }

    /**
     * @return array<string,mixed>
     */
    private function serialize(ModerationItem $m): array
    {
        return [
            'id' => $m->getId(),
            'authorId' => $m->getAuthorId(),
            'kind' => $m->getKind(),
            'status' => $m->getStatus(),
            'reviewerId' => $m->getReviewerId(),
            'qualityScore' => $m->getQualityScore(),
            'reason' => $m->getReason(),
            'submittedAt' => $m->getSubmittedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeAttachment(ModerationAttachment $a): array
    {
        return [
            'id' => $a->getId(),
            'itemId' => $a->getItemId(),
            'filename' => $a->getFilename(),
            'mimeType' => $a->getMimeType(),
            'sizeBytes' => $a->getSizeBytes(),
            'checksum' => $a->getChecksum(),
            'uploadedAt' => $a->getUploadedAt()->format(DATE_ATOM),
        ];
    }
}
