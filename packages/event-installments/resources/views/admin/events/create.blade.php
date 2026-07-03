@extends('event-installments::admin.layout')

@section('title', 'Create Event')

@section('content')
    <form method="post" action="{{ route('event-installments.events.store') }}">
        @include('event-installments::admin.events._form')
    </form>
@endsection
