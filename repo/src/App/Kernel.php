<?php

declare(strict_types=1);

namespace App\App;

use App\Controller\AdminController;
use App\Controller\AssessmentController;
use App\Controller\AuthController;
use App\Controller\BookingController;
use App\Controller\CertificateController;
use App\Controller\GuardianController;
use App\Controller\ModerationController;
use App\Controller\ProfileController;
use App\Controller\ResourceController;
use App\Controller\SessionController;
use App\Controller\VoucherController;
use App\Http\Router;
use App\Persistence\Database;
use App\Persistence\InMemoryDatabase;
use App\Persistence\PdoDatabase;
use App\Repository\AssessmentRepository;
use App\Repository\AssessmentTemplateRepository;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\BookingRepository;
use App\Repository\CertificateRepository;
use App\Repository\Contract\AssessmentRepositoryInterface;
use App\Repository\Contract\AssessmentTemplateRepositoryInterface;
use App\Repository\Contract\AuditLogRepositoryInterface;
use App\Repository\Contract\AuthSessionRepositoryInterface;
use App\Repository\Contract\BookingRepositoryInterface;
use App\Repository\Contract\CertificateRepositoryInterface;
use App\Repository\Contract\DeviceRepositoryInterface;
use App\Repository\Contract\GuardianLinkRepositoryInterface;
use App\Repository\Contract\LeaveRepositoryInterface;
use App\Repository\Contract\ModerationAttachmentRepositoryInterface;
use App\Repository\Contract\ModerationRepositoryInterface;
use App\Repository\Contract\ProfileKeyRepositoryInterface;
use App\Repository\Contract\RankRepositoryInterface;
use App\Repository\Contract\ResourceRepositoryInterface;
use App\Repository\Contract\ResourceReservationRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;
use App\Repository\Contract\SystemStateRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\VoucherClaimRepositoryInterface;
use App\Repository\Contract\VoucherRepositoryInterface;
use App\Repository\DeviceRepository;
use App\Repository\GuardianLinkRepository;
use App\Repository\LeaveRepository;
use App\Repository\ModerationAttachmentRepository;
use App\Repository\ModerationRepository;
use App\Repository\Pdo\PdoAssessmentRepository;
use App\Repository\Pdo\PdoAssessmentTemplateRepository;
use App\Repository\Pdo\PdoAuditLogRepository;
use App\Repository\Pdo\PdoAuthSessionRepository;
use App\Repository\Pdo\PdoBookingRepository;
use App\Repository\Pdo\PdoCertificateRepository;
use App\Repository\Pdo\PdoDeviceRepository;
use App\Repository\Pdo\PdoGuardianLinkRepository;
use App\Repository\Pdo\PdoLeaveRepository;
use App\Repository\Pdo\PdoModerationAttachmentRepository;
use App\Repository\Pdo\PdoModerationRepository;
use App\Repository\Pdo\PdoProfileKeyRepository;
use App\Repository\Pdo\PdoRankRepository;
use App\Repository\Pdo\PdoResourceRepository;
use App\Repository\Pdo\PdoResourceReservationRepository;
use App\Repository\Pdo\PdoSessionRepository;
use App\Repository\Pdo\PdoSystemStateRepository;
use App\Repository\Pdo\PdoUserRepository;
use App\Repository\Pdo\PdoVoucherClaimRepository;
use App\Repository\Pdo\PdoVoucherRepository;
use App\Repository\ProfileKeyRepository;
use App\Repository\RankRepository;
use App\Repository\ResourceRepository;
use App\Repository\ResourceReservationRepository;
use App\Repository\SessionRepository;
use App\Repository\SystemStateRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherClaimRepository;
use App\Repository\VoucherRepository;
use App\Service\AssessmentService;
use App\Service\AuditLogger;
use App\Service\AuthService;
use App\Service\AuthorizationService;
use App\Service\BookingService;
use App\Service\CertificateService;
use App\Service\Clock;
use App\Service\ContentChecker;
use App\Service\GuardianService;
use App\Service\IdGenerator;
use App\Service\Keyring;
use App\Service\ModerationService;
use App\Service\PasswordHasher;
use App\Service\ProfileCipher;
use App\Service\ProfileService;
use App\Service\RbacService;
use App\Service\ResourceService;
use App\Service\SchedulingService;
use App\Service\SequenceIdGenerator;
use App\Service\SnapshotExporter;
use App\Service\StorageTieringService;
use App\Service\SystemClock;
use App\Service\VoucherService;

final class Kernel
{
    public UserRepositoryInterface $users;
    public SessionRepositoryInterface $sessions;
    public BookingRepositoryInterface $bookings;
    public AssessmentTemplateRepositoryInterface $templates;
    public AssessmentRepositoryInterface $assessments;
    public RankRepositoryInterface $ranks;
    public VoucherRepositoryInterface $vouchers;
    public VoucherClaimRepositoryInterface $claims;
    public GuardianLinkRepositoryInterface $guardianLinks;
    public DeviceRepositoryInterface $devices;
    public ModerationRepositoryInterface $moderation;
    public ModerationAttachmentRepositoryInterface $attachments;
    public AuditLogRepositoryInterface $auditLogs;
    public CertificateRepositoryInterface $certificates;
    public AuthSessionRepositoryInterface $authSessions;
    public SystemStateRepositoryInterface $systemState;
    public LeaveRepositoryInterface $leaves;
    public ProfileKeyRepositoryInterface $profileKeys;
    public ResourceRepositoryInterface $resources;
    public ResourceReservationRepositoryInterface $resourceReservations;

    public PasswordHasher $hasher;
    public RbacService $rbac;
    public AuthorizationService $authz;
    public AuthService $auth;
    public AuditLogger $audit;
    public SchedulingService $scheduling;
    public BookingService $bookingService;
    public AssessmentService $assessmentService;
    public ResourceService $resourceService;
    public VoucherService $voucherService;
    public ContentChecker $contentChecker;
    public ModerationService $moderationService;
    public GuardianService $guardianService;
    public CertificateService $certService;
    public StorageTieringService $tiering;
    public Keyring $keyring;
    public ProfileCipher $profileCipher;
    public ProfileService $profileService;
    public SnapshotExporter $snapshotExporter;

    public Database $database;
    public string $storageRoot;

    public Router $router;

    public function __construct(
        public Clock $clock = new SystemClock(),
        public IdGenerator $ids = new SequenceIdGenerator('k'),
        ?string $storageRoot = null,
        ?Database $database = null,
        ?string $kek = null,
    ) {
        $this->storageRoot = $storageRoot ?? sys_get_temp_dir() . '/workforce-' . bin2hex(random_bytes(4));
        $this->database = $database ?? new InMemoryDatabase();
        $pdo = $this->database->pdo();

        if ($pdo instanceof \PDO) {
            $this->users = new PdoUserRepository($pdo);
            $this->sessions = new PdoSessionRepository($pdo);
            $this->bookings = new PdoBookingRepository($pdo);
            $this->templates = new PdoAssessmentTemplateRepository($pdo);
            $this->assessments = new PdoAssessmentRepository($pdo);
            $this->ranks = new PdoRankRepository($pdo);
            $this->vouchers = new PdoVoucherRepository($pdo);
            $this->claims = new PdoVoucherClaimRepository($pdo);
            $this->guardianLinks = new PdoGuardianLinkRepository($pdo);
            $this->devices = new PdoDeviceRepository($pdo);
            $this->moderation = new PdoModerationRepository($pdo);
            $this->attachments = new PdoModerationAttachmentRepository($pdo);
            $this->auditLogs = new PdoAuditLogRepository($pdo);
            $this->certificates = new PdoCertificateRepository($pdo);
            $this->authSessions = new PdoAuthSessionRepository($pdo);
            $this->systemState = new PdoSystemStateRepository($pdo);
            $this->leaves = new PdoLeaveRepository($pdo);
            $this->profileKeys = new PdoProfileKeyRepository($pdo);
            $this->resources = new PdoResourceRepository($pdo);
            $this->resourceReservations = new PdoResourceReservationRepository($pdo);
        } else {
            $this->users = new UserRepository();
            $this->sessions = new SessionRepository();
            $this->bookings = new BookingRepository();
            $this->templates = new AssessmentTemplateRepository();
            $this->assessments = new AssessmentRepository();
            $this->ranks = new RankRepository();
            $this->vouchers = new VoucherRepository();
            $this->claims = new VoucherClaimRepository();
            $this->guardianLinks = new GuardianLinkRepository();
            $this->devices = new DeviceRepository();
            $this->moderation = new ModerationRepository();
            $this->attachments = new ModerationAttachmentRepository();
            $this->auditLogs = new AuditLogRepository();
            $this->certificates = new CertificateRepository();
            $this->authSessions = new AuthSessionRepository();
            $this->systemState = new SystemStateRepository();
            $this->leaves = new LeaveRepository();
            $this->profileKeys = new ProfileKeyRepository();
            $this->resources = new ResourceRepository();
            $this->resourceReservations = new ResourceReservationRepository();
        }

        $this->hasher = new PasswordHasher();
        $this->rbac = new RbacService();
        $this->audit = new AuditLogger($this->auditLogs, $this->clock, $this->ids);
        $this->auth = new AuthService(
            $this->users,
            $this->authSessions,
            $this->systemState,
            $this->hasher,
            $this->clock,
            $this->ids,
            $this->audit,
        );
        $this->authz = new AuthorizationService(
            $this->rbac,
            $this->bookings,
            $this->assessments,
            $this->guardianLinks,
            $this->sessions,
        );
        $this->resourceService = new ResourceService(
            $this->resources,
            $this->resourceReservations,
            $this->clock,
            $this->ids,
            $this->audit,
            $this->database,
        );
        $this->scheduling = new SchedulingService(
            $this->sessions,
            $this->leaves,
            $this->ids,
            $this->clock,
            $this->audit,
            $this->database,
            $this->resourceService,
        );
        $this->bookingService = new BookingService(
            $this->sessions,
            $this->bookings,
            $this->clock,
            $this->ids,
            $this->audit,
            $this->database,
        );
        $this->assessmentService = new AssessmentService(
            $this->templates,
            $this->assessments,
            $this->ranks,
            $this->clock,
            $this->ids,
            $this->audit,
        );
        $this->voucherService = new VoucherService(
            $this->vouchers,
            $this->claims,
            $this->clock,
            $this->ids,
            $this->audit,
            $this->database,
        );
        $this->contentChecker = new ContentChecker(['forbidden']);
        // Build tiering first so certificate + moderation services can be
        // wired with transparent cold-tier fallback for artifact reads.
        $this->tiering = new StorageTieringService(
            $this->storageRoot . '/hot',
            $this->storageRoot . '/cold',
            $this->clock,
            180,
            [
                'certificates' => [
                    'hot' => $this->storageRoot . '/certs',
                    'cold' => $this->storageRoot . '/certs-cold',
                ],
                'uploads' => [
                    'hot' => $this->storageRoot . '/uploads',
                    'cold' => $this->storageRoot . '/uploads-cold',
                ],
            ],
        );
        $this->moderationService = new ModerationService(
            $this->moderation,
            $this->attachments,
            $this->contentChecker,
            $this->clock,
            $this->ids,
            $this->audit,
            $this->storageRoot . '/uploads',
            $this->tiering,
        );
        $this->guardianService = new GuardianService(
            $this->guardianLinks,
            $this->devices,
            $this->users,
            $this->clock,
            $this->ids,
            $this->audit,
        );
        $this->certService = new CertificateService(
            $this->certificates,
            $this->ranks,
            $this->users,
            $this->clock,
            $this->ids,
            $this->audit,
            $this->storageRoot . '/certs',
            $this->tiering,
        );

        $kekBytes = $kek ?? Keyring::loadKek($this->storageRoot . '/keys/kek.bin');
        $this->keyring = new Keyring($this->profileKeys, $kekBytes, $this->clock);
        $this->profileCipher = new ProfileCipher($this->keyring);
        $this->profileService = new ProfileService(
            $this->users,
            $this->profileCipher,
            $this->keyring,
            $this->audit,
        );
        $this->snapshotExporter = new SnapshotExporter(
            $this->storageRoot . '/snapshots',
            $this->users,
            $this->sessions,
            $this->bookings,
            $this->assessments,
            $this->vouchers,
            $this->claims,
            $this->moderation,
            $this->certificates,
            $this->auditLogs,
            $this->clock,
        );

        $this->router = new Router();
        $this->registerRoutes();
    }

    public static function fromEnv(): self
    {
        $dbDsn = getenv('DB_DSN') ?: '';
        if ($dbDsn === '') {
            $host = getenv('DB_HOST') ?: '';
            $name = getenv('DB_NAME') ?: '';
            if ($host !== '' && $name !== '') {
                $port = getenv('DB_PORT') ?: '';
                $portFragment = $port !== '' ? ";port={$port}" : '';
                $dbDsn = "mysql:host={$host}{$portFragment};dbname={$name};charset=utf8mb4";
            }
        }
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASS') ?: '';
        $database = null;
        if ($dbDsn !== '') {
            $maxAttempts = (int) (getenv('DB_CONNECT_RETRIES') ?: '5');
            $retryMs = (int) (getenv('DB_CONNECT_RETRY_MS') ?: '200');
            $database = PdoDatabase::fromDsn(
                $dbDsn,
                $dbUser,
                $dbPass,
                $maxAttempts > 0 ? $maxAttempts : 5,
                $retryMs > 0 ? $retryMs : 200,
            );
        }
        $storageRoot = getenv('STORAGE_ROOT') ?: (__DIR__ . '/../../storage');
        $kekPath = getenv('KEK_PATH') ?: ($storageRoot . '/keys/kek.bin');
        $kek = Keyring::loadKek($kekPath);
        return new self(new SystemClock(), new \App\Service\UuidGenerator(), $storageRoot, $database, $kek);
    }

    public function container(): Container
    {
        return new Container($this);
    }

    private function registerRoutes(): void
    {
        $cookieSecure = filter_var(getenv('SESSION_COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $authC = new AuthController($this->auth, $this->rbac, $this->systemState, $cookieSecure);
        $sessC = new SessionController($this->auth, $this->rbac, $this->scheduling, $this->bookingService);
        $bookC = new BookingController($this->auth, $this->rbac, $this->authz, $this->bookingService, $this->bookings, $this->sessions);
        $assessC = new AssessmentController($this->auth, $this->rbac, $this->authz, $this->assessmentService);
        $voucherC = new VoucherController($this->auth, $this->rbac, $this->voucherService);
        $modC = new ModerationController($this->auth, $this->rbac, $this->moderationService, $this->contentChecker);
        $gC = new GuardianController($this->auth, $this->rbac, $this->authz, $this->guardianService, $this->assessmentService);
        $certC = new CertificateController($this->auth, $this->rbac, $this->authz, $this->certService);
        $adminC = new AdminController(
            $this->auth,
            $this->rbac,
            $this->audit,
            $this->tiering,
            $this->snapshotExporter,
            $this->keyring,
            $this->clock,
        );
        $profileC = new ProfileController($this->auth, $this->authz, $this->profileService, $this->rbac);
        $resourceC = new ResourceController($this->auth, $this->rbac, $this->resourceService);

        $this->router->add('POST', '/api/auth/bootstrap', [$authC, 'bootstrap']);
        $this->router->add('POST', '/api/auth/register', [$authC, 'register']);
        $this->router->add('POST', '/api/auth/login', [$authC, 'login']);
        $this->router->add('POST', '/api/auth/select-role', [$authC, 'selectRole']);
        $this->router->add('POST', '/api/auth/switch-role', [$authC, 'switchRole']);
        $this->router->add('POST', '/api/auth/logout', [$authC, 'logout']);
        $this->router->add('POST', '/api/auth/change-password', [$authC, 'changePassword']);
        $this->router->add('GET', '/api/auth/me', [$authC, 'me']);

        $this->router->add('POST', '/api/sessions', [$sessC, 'create']);
        $this->router->add('GET', '/api/sessions', [$sessC, 'list']);
        $this->router->add('POST', '/api/sessions/{id}/close', [$sessC, 'close']);
        $this->router->add('GET', '/api/sessions/{id}/availability', [$sessC, 'availability']);
        $this->router->add('POST', '/api/sessions/leaves', [$sessC, 'addLeave']);
        $this->router->add('GET', '/api/sessions/leaves', [$sessC, 'listLeaves']);

        $this->router->add('POST', '/api/bookings', [$bookC, 'create']);
        $this->router->add('GET', '/api/bookings', [$bookC, 'list']);
        $this->router->add('GET', '/api/bookings/{id}', [$bookC, 'show']);
        $this->router->add('POST', '/api/bookings/{id}/confirm', [$bookC, 'confirm']);
        $this->router->add('POST', '/api/bookings/{id}/cancel', [$bookC, 'cancel']);
        $this->router->add('POST', '/api/bookings/{id}/reschedule', [$bookC, 'reschedule']);

        $this->router->add('POST', '/api/assessments/templates', [$assessC, 'createTemplate']);
        $this->router->add('POST', '/api/assessments/ranks', [$assessC, 'createRank']);
        $this->router->add('GET', '/api/assessments/ranks', [$assessC, 'listRanks']);
        $this->router->add('POST', '/api/assessments', [$assessC, 'record']);
        $this->router->add('GET', '/api/assessments/progress/{traineeId}', [$assessC, 'progress']);

        $this->router->add('POST', '/api/vouchers', [$voucherC, 'issue']);
        $this->router->add('GET', '/api/vouchers', [$voucherC, 'listAll']);
        $this->router->add('GET', '/api/vouchers/{code}', [$voucherC, 'describe']);
        $this->router->add('POST', '/api/vouchers/claims', [$voucherC, 'claim']);
        $this->router->add('POST', '/api/vouchers/claims/{id}/redeem', [$voucherC, 'redeem']);
        $this->router->add('POST', '/api/vouchers/claims/{id}/void', [$voucherC, 'voidClaim']);
        $this->router->add('POST', '/api/vouchers/{id}/void', [$voucherC, 'voidVoucher']);

        $this->router->add('POST', '/api/moderation', [$modC, 'submit']);
        $this->router->add('POST', '/api/moderation/{id}/attachments', [$modC, 'attach']);
        $this->router->add('POST', '/api/moderation/{id}/approve', [$modC, 'approve']);
        $this->router->add('POST', '/api/moderation/{id}/reject', [$modC, 'reject']);
        $this->router->add('POST', '/api/moderation/bulk-approve', [$modC, 'bulkApprove']);
        $this->router->add('POST', '/api/moderation/bulk-reject', [$modC, 'bulkReject']);
        $this->router->add('GET', '/api/moderation/pending', [$modC, 'pending']);

        $this->router->add('POST', '/api/guardians/links', [$gC, 'link']);
        $this->router->add('POST', '/api/guardians/devices', [$gC, 'approveDevice']);
        $this->router->add('POST', '/api/guardians/devices/{id}/logout', [$gC, 'remoteLogout']);
        $this->router->add('GET', '/api/guardians/children', [$gC, 'children']);
        $this->router->add('GET', '/api/guardians/children/{childId}/progress', [$gC, 'childProgress']);
        $this->router->add('GET', '/api/guardians/children/{childId}/devices', [$gC, 'listDevices']);

        $this->router->add('POST', '/api/certificates', [$certC, 'issue']);
        $this->router->add('GET', '/api/certificates', [$certC, 'listAll']);
        $this->router->add('GET', '/api/certificates/mine', [$certC, 'listMine']);
        $this->router->add('GET', '/api/certificates/verify/{code}', [$certC, 'verify']);
        $this->router->add('POST', '/api/certificates/{id}/revoke', [$certC, 'revoke']);
        $this->router->add('GET', '/api/certificates/{id}/download', [$certC, 'download']);

        $this->router->add('GET', '/api/profile', [$profileC, 'get']);
        $this->router->add('PUT', '/api/profile', [$profileC, 'update']);

        $this->router->add('GET', '/api/resources', [$resourceC, 'list']);
        $this->router->add('POST', '/api/resources', [$resourceC, 'create']);
        $this->router->add('POST', '/api/resources/{id}/retire', [$resourceC, 'retire']);
        $this->router->add('GET', '/api/resources/{id}/reservations', [$resourceC, 'reservations']);

        $this->router->add('GET', '/api/admin/bookings', [$bookC, 'adminList']);

        $this->router->add('GET', '/api/admin/audit/{type}/{id}', [$adminC, 'auditHistory']);
        $this->router->add('POST', '/api/admin/storage/tier', [$adminC, 'runTiering']);
        $this->router->add('POST', '/api/admin/snapshots', [$adminC, 'snapshot']);
        $this->router->add('POST', '/api/admin/keys/rotate', [$adminC, 'rotateKey']);
    }
}
