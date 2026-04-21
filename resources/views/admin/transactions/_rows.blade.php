@forelse($logs as $log)
    @php
        $redactLogData = function ($value, $key = null) use (&$redactLogData) {
            $sensitiveTerms = ['password', 'passcode', 'otp', 'token', 'secret', 'authorization', 'cookie', 'session', 'credential', 'private_key'];

            if ($key !== null) {
                $normalized = strtolower((string) $key);
                foreach ($sensitiveTerms as $term) {
                    if (str_contains($normalized, $term)) {
                        return '[redacted]';
                    }
                }
            }

            if (is_array($value)) {
                $result = [];
                foreach ($value as $childKey => $childValue) {
                    $result[$childKey] = $redactLogData($childValue, (string) $childKey);
                }
                return $result;
            }

            if (is_string($value) && str_contains($value, '@')) {
                $parts = explode('@', $value, 2);
                if (count($parts) === 2) {
                    $local = $parts[0];
                    $maskedLocal = strlen($local) <= 2 ? str_repeat('*', strlen($local)) : substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));
                    return $maskedLocal . '@' . $parts[1];
                }
                return '[redacted]';
            }

            return $value;
        };

        $safeBefore = $redactLogData($log->before_data ?? []);
        $safeAfter = $redactLogData($log->after_data ?? []);
        $safeMetadata = $redactLogData($log->metadata ?? []);
    @endphp
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
            <button
                type="button"
                class="btn btn-link btn-sm p-0"
                data-toggle="modal"
                data-target="#log-detail-{{ $log->id }}"
            >
                <i class="fas fa-caret-right mr-1"></i>View
            </button>

            <div class="modal fade" id="log-detail-{{ $log->id }}" tabindex="-1" role="dialog" aria-labelledby="log-detail-label-{{ $log->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="log-detail-label-{{ $log->id }}">Transaction Details</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3 p-3 border rounded bg-light">
                                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;">
                                    <span class="badge badge-primary">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</span>
                                    <span class="badge badge-secondary">{{ str_replace('_', ' ', $log->transaction_type) }}</span>
                                    <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($log->status) }}</span>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Timestamp</small>
                                        <div>{{ optional($log->created_at)->format('M d, Y h:i A') }}</div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Actor</small>
                                        <div>{{ $log->actor_email ?? 'System' }}</div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Reference</small>
                                        <div>{{ $log->reference_type ?? '-' }}{{ $log->reference_id ? ': ' . $log->reference_id : '' }}</div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">User ID</small>
                                        <div>{{ $log->actor_user_id ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            @if($log->reason)
                                <div class="mb-3">
                                    <strong>Reason</strong>
                                    <div class="text-muted">{{ $log->reason }}</div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <strong>Before Data</strong>
                                @if(empty($safeBefore))
                                    <div class="text-muted">No before snapshot.</div>
                                @else
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-bordered mb-0">
                                            <tbody>
                                                @foreach($safeBefore as $key => $value)
                                                    <tr>
                                                        <th style="width: 30%;" class="text-muted">{{ $key }}</th>
                                                        <td>
                                                            @if(is_array($value) || is_object($value))
                                                                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                            @else
                                                                {{ is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? '-') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <strong>After Data</strong>
                                @if(empty($safeAfter))
                                    <div class="text-muted">No after snapshot.</div>
                                @else
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-bordered mb-0">
                                            <tbody>
                                                @foreach($safeAfter as $key => $value)
                                                    <tr>
                                                        <th style="width: 30%;" class="text-muted">{{ $key }}</th>
                                                        <td>
                                                            @if(is_array($value) || is_object($value))
                                                                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                            @else
                                                                {{ is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? '-') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <strong>Metadata</strong>
                                @if(empty($safeMetadata))
                                    <div class="text-muted">No metadata recorded.</div>
                                @else
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-bordered mb-0">
                                            <tbody>
                                                @foreach($safeMetadata as $key => $value)
                                                    <tr>
                                                        <th style="width: 30%;" class="text-muted">{{ $key }}</th>
                                                        <td>
                                                            @php
                                                                $displayValue = $value;

                                                                if ($key === 'ip' && is_string($value) && $value !== '') {
                                                                    $displayValue = encrypt($value);
                                                                }
                                                            @endphp
                                                            @if(is_array($value) || is_object($value))
                                                                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($displayValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                            @else
                                                                {{ is_bool($displayValue) ? ($displayValue ? 'true' : 'false') : ($displayValue ?? '-') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="rg-empty">No transactions found.</td>
    </tr>
@endforelse