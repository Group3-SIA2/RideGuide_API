@forelse($commuters as $index => $commuter)
<tr>
    <td class="rg-td-index">{{ $commuters->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($commuter->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($commuter->user->last_name ?? '?', 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $commuter->user->first_name ?? '—' }} {{ $commuter->user->last_name ?? '' }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $commuter->user->email ?? '—' }}</td>
    <td>
        <span class="rg-role-badge">
            {{ $commuter->discount?->classificationType?->classification_name ?? 'Regular' }}
        </span>
    </td>
    <td class="rg-td-muted">{{ $commuter->discount?->ID_number ?? '—' }}</td>
    <td class="rg-td-muted">{{ $commuter->created_at->format('M d, Y') }}</td>
</tr>
@empty
<tr>
    <td colspan="6" class="rg-empty">No commuters found.</td>
</tr>
@endforelse
