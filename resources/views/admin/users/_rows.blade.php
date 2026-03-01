@forelse($users as $index => $user)
<tr>
    <td class="rg-td-index">{{ $users->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
                @if($user->middle_name)
                    <span class="rg-td-muted" style="font-size:0.72rem;">{{ $user->middle_name }}</span>
                @endif
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $user->email }}</td>
    <td>
        <span class="rg-role-badge">{{ ucfirst($user->role?->name ?? 'N/A') }}</span>
    </td>
    <td>
        <span class="rg-status-badge {{ $user->email_verified_at ? 'rg-status-active' : 'rg-status-pending' }}">
            {{ $user->email_verified_at ? 'Verified' : 'Pending' }}
        </span>
    </td>
    <td class="rg-td-muted">{{ $user->created_at->format('M d, Y') }}</td>
</tr>
@empty
<tr>
    <td colspan="6" class="rg-empty">No users found.</td>
</tr>
@endforelse
