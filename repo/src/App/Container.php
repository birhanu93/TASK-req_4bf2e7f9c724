<?php

declare(strict_types=1);

namespace App\App;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;

/**
 * Thin PSR-11 container that mirrors the services wired imperatively on the
 * Kernel. Interfaces and concrete services are registered once; lookups either
 * go through Symfony\Component\DependencyInjection\Container or fall back to
 * the underlying Kernel instance.
 */
final class Container implements ContainerInterface
{
    private SymfonyContainer $inner;

    /** @var array<string,object> */
    private array $bindings = [];

    public function __construct(Kernel $kernel)
    {
        $this->inner = new SymfonyContainer();

        $this->bindings = [
            // Core persistence.
            'database' => $kernel->database,
            \App\Persistence\Database::class => $kernel->database,

            // Repositories (interface -> concrete).
            \App\Repository\Contract\UserRepositoryInterface::class => $kernel->users,
            \App\Repository\Contract\SessionRepositoryInterface::class => $kernel->sessions,
            \App\Repository\Contract\BookingRepositoryInterface::class => $kernel->bookings,
            \App\Repository\Contract\AssessmentTemplateRepositoryInterface::class => $kernel->templates,
            \App\Repository\Contract\AssessmentRepositoryInterface::class => $kernel->assessments,
            \App\Repository\Contract\RankRepositoryInterface::class => $kernel->ranks,
            \App\Repository\Contract\VoucherRepositoryInterface::class => $kernel->vouchers,
            \App\Repository\Contract\VoucherClaimRepositoryInterface::class => $kernel->claims,
            \App\Repository\Contract\GuardianLinkRepositoryInterface::class => $kernel->guardianLinks,
            \App\Repository\Contract\DeviceRepositoryInterface::class => $kernel->devices,
            \App\Repository\Contract\ModerationRepositoryInterface::class => $kernel->moderation,
            \App\Repository\Contract\ModerationAttachmentRepositoryInterface::class => $kernel->attachments,
            \App\Repository\Contract\AuditLogRepositoryInterface::class => $kernel->auditLogs,
            \App\Repository\Contract\CertificateRepositoryInterface::class => $kernel->certificates,
            \App\Repository\Contract\AuthSessionRepositoryInterface::class => $kernel->authSessions,
            \App\Repository\Contract\SystemStateRepositoryInterface::class => $kernel->systemState,
            \App\Repository\Contract\LeaveRepositoryInterface::class => $kernel->leaves,
            \App\Repository\Contract\ProfileKeyRepositoryInterface::class => $kernel->profileKeys,
            \App\Repository\Contract\ResourceRepositoryInterface::class => $kernel->resources,
            \App\Repository\Contract\ResourceReservationRepositoryInterface::class => $kernel->resourceReservations,

            // Services.
            \App\Service\Clock::class => $kernel->clock,
            \App\Service\IdGenerator::class => $kernel->ids,
            \App\Service\PasswordHasher::class => $kernel->hasher,
            \App\Service\RbacService::class => $kernel->rbac,
            \App\Service\AuthService::class => $kernel->auth,
            \App\Service\AuthorizationService::class => $kernel->authz,
            \App\Service\AuditLogger::class => $kernel->audit,
            \App\Service\SchedulingService::class => $kernel->scheduling,
            \App\Service\BookingService::class => $kernel->bookingService,
            \App\Service\AssessmentService::class => $kernel->assessmentService,
            \App\Service\ResourceService::class => $kernel->resourceService,
            \App\Service\VoucherService::class => $kernel->voucherService,
            \App\Service\ContentChecker::class => $kernel->contentChecker,
            \App\Service\ModerationService::class => $kernel->moderationService,
            \App\Service\GuardianService::class => $kernel->guardianService,
            \App\Service\CertificateService::class => $kernel->certService,
            \App\Service\StorageTieringService::class => $kernel->tiering,
            \App\Service\Keyring::class => $kernel->keyring,
            \App\Service\ProfileCipher::class => $kernel->profileCipher,
            \App\Service\ProfileService::class => $kernel->profileService,
            \App\Service\SnapshotExporter::class => $kernel->snapshotExporter,

            // Router + kernel itself.
            \App\Http\Router::class => $kernel->router,
            Kernel::class => $kernel,
        ];

        foreach ($this->bindings as $id => $service) {
            $this->inner->set($id, $service);
        }
    }

    public function get(string $id): object
    {
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id];
        }
        if ($this->inner->has($id)) {
            return $this->inner->get($id);
        }
        throw new \RuntimeException("service '{$id}' not registered");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || $this->inner->has($id);
    }
}
