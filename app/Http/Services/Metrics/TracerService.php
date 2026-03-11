<?php

namespace App\Http\Services\Metrics;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TracerService
{
    private Client $httpClient;

    private HttpFactory $httpFactory;

    private TracerProvider $tracerProvider;

    private TracerInterface $tracer;

    private SpanInterface $rootSpan;

    private ScopeInterface $rootSpanScope;

    public function __construct()
    {
        if ($this->shouldTrace()) {
            $this->initTracer();
        }
    }

    public function shouldTrace(): bool
    {
        return ! in_array(Env::get('APP_ENV'), ['local', 'test', 'testing']);
    }

    public function initRootSpan(Request $request)
    {
        if (! $this->shouldTrace()) {
            return;
        }
        $this->rootSpan = $this->tracer->spanBuilder($request->url())->startSpan();
        $attributes = [
            'request.method' => $request->method(),
            'request.user_agent' => $request->userAgent(),
            'request.host' => $request->getHost(),
            'request.id' => BRIJ_REQUEST_ID,
        ];
        $this->rootSpan->setAttributes($attributes);
        $this->rootSpanScope = $this->rootSpan->activate();
    }

    public function endRootSpan($response): void
    {
        if (! $this->shouldTrace()) {
            return;
        }
        $attributes = array_merge([
            'status_code' => $response->getStatusCode(),
            'response.is_successful' => $response->isSuccessful(),
        ], $this->getUserProperties());

        $this->rootSpan->setAttributes($attributes);
        $this->rootSpan->end();
        $this->rootSpanScope->detach();
    }

    public function getTracer(): TracerInterface
    {
        return $this->tracer;
    }

    public function traceDbQuery(QueryExecuted $query): void
    {
        if (! $this->shouldTrace()) {
            return;
        }
        $sql = $query->sql;
        $type = Str::of($sql)->explode(' ')->first();
        $tableName = $this->getTableName($type, $sql);
        $dbSpan = $this->getTracer()->spanBuilder($type.'_'.$tableName)->startSpan();
        $dbSpan->activate();
        $dbSpan->setAttribute('db.query_type', $type);
        $dbSpan->setAttribute('db.table', $tableName);
        $dbSpan->setAttribute('db.query', str_replace('"', "'", $sql));
        $dbSpan->setAttribute('db.query_time_ms', $query->time.'ms');
        $dbSpan->setAttribute('db.query_binding', $query->bindings);
        $dbSpan->end();
    }

    public function startExternalRequestTrace(string $provider, RequestInterface $request): ?SpanInterface
    {
        if (! $this->shouldTrace()) {
            return null;
        }
        $span = $this->getTracer()->spanBuilder('external_request')->startSpan();
        $span->activate();
        $span->setAttribute('provider.name', $provider);
        $span->setAttribute('provider.req.method', $request->getMethod());
        $uri = $request->getUri();
        $span->setAttribute('provider.req.host', $uri->getHost());
        $span->setAttribute('provider.req.path', $uri->getPath());
        $span->setAttribute('provider.req.scheme', $uri->__toString());

        return $span;
    }

    public function endExternalRequestTrace(?SpanInterface $span, ResponseInterface $response): void
    {
        if (! $this->shouldTrace() || $span == null) {
            return;
        }
        $span->setAttribute('provider.resp.status', $response->getStatusCode());
        $span->setAttribute('provider.resp.reason', $response->getReasonPhrase());
        $span->end();
    }

    private function initTracer()
    {
        $this->httpClient = new Client();
        $this->httpFactory = new HttpFactory();
        $this->tracerProvider = $this->setUpProvider();
        $this->tracer = $this->tracerProvider->getTracer('brij_php_api');
        ShutdownHandler::register([$this->tracerProvider, 'shutdown']);
    }

    private function getUserProperties(): array
    {
        $user = auth()?->user();
        if ($user) {
            return
                ['user.id' => $user->uuid,
                    'user.device_id' => $user->device_id,
                    'user.country_id' => $user->country_id,
                    'user.status' => $user->status,
                ];
        }

        return [];
    }

    private function getTableName(string $type, string $query): string
    {
        $preWord = $type;
        switch ($type) {
            case 'select':
                $preWord = 'from';
                break;
            case 'insert':
                $preWord = 'into';
                break;
        }

        return Str::of($query)
            ->after($preWord)
            ->replace('`', '')
            ->trim()
            ->explode(' ')
            ->first();
    }

    private function setUpProvider(): TracerProvider
    {
        $processors = [];

        return new TracerProvider($processors);
    }

    // private function honeyCombProcessor(): SimpleSpanProcessor
    // {
    //     $transport = (new OtlpHttpTransportFactory())->create('http://collector:4318/v1/traces', 'application/x-protobuf');
    //     $exporter = new SpanExporter($transport);

    //     $exporter =
    //         new HttpExporter($this->httpClient, $this->httpFactory, $this->httpFactory);

    //     return new SimpleSpanProcessor(
    //         $exporter
    //     );
    // }
}
