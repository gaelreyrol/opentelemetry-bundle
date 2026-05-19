# FrankenPHP and other long-running runtimes

PHP's shared-nothing request model is a poor fit for OpenTelemetry metrics. Each FPM process gets its own `MeterProvider`, so cumulative counters reset every request and the collector sees the latest value instead of an accumulating series. Spans and logs are less affected because they're already per-request; metrics are the painful case.

[FrankenPHP](https://frankenphp.dev/)'s worker mode keeps the PHP process resident between requests. Combined with the bundle settings below it gives you the long-lived state the OpenTelemetry SDK was designed for.

## Quick start

```yaml
# config/packages/open_telemetry.yaml
open_telemetry:
    runtime: auto         # auto-detect FrankenPHP worker mode (default)
    provider_source: di   # build providers via this bundle (default)
    # ... your existing service / traces / metrics / logs config
```

Then run your application through FrankenPHP worker mode. On Symfony 7.4+ this is built into `symfony/runtime`; on older versions install `runtime/frankenphp-symfony` and set `APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime`.

That's the whole opt-in: when `runtime` is `auto` the bundle inspects `$_SERVER['APP_RUNTIME_MODE']` (set by Symfony's `FrankenPhpWorkerRunner` to `web=1&worker=1`) and switches the `kernel.terminate` subscriber from `shutdown()` to `forceFlush()`. Counters built once during worker boot keep accumulating across all requests in that worker.

## The `runtime` config key

```yaml
open_telemetry:
    runtime: auto | classic | frankenphp_worker
```

- `auto` (default) — detect at runtime. Returns `frankenphp_worker` when `$_SERVER['APP_RUNTIME_MODE']` contains `worker=1`, or when `frankenphp_handle_request()` exists and `APP_RUNTIME` resolves to a FrankenPHP runtime class.
- `classic` — force shared-nothing semantics. The kernel.terminate subscriber calls `MeterProvider::shutdown()` after every request. Use this when running under FPM, the built-in PHP server, or for one-shot CLI commands.
- `frankenphp_worker` — force worker semantics. The subscriber calls `forceFlush()` instead and defers `shutdown()` to `register_shutdown_function` so the worker can keep accumulating across iterations.

You only need an explicit value if auto-detection misses your setup or you want determinism in tests.

## Multi-worker resource attributes

A single FrankenPHP server runs N worker processes (often `2 × CPU`). Each worker has its own MeterProvider and its own in-memory counter. If they all reported under the same service identity, the collector would see N writers for a single time series and behaviour becomes undefined.

The bundle automatically adds `process.pid` to the resource composition (semconv attribute) so each worker becomes a distinct series the backend can sum across. Nothing to configure — it works in classic mode too, but the impact is only meaningful under worker mode.

FrankenPHP does not expose a stable per-worker identifier beyond the OS PID; the Caddyfile `worker { name … }` directive names the worker pool for FrankenPHP's own metrics/logs but is not surfaced to PHP. PID alone is sufficient.

## `provider_source: globals` — externally bootstrapped SDK

If you bootstrap the OpenTelemetry SDK outside the bundle (`OTEL_PHP_AUTOLOAD_ENABLED=true` runs SDK initializers during Composer autoload, or you wire `Sdk::builder()->buildAndRegisterGlobal()` manually in your FrankenPHP worker entry script) you don't want the bundle to build its own provider pipeline — you want it to consume the providers you already published into `OpenTelemetry\API\Globals`.

```yaml
open_telemetry:
    provider_source: globals
    # service / instrumentation config still required;
    # traces.processors / traces.exporters / metrics.exporters / logs.* sections still parsed
    # but their values are ignored because providers come from Globals.
```

In this mode:

- Every provider service the bundle builds is a thin delegate over `Globals::tracerProvider()`, `meterProvider()`, `loggerProvider()`. Construction-time arguments (samplers, processors, exporters) are accepted but ignored.
- The bundle still owns **instrumentation** — event subscribers, decorators, middleware. These consume the Globals-sourced providers transparently.
- If `Globals::*Provider()` returns an API-level no-op (because no external bootstrap published an SDK provider before the bundle resolved its services), the bundle's `GlobalsXProviderFactory` throws a `LogicException` with a pointer to fix the bootstrap order.

When `provider_source` is `di` (the default) the bundle additionally publishes its DI-built providers *into* Globals on first `kernel.request`, so third-party libraries reaching for `Globals::*Provider()` see the same instances the bundle uses. No flag to set — this is automatic.

### Choosing between `di` and `globals`

| You want… | Use |
|---|---|
| The bundle to own provider construction; everything configured via YAML | `di` (default) |
| Auto-loaded SDK contrib instrumentation (the `open-telemetry/opentelemetry-auto-*` packages) to share providers with the bundle | `di` — they reach via Globals, the bundle publishes there automatically |
| The SDK bootstrapped externally (e.g. for compatibility with a deployment-level config) and the bundle to consume those providers | `globals` |

## Known limitations

- **No periodic export.** PHP has no native background threads, so a `PeriodicExportingMetricReader` cannot truly tick on a timer. The bundle uses an `ExportingReader` that flushes on `kernel.terminate` — under steady traffic that's an export per request, which is fine. Under idle conditions exports lag until the next request.
- **State leaks.** Worker mode reuses services across requests. The OpenTelemetry providers are designed to do this safely, but application services with mutable state need `Symfony\Contracts\Service\ResetInterface` or they will leak. The FrankenPHP docs recommend [igor-php/igor-php](https://github.com/igor-php/igor-php) as a static linter to surface these.
- **Provider source rules per signal.** When `provider_source: globals` is set globally, *all* configured providers in `traces.providers`, `metrics.providers`, and `logs.providers` are forced to `type: globals`. To mix-and-match (e.g. metrics from Globals but traces from DI), set `provider_source: di` and explicitly set `type: globals` on the providers that should consume Globals.

## Verifying it works

A functional test under `tests/Functional/Runtime/WorkerModeAccumulationTest` boots the test kernel with `runtime: frankenphp_worker`, disables `KernelBrowser` reboot (so the kernel reuses its container across `$client->request()` calls like a real FrankenPHP worker), issues two `/increment/{value}` requests, and asserts both values reach the exporter via the same provider. That's the regression test for the original bug — the `MeterProvider` no longer dies on `kernel.terminate` in worker mode.

For end-to-end validation against a real FrankenPHP worker, see `tests/Acceptance/FrankenPHPRuntimeTest` (run via the dedicated CI job).
