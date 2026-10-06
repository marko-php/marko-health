<?php

declare(strict_types=1);

use Marko\Health\Config\HealthConfig;
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Controller\HealthController;
use Marko\Health\Exceptions\HealthException;
use Marko\Health\Registry\HealthCheckRegistry;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeConfigRepository;

function healthCheck(
    string $name,
    HealthStatus $status,
    string $message,
    ?Throwable $exception = null,
): HealthCheckInterface {
    return new readonly class ($name, $status, $message, $exception) implements HealthCheckInterface
    {
        public function __construct(
            private string $name,
            private HealthStatus $status,
            private string $message,
            private ?Throwable $exception,
        ) {}

        public function getName(): string
        {
            return $this->name;
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: $this->name,
                status: $this->status,
                message: $this->message,
                metadata: [],
                duration: 0.1,
                exception: $this->exception,
            );
        }
    };
}

function healthController(
    HealthCheckRegistry $registry,
    mixed $secret = null,
): HealthController {
    return new HealthController(
        $registry,
        new HealthConfig(new FakeConfigRepository(['health.secret' => $secret])),
    );
}

it('aggregates all check results in HealthController', function () {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Healthy, 'Database OK'));
    $registry->register(healthCheck('cache', HealthStatus::Healthy, 'Cache OK'));

    $response = healthController($registry)->index(new Request());

    expect($response)->toBeInstanceOf(Response::class);

    $data = json_decode($response->body(), true);

    expect($data['checks'])->toHaveCount(2)
        ->and($data['checks'][0]['name'])->toBe('database')
        ->and($data['checks'][1]['name'])->toBe('cache');
});

it('returns 200 with healthy status when all checks pass', function () {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Healthy, 'Database OK'));

    $response = healthController($registry)->index(new Request());

    expect($response->statusCode())->toBe(200);

    $data = json_decode($response->body(), true);

    expect($data['status'])->toBe('healthy');
});

it('returns 503 with unhealthy status when any critical check fails', function () {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Unhealthy, 'Database connection failed'));

    $response = healthController($registry)->index(new Request());

    expect($response->statusCode())->toBe(503);

    $data = json_decode($response->body(), true);

    expect($data['status'])->toBe('unhealthy');
});

it('never serializes the exception behind a failed check into the response', function () {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck(
        'database',
        HealthStatus::Unhealthy,
        'Database connection failed',
        new RuntimeException("Access denied for user 'app'@'10.0.3.7'"),
    ));

    $response = healthController($registry)->index(new Request());

    expect($response->body())->not->toContain('Access denied')
        ->and($response->body())->not->toContain('10.0.3.7')
        ->and(json_decode($response->body(), true)['checks'][0])->toBe([
            'name' => 'database',
            'status' => 'unhealthy',
            'message' => 'Database connection failed',
            'duration' => 0.1,
        ]);
});

it('exposes GET /health route via HealthController', function () {
    $reflection = new ReflectionMethod(HealthController::class, 'index');
    $attributes = $reflection->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $route = $attributes[0]->newInstance();

    expect($route->path)->toBe('/health');
});

it('serves the report without a secret when health.secret is not set', function (mixed $secret) {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Healthy, 'Database OK'));

    $response = healthController($registry, $secret)->index(new Request());

    expect($response->statusCode())->toBe(200);
})->with([
    'null' => [null],
    'empty string' => [''],
]);

it('responds 404 without running checks when the secret is missing', function () {
    $registry = new HealthCheckRegistry();
    $check = $this->createMock(HealthCheckInterface::class);
    $check->expects($this->never())->method('check');
    $registry->register($check);

    expect(fn () => healthController($registry, 's3cret')->index(new Request()))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(404));
});

it('responds 404 when the secret does not match', function (Request $request) {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Healthy, 'Database OK'));

    expect(fn () => healthController($registry, 's3cret')->index($request))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(404));
})->with([
    'wrong header' => [new Request(server: ['HTTP_X_HEALTH_SECRET' => 'wrong'])],
    'wrong query' => [new Request(query: ['secret' => 'wrong'])],
    'array query' => [new Request(query: ['secret' => ['s3cret']])],
    'empty header' => [new Request(server: ['HTTP_X_HEALTH_SECRET' => ''])],
]);

it('serves the report when the secret matches', function (Request $request) {
    $registry = new HealthCheckRegistry();
    $registry->register(healthCheck('database', HealthStatus::Healthy, 'Database OK'));

    $response = healthController($registry, 's3cret')->index($request);

    expect($response->statusCode())->toBe(200)
        ->and(json_decode($response->body(), true)['status'])->toBe('healthy');
})->with([
    'X-Health-Secret header' => [new Request(server: ['HTTP_X_HEALTH_SECRET' => 's3cret'])],
    'secret query parameter' => [new Request(query: ['secret' => 's3cret'])],
]);

it('throws a loud error when health.secret is not a string', function () {
    $registry = new HealthCheckRegistry();

    expect(fn () => healthController($registry, 12345)->index(new Request()))
        ->toThrow(HealthException::class, "Config key 'health.secret' must be a string or null, int given.");
});
