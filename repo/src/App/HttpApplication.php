<?php

declare(strict_types=1);

namespace App\App;

use App\Exception\DomainException;
use App\Http\Request as AppRequest;
use App\Http\Response as AppResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Symfony HttpKernel-compatible driver. Converts a HttpFoundation Request
 * into the app's internal Request, dispatches through the Router, and emits
 * a HttpFoundation Response. Domain exceptions map to JSON error payloads
 * with the matching status code.
 */
final class HttpApplication implements HttpKernelInterface
{
    public function __construct(private Kernel $kernel)
    {
    }

    public function handle(SymfonyRequest $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): SymfonyResponse
    {
        try {
            $internal = AppRequest::fromSymfony($request);
            $response = $this->kernel->router->dispatch($internal);
            return $response->toSymfony();
        } catch (DomainException $e) {
            return AppResponse::error($e->getMessage(), $e->getHttpStatus())->toSymfony();
        } catch (\InvalidArgumentException $e) {
            return AppResponse::error($e->getMessage(), 400)->toSymfony();
        } catch (\Throwable $e) {
            if (!$catch) {
                throw $e;
            }
            return AppResponse::error('internal server error', 500)->toSymfony();
        }
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }
}
