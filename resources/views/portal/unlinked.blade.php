@extends('layouts.portal')
@section('title', 'Account Notice')

@section('content')
<div style="max-width: 600px; margin: 40px auto;">
    <x-portal.empty-state
        title="Student Profile Pending Link"
        description="Your portal account has been created, but it is not linked to any student record in the school database yet. Please reach out to the school administration office with your admission number or contact number to link your record."
        icon="school"
        actionText="Sign Out"
        :actionUrl="route('portal.logout')"
        badge="Notice"
    />
</div>
@endsection
