<?php

namespace App\Http\Middleware;

use App\Support\TransactionLogbook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogAdminTransactions
{
	public function handle(Request $request, Closure $next): Response
	{
		if ($this->shouldSkipLogging($request)) {
			return $next($request);
		}

		$user = $request->user();
		$actorUserId = $user?->id ? (string) $user->id : null;
		$actorEmail = is_string($user?->email) ? $user->email : null;

		$routeName = (string) optional($request->route())->getName();

		$module = $this->resolveModule($routeName, $request);
		$transactionType = $this->resolveTransactionType($routeName, $request);
		[$referenceType, $referenceId] = $this->resolveReference($request);
		$before = $this->resolveBeforeData($request);

		$after = [
			'input' => $this->sanitizeForLog($request->except([
				'_token',
				'_method',
				'password',
				'password_confirmation',
				'current_password',
			])),
		];

		try {
			$response = $next($request);

			$status = $response->getStatusCode() >= 400 ? 'failed' : 'success';
			$reason = $status === 'failed' ? ('HTTP ' . $response->getStatusCode()) : null;

			$this->safeWrite(
				request: $request,
				module: $module,
				transactionType: $transactionType,
				status: $status,
				referenceType: $referenceType,
				referenceId: $referenceId,
				before: $before,
				after: $after,
				reason: $reason,
				actorUserId: $actorUserId,
				actorEmail: $actorEmail,
				metadata: [
					'route_name' => $routeName,
					'panel' => $routeName !== '' ? Str::before($routeName, '.') : null,
					'http_method' => $request->method(),
					'response_status' => $response->getStatusCode(),
				]
			);

			return $response;
		} catch (Throwable $e) {
			$this->safeWrite(
				request: $request,
				module: $module,
				transactionType: $transactionType,
				status: 'failed',
				referenceType: $referenceType,
				referenceId: $referenceId,
				before: $before,
				after: $after,
				reason: Str::limit($e->getMessage(), 190),
				actorUserId: $actorUserId,
				actorEmail: $actorEmail,
				metadata: [
					'route_name' => $routeName,
					'panel' => $routeName !== '' ? Str::before($routeName, '.') : null,
					'http_method' => $request->method(),
					'exception' => class_basename($e),
				]
			);

			throw $e;
		}
	}

	private function safeWrite(
		Request $request,
		string $module,
		string $transactionType,
		string $status,
		?string $referenceType,
		?string $referenceId,
		?array $before,
		array $after,
		?string $reason,
		?string $actorUserId,
		?string $actorEmail,
		array $metadata
	): void {
		try {
			TransactionLogbook::write(
				request: $request,
				module: $module,
				transactionType: $transactionType,
				status: $status,
				referenceType: $referenceType,
				referenceId: $referenceId,
				before: $before,
				after: $after,
				reason: $reason,
				metadata: $metadata,
				actorUserId: $actorUserId,
				actorEmail: $actorEmail
			);
		} catch (Throwable $e) {
			report($e);
		}
	}

	private function resolveModule(string $routeName, Request $request): string
	{
		if ($routeName !== '') {
			$parts = explode('.', $routeName);

			if (count($parts) >= 2 && $parts[1] !== '') {
				return $parts[1];
			}
		}

		$segment = (string) ($request->segment(1) ?? 'general');

		return Str::snake($segment);
	}

	private function resolveTransactionType(string $routeName, Request $request): string
	{
		if ($routeName !== '') {
			$parts = explode('.', $routeName);
			$tail = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : '';

			if ($tail !== '') {
				return $tail;
			}
		}

		return Str::lower($request->method());
	}

	private function resolveReference(Request $request): array
	{
		$params = optional($request->route())->parameters() ?? [];

		foreach ($params as $key => $value) {
			if ($value instanceof Model) {
				return [class_basename($value), (string) $value->getKey()];
			}

			if (is_scalar($value)) {
				return [(string) $key, (string) $value];
			}
		}

		return [null, null];
	}

	private function resolveBeforeData(Request $request): ?array
	{
		$params = optional($request->route())->parameters() ?? [];

		foreach ($params as $value) {
			if ($value instanceof Model) {
				return $this->sanitizeForLog($value->getAttributes());
			}
		}

		return null;
	}

	private function shouldSkipLogging(Request $request): bool
	{
		if (Str::startsWith((string) $request->path(), ['_debugbar', 'telescope'])) {
			return true;
		}

		return false;
	}

	private function sanitizeForLog(mixed $value, ?string $key = null): mixed
	{
		if ($key !== null && $this->isSensitiveKey($key)) {
			return $this->maskSensitiveValue($value, $key);
		}

		if (is_array($value)) {
			$sanitized = [];

			foreach ($value as $key => $item) {
				$sanitized[$key] = $this->sanitizeForLog($item, (string) $key);
			}

			return $sanitized;
		}

		if ($value instanceof \Illuminate\Http\UploadedFile) {
			return [
				'filename' => $value->getClientOriginalName(),
				'size' => $value->getSize(),
				'mime' => $value->getClientMimeType(),
			];
		}

		if (is_object($value)) {
			return method_exists($value, '__toString') ? (string) $value : class_basename($value);
		}

		if (is_string($value) && str_contains($value, '@')) {
			return $this->maskEmail($value);
		}

		return $value;
	}

	private function isSensitiveKey(string $key): bool
	{
		$normalized = Str::lower($key);
		$sensitiveTerms = [
			'password',
			'passcode',
			'otp',
			'token',
			'secret',
			'api_key',
			'authorization',
			'cookie',
			'session',
			'remember',
			'credential',
			'private_key',
			'email',
		];

		foreach ($sensitiveTerms as $term) {
			if (str_contains($normalized, $term)) {
				return true;
			}
		}

		return false;
	}

	private function maskSensitiveValue(mixed $value, string $key): mixed
	{
		if (is_array($value)) {
			return '[redacted_array:' . $key . ']';
		}

		if (is_object($value)) {
			return '[redacted_object:' . $key . ']';
		}

		if (is_string($value) && str_contains($value, '@')) {
			return $this->maskEmail($value);
		}

		return '[redacted]';
	}

	private function maskEmail(string $email): string
	{
		$parts = explode('@', $email, 2);

		if (count($parts) !== 2) {
			return '[redacted]';
		}

		$local = $parts[0];
		$domain = $parts[1];
		$maskedLocal = strlen($local) <= 2
			? str_repeat('*', strlen($local))
			: substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));

		return $maskedLocal . '@' . $domain;
	}
}
