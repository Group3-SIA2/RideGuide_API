@forelse($organizations as $index => $org)
<tr>
    <td class="rg-td-index">{{ $organizations->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar" style="background: var(--rg-accent-dim); color: var(--rg-accent);">
                {{ strtoupper(substr($org->name, 0, 2)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $org->name }}</p>
            </div>
        </div>
    </td>
    <td>
        <span class="rg-role-badge">{{ $org->type }}</span>
    </td>
    <td class="rg-td-muted">{{ $org->address ?? '—' }}</td>
    <td class="rg-td-muted">{{ $org->contact_number ?? '—' }}</td>
    <td class="rg-td-muted">{{ $org->drivers_count }}</td>
    <td>
        <span class="rg-status-badge {{ $org->status === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
            {{ ucfirst($org->status) }}
        </span>
    </td>
</tr>
@empty
<tr>
    <td colspan="7" class="rg-empty">No organizations found.</td>
</tr>
@endforelse
