# RuntimeContext

`RuntimeContext` provides a centralized way to manage application-wide context information like Trace IDs, Tenant details, and other cross-cutting data that needs to be accessible throughout the request lifecycle.

## 1. Quick Start & Usage

The `bedrock` package provides a `runtimeContext()` helper that resolves the singleton instance from the container.

### Basic Usage

Once booted (e.g., via middleware), you can access the context data anywhere in your application:

```php
// Core contexts (provided by Bedrock)
$traceId = runtimeContext()->trace()->trace_id;
$tenantId = runtimeContext()->tenant()->tenant_id;

// Custom contexts (if extended in your app)
$marketCode = runtimeContext()->market()->market_code;
$isUs = runtimeContext()->market()->is_us_market; // Uses computed attribute
```
