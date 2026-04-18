<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\App\Kernel;
use App\Persistence\PdoDatabase;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Static guarantees for the DI wiring: every service that depends on a
 * repository must typehint the repository interface, never a concrete class.
 * That invariant is load-bearing because the Kernel supplies in-memory
 * repositories for tests and PDO repositories in production — a concrete
 * typehint would throw a TypeError on one of the two boot paths.
 *
 * The test also exercises both branches end-to-end: it constructs a kernel
 * with an InMemoryDatabase (exercised elsewhere) and a kernel backed by a
 * sqlite PDO (proving the PDO path wires every service without a
 * type-constraint failure).
 */
final class InterfaceWiringTest extends TestCase
{
    /**
     * Every service constructor in src/Service must typehint its repository
     * parameters as interfaces from App\Repository\Contract, never as a
     * concrete class from App\Repository. Enforced by reflection so the
     * regression cannot slip in unnoticed.
     */
    public function testNoServiceConstructorTakesConcreteRepository(): void
    {
        $serviceDir = dirname(__DIR__, 2) . '/src/Service';
        $offenders = [];

        foreach (glob($serviceDir . '/*.php') ?: [] as $file) {
            $basename = basename($file, '.php');
            $class = 'App\\Service\\' . $basename;
            if (!class_exists($class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            $ctor = $ref->getConstructor();
            if ($ctor === null) {
                continue;
            }
            foreach ($ctor->getParameters() as $param) {
                $type = $param->getType();
                if (!$type instanceof \ReflectionNamedType) {
                    continue;
                }
                $name = $type->getName();
                if (!str_starts_with($name, 'App\\Repository\\')) {
                    continue;
                }
                if (str_starts_with($name, 'App\\Repository\\Contract\\')) {
                    continue;
                }
                $offenders[] = "{$class}::\${$param->getName()} typed as {$name}";
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Service constructors must depend on interfaces from App\\Repository\\Contract, not concrete repositories: '
                . implode('; ', $offenders),
        );
    }

    /**
     * Concretely asserts AuditLogger accepts the interface. A regression to
     * the concrete class would break the PDO boot path because
     * PdoAuditLogRepository does not and cannot extend the final
     * AuditLogRepository in-memory class.
     */
    public function testAuditLoggerTypehintIsInterface(): void
    {
        $ctor = (new \ReflectionClass(\App\Service\AuditLogger::class))->getConstructor();
        self::assertNotNull($ctor);
        $logsParam = $ctor->getParameters()[0] ?? null;
        self::assertNotNull($logsParam);
        $type = $logsParam->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(
            \App\Repository\Contract\AuditLogRepositoryInterface::class,
            $type->getName(),
        );
    }

    /**
     * Every repository interface in App\Repository\Contract must have at
     * least two implementations available in the repository tree — the
     * in-memory class and the Pdo* class. Without both, the kernel's
     * conditional wiring branch would be uninstantiable.
     */
    public function testEveryRepositoryInterfaceHasBothBackends(): void
    {
        $contractDir = dirname(__DIR__, 2) . '/src/Repository/Contract';
        $missing = [];

        foreach (glob($contractDir . '/*RepositoryInterface.php') ?: [] as $file) {
            $interface = 'App\\Repository\\Contract\\' . basename($file, '.php');
            if (!interface_exists($interface)) {
                continue;
            }
            $short = str_replace('Interface', '', basename($file, '.php'));
            $inMemory = 'App\\Repository\\' . $short;
            $pdo = 'App\\Repository\\Pdo\\Pdo' . $short;
            $reasons = [];
            if (!class_exists($inMemory) || !is_subclass_of($inMemory, $interface)) {
                $reasons[] = "missing in-memory {$inMemory}";
            }
            if (!class_exists($pdo) || !is_subclass_of($pdo, $interface)) {
                $reasons[] = "missing PDO {$pdo}";
            }
            if ($reasons !== []) {
                $missing[] = $interface . ': ' . implode(', ', $reasons);
            }
        }

        self::assertSame([], $missing, implode('; ', $missing));
    }

    /**
     * End-to-end proof: the PDO boot path wires every service successfully
     * under a real PDO connection. Uses sqlite (always available) to
     * exercise the branch without requiring a live MySQL.
     */
    public function testPdoBackedKernelBootsWithoutTypeError(): void
    {
        $root = sys_get_temp_dir() . '/workforce-wiring-' . bin2hex(random_bytes(4));
        $db = PdoDatabase::fromDsn('sqlite::memory:', '', '');

        // Construct with a PDO-backed Database; this routes Kernel down the
        // branch that instantiates every Pdo*Repository. If any service
        // typehint were concrete instead of interface-typed, this line would
        // throw a TypeError.
        $kernel = new Kernel(
            new \App\Service\FixedClock(new \DateTimeImmutable('2026-04-18T10:00:00+00:00')),
            new \App\Service\SequenceIdGenerator('w'),
            $root,
            $db,
            str_repeat("\x01", 32),
        );

        self::assertNotNull($kernel->auth);
        self::assertNotNull($kernel->bookingService);
        self::assertNotNull($kernel->audit);
        self::assertNotNull($kernel->voucherService);
        self::assertNotNull($kernel->scheduling);
        self::assertNotNull($kernel->certService);
        self::assertNotNull($kernel->moderationService);
        self::assertNotNull($kernel->guardianService);
        self::assertNotNull($kernel->tiering);
        self::assertNotNull($kernel->profileService);
        self::assertNotNull($kernel->snapshotExporter);
    }

    /**
     * The container lookups exposed by the PSR-11 wrapper must resolve every
     * repository by interface on both boot paths.
     */
    public function testContainerResolvesRepositoryInterfacesForBothBackends(): void
    {
        $inMemory = Factory::kernel();
        $db = PdoDatabase::fromDsn('sqlite::memory:', '', '');
        $pdoRoot = sys_get_temp_dir() . '/workforce-wiring-pdo-' . bin2hex(random_bytes(4));
        $pdoKernel = new Kernel(
            new \App\Service\FixedClock(new \DateTimeImmutable('2026-04-18T10:00:00+00:00')),
            new \App\Service\SequenceIdGenerator('w'),
            $pdoRoot,
            $db,
            str_repeat("\x01", 32),
        );

        foreach ([$inMemory, $pdoKernel] as $kernel) {
            $container = $kernel->container();
            $contractDir = dirname(__DIR__, 2) . '/src/Repository/Contract';
            foreach (glob($contractDir . '/*RepositoryInterface.php') ?: [] as $file) {
                $interface = 'App\\Repository\\Contract\\' . basename($file, '.php');
                self::assertTrue($container->has($interface), "missing binding {$interface}");
                $service = $container->get($interface);
                self::assertInstanceOf($interface, $service, "{$interface} binding is wrong type");
            }
        }
    }
}
