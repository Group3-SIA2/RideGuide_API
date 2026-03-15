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
    <td class="rg-td-muted" style="max-width:280px;">{{ $org->description ?? '—' }}</td>
    <td class="rg-td-muted">{{ $org->hq_address ?? '—' }}</td>
    <td class="rg-td-muted">{{ $org->drivers_count }}</td>
    <td>
        @if($showDeleted ?? false)
            <span class="rg-status-badge rg-status-pending">Deleted</span>
        @else
            <span class="rg-status-badge {{ $org->status === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                {{ ucfirst($org->status) }}
            </span>
        @endif
    </td>
    <td>
        @if($showDeleted ?? false)
            <form method="POST" action="{{ route('admin.organizations.restore', $org->id) }}">
                @csrf
                <button type="submit" class="rg-btn rg-btn-sm"
                        style="background:rgba(34,197,94,0.12);color:#15803d;border:none;">
                    <i class="fas fa-undo"></i> Restore
                </button>
            </form>
        @else
            <div class="d-flex gap-1">
                <a href="{{ route('admin.organizations.edit', $org->id) }}"
                   class="rg-btn rg-btn-sm" style="background:var(--rg-accent-dim);color:var(--rg-accent);border:none;">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="{{ route('admin.organizations.destroy', $org->id) }}"
                      onsubmit="return confirm('Delete {{ addslashes($org->name) }}? It can be restored later.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rg-btn rg-btn-sm rg-btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="8" class="rg-empty">
        {{ ($showDeleted ?? false) ? 'No deleted organizations.' : 'No organizations found.' }}
    </td>
</tr>
@endforelse
