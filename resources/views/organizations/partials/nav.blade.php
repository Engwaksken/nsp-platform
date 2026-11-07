{{-- Tabs for one organization. Expects $organization and $membership. --}}
<nav class="tags" aria-label="{{ $organization->name }} sections" style="margin:1rem 0">
    <a class="tag" href="{{ route('organizations.show', $organization) }}" @if (request()->routeIs('organizations.show', 'organizations.startup')) aria-current="true" @endif>Portfolio</a>
    <a class="tag" href="{{ route('organizations.members', $organization) }}" @if (request()->routeIs('organizations.members')) aria-current="true" @endif>Members</a>
    <a class="tag" href="{{ route('organizations.index') }}">All my organizations</a>
</nav>
