@forelse($drivers as $index => $driver)
<tr>
    <td class="rg-td-index">{{ $drivers->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($driver->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($driver->user->last_name ?? '?', 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $driver->user->first_name ?? '—' }} {{ $driver->user->last_name ?? '' }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $driver->user->email ?? '—' }}</td>
    <td class="rg-td-muted">{{ $driver->license_number ?? '—' }}</td>
    <td class="rg-td-muted">{{ $driver->franchise_number ?? '—' }}</td>
    <td>
        @php $status = $driver->verification_status ?? 'pending'; @endphp
        <span class="rg-status-badge {{ $status === 'verified' ? 'rg-status-active' : 'rg-status-pending' }}">
            {{ ucfirst($status) }}
        </span>
    </td>
    <td class="rg-td-muted">{{ $driver->created_at->format('M d, Y') }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="rg-empty">No drivers found.</td>
</tr>
@endforelse
