<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InjectLogbookTracker
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($response, $request)) {
            return $response;
        }

        $content = (string) $response->getContent();

        if (str_contains($content, 'window.__rideguideLogbookTrackerInjected')) {
            return $response;
        }

        $sessionKey = (string) Str::uuid();
        $request->attributes->set('logbook_session_key', $sessionKey);

        $script = $this->buildScript($sessionKey);

        $response->setContent(str_replace('</body>', $script . "\n</body>", $content));

        return $response;
    }

    private function shouldInject(Response $response, Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') && str_contains((string) $response->getContent(), '</body>');
    }

    private function buildScript(string $sessionKey): string
    {
        $endpoint = route('logbook.page-time');
        $csrfToken = csrf_token();
        $endpointJson = json_encode($endpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
        $csrfJson = json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
        $sessionKeyJson = json_encode($sessionKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';

        return <<<HTML
<script>
(function () {
    if (window.__rideguideLogbookTrackerInjected) {
        return;
    }

    window.__rideguideLogbookTrackerInjected = true;

    const endpoint = {$endpointJson};
    const csrfToken = {$csrfJson};
    const sessionKey = {$sessionKeyJson};
    const path = window.location.pathname + window.location.search;
    const startedAt = new Date();
    const startedMs = Date.now();

    const durationSeconds = () => Math.max(1, Math.round((Date.now() - startedMs) / 1000));

    const buildPayload = (reason) => ({
        path,
        page_title: document.title || null,
        page_url: window.location.href,
        referrer: document.referrer || null,
        route_name: document.body ? document.body.getAttribute('data-route-name') : null,
        session_key: sessionKey,
        duration_seconds: durationSeconds(),
        started_at: startedAt.toISOString(),
        ended_at: new Date().toISOString(),
        visibility_state: document.visibilityState || reason || null,
    });

    const sendHeartbeat = () => {
        if (!endpoint) return;
        try {
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(buildPayload('heartbeat')),
            }).catch(() => {});
        } catch (e) {}
    };

    // Periodic heartbeat so duration updates in near-real-time (every 30s)
    const HEARTBEAT_MS = 30 * 1000;
    const heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_MS);

    const sendFinal = (reason) => {
        clearInterval(heartbeatTimer);
        try {
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(buildPayload(reason || 'final')),
            }).catch(() => {});
        } catch (e) {}
    };

    // send immediately, then periodically, so duration starts updating right away
    sendHeartbeat();

    window.addEventListener('pagehide', () => sendFinal('pagehide'), { once: true });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            sendFinal('hidden');
        }
    });
})();
</script>
HTML;
    }
}