<?php

namespace App\Http\Middleware;

use App\Models\Role;
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
		$user = $request->user();

		if (! $user || ! $this->isAuditableActor($user)) {
			return $next($request);
		}

		$actorUserId = (string) $user->id;
		$actorEmail = $user->email;

		if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
			return $next($request);
		}

		$routeName = (string) optional($request->route())->getName();

		if (! Str::startsWith($routeName, ['admin.', 'super-admin.', 'org-manager.'])) {
			return $next($request);
		}

		if (Str::contains($routeName, '.transactions.')) {
			return $next($request);
		}

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
					'panel' => Str::before($routeName, '.'),
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
					'panel' => Str::before($routeName, '.'),
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
		string $actorUserId,
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
		$parts = explode('.', $routeName);

		if (count($parts) >= 2 && $parts[1] !== '') {
			return $parts[1];
		}

		$segment = (string) ($request->segment(2) ?? 'general');

		return Str::snake($segment);
	}

	private function resolveTransactionType(string $routeName, Request $request): string
	{
		$parts = explode('.', $routeName);
		$tail = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : '';

		if ($tail !== '') {
			return $tail;
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

	private function isAuditableActor(object $user): bool
	{
		if (! method_exists($user, 'hasRole')) {
			return false;
		}

		return $user->hasRole(Role::SUPER_ADMIN) || $user->hasRole(Role::ADMIN);
	}

	private function sanitizeForLog(mixed $value): mixed
	{
		if (is_array($value)) {
			$sanitized = [];

			foreach ($value as $key => $item) {
				$sanitized[$key] = $this->sanitizeForLog($item);
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

		return $value;
	}
}
