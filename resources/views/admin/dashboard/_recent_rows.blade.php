@forelse($recentUsers as $index => $user)
<tr>
    <td class="rg-td-index">{{ $index + 1 }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $user->email }}</td>
    <td>
        <span class="rg-role-badge">{{ ucfirst(str_replace('_', ' ', $user->roles->first()?->name ?? 'N/A')) }}</span>
    </td>
    <td class="rg-td-muted">{{ $user->created_at->format('M d, Y') }}</td>
    <td>
        <span class="rg-status-badge {{ ($user->status ?? 'active') === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
            {{ ucfirst($user->status ?? 'active') }}
        </span>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="rg-empty">No users found.</td>
</tr>
@endforelse
