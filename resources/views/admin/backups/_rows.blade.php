@forelse($backups as $index => $backup)
<tr>
    <td class="rg-td-index">{{ $index + 1 }}</td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <div class="rg-avatar" style="width:32px; height:32px; border-radius:8px; font-size:0.72rem;">
                <i class="fas fa-database" style="font-size:0.78rem;"></i>
            </div>
            <div>
                <p class="rg-user-name mb-0" style="font-size:0.82rem;">{{ $backup['name'] }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $backup['size'] }}</td>
    <td class="rg-td-muted">
        @if($backup['created_at'])
            {{ \Carbon\Carbon::parse($backup['created_at'])->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}
        @else
            N/A
        @endif
    </td>
    <td>
        <div class="rg-action-group">
            <button type="button"
                    class="rg-btn-icon rg-download-btn"
                    data-filename="{{ $backup['name'] }}"
                    title="Download backup">
                <i class="fas fa-download"></i>
            </button>
            <button type="button"
                    class="rg-btn-icon rg-btn-icon-warning rg-restore-btn"
                    data-filename="{{ $backup['name'] }}"
                    title="Restore from this backup">
                <i class="fas fa-undo"></i>
            </button>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="rg-empty">No backups found. Click "Create Backup" to generate one.</td>
</tr>
@endforelse
