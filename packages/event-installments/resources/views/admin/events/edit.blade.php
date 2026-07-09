@extends('event-installments::admin.layout')

@section('title', 'Edit Event')

@section('content')
    <form method="post" action="{{ route('event-installments.events.update', $event) }}">
        @include('event-installments::admin.events._form')
    </form>
@endsection
