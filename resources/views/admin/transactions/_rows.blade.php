@forelse($logs as $log)
    <tr>
        <td>
            <span class="text-muted">
                {{ optional($log->created_at)->format('M d, Y h:i A') }}
            </span>
        </td>

        <td>
            <div class="d-flex flex-column">
                <strong>{{ $log->actor_email ?? 'System' }}</strong>
                <small class="text-muted">{{ $log->actor_user_id ? 'User ID: ' . $log->actor_user_id : 'No actor user id' }}</small>
            </div>
        </td>

        <td>
            <span class="rg-role-badge">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</span>
        </td>

        <td>
            <span class="font-weight-semibold">{{ str_replace('_', ' ', $log->transaction_type) }}</span>
        </td>

        <td>
            <small class="text-muted">
                {{ $log->reference_type ?? '-' }}
                @if($log->reference_id)
                    : {{ \Illuminate\Support\Str::limit($log->reference_id, 12) }}
                @endif
            </small>
        </td>

        <td>
            <span class="rg-status-badge {{ $log->status === 'success' ? 'rg-status-active' : 'rg-status-danger' }}">
                {{ ucfirst($log->status) }}
            </span>
        </td>

        <td style="max-width: 420px;">
            <details>
                <summary class="text-primary" style="cursor:pointer;">View</summary>

                @if($log->reason)
                    <div class="mt-2">
                        <strong>Reason:</strong>
                        <div class="text-muted">{{ $log->reason }}</div>
                    </div>
                @endif

                <div class="mt-2">
                    <strong>Before:</strong>
                    <pre class="mb-2" style="white-space:pre-wrap;">{{ json_encode($log->before_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>

                <div>
                    <strong>After:</strong>
                    <pre class="mb-2" style="white-space:pre-wrap;">{{ json_encode($log->after_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>

                <div>
                    <strong>Metadata:</strong>
                    <pre style="white-space:pre-wrap;">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </details>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="rg-empty">No transactions found.</td>
    </tr>
@endforelse